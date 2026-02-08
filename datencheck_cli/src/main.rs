use clap::Parser;
use ged_io::Gedcom;
use mysql::prelude::*;
use mysql::*;
use regex;
use rphonetic::{Cologne, Encoder};
use serde::Serialize;
use serde_json;
use strsim::levenshtein;

#[derive(Parser)]
#[command(author, version, about, long_about = None)]
struct Cli {
    /// Mode to run in (bulk, interactive)
    #[arg(long, default_value = "bulk")]
    mode: String,

    /// Database Host
    #[arg(long, default_value = "localhost")]
    db_host: String,

    /// Database User
    #[arg(long)]
    db_user: String,

    /// Database Password
    #[arg(long)]
    db_pass: String,

    /// Database Name
    #[arg(long)]
    db_name: String,

    /// Table Prefix (e.g. wt_)
    #[arg(long, default_value = "wt_")]
    db_prefix: String,

    /// Tree ID (i_file)
    #[arg(long)]
    tree_id: i32,

    /// Given Name (for interactive mode)
    #[arg(long)]
    given_name: Option<String>,

    /// Surname (for interactive mode)
    #[arg(long)]
    surname: Option<String>,

    /// Birth Date (for interactive mode)
    #[arg(long)]
    birth_date: Option<String>,

    /// Husband ID (for family check)
    #[arg(long)]
    husband_id: Option<String>,

    /// Wife ID (for family check)
    #[arg(long)]
    wife_id: Option<String>,

    /// Fuzzy threshold for high age (over 80)
    #[arg(long, default_value = "6")]
    fuzzy_diff_high_age: i32,

    /// Fuzzy threshold for default cases (baptism, marriage, etc)
    #[arg(long, default_value = "2")]
    fuzzy_diff_default: i32,

    /// Child Given Name (for sibling check)
    #[arg(long)]
    child_given: Option<String>,

    /// Child Surname (for sibling check)
    #[arg(long)]
    child_surname: Option<String>,

    /// Child Birth Date (for sibling check)
    #[arg(long)]
    child_birth: Option<String>,
}

#[derive(Debug, Clone)]
struct Person {
    id: String,
    name: String,
    birth_year: Option<i32>,
    death_year: Option<i32>,
}

#[derive(Serialize)]
struct AnalysisResult {
    check_type: String,
    description: String,
    data: serde_json::Value,
}

#[derive(Serialize)]
struct DuplicateStart {
    id1: String,
    name1: String,
    id2: String,
    name2: String,
    distance: usize,
    phonetic_match: bool,
}

fn main() {
    let cli = Cli::parse();
    let url = format!(
        "mysql://{}:{}@{}:3306/{}",
        cli.db_user, cli.db_pass, cli.db_host, cli.db_name
    );

    let pool = match Pool::new(url.as_str()) {
        Ok(p) => p,
        Err(e) => {
            print_error(&format!("DB Connection Error: {}", e));
            return;
        }
    };

    let mut conn = match pool.get_conn() {
        Ok(c) => c,
        Err(e) => {
            print_error(&format!("DB Pool Error: {}", e));
            return;
        }
    };

    if cli.mode == "interactive" {
        run_interactive_mode(&cli, &mut conn);
    } else if cli.mode == "family_check" {
        run_family_check(&cli, &mut conn);
    } else if cli.mode == "sibling_check" {
        run_sibling_check(&cli, &mut conn);
    } else {
        run_bulk_mode(&cli, &mut conn);
    }
}

fn normalize_name(name: &str) -> String {
    name.replace('/', "")
        .replace("  ", " ")
        .trim()
        .to_lowercase()
}

fn map_month(month: &str) -> Option<&'static str> {
    match month.to_lowercase().as_str() {
        "januar" | "jan" | "01" | "1" => Some("JAN"),
        "februar" | "feb" | "02" | "2" => Some("FEB"),
        "märz" | "march" | "mar" | "03" | "3" => Some("MAR"),
        "april" | "apr" | "04" | "4" => Some("APR"),
        "mai" | "may" | "05" | "5" => Some("MAY"),
        "juni" | "june" | "jun" | "06" | "6" => Some("JUN"),
        "juli" | "july" | "jul" | "07" | "7" => Some("JUL"),
        "august" | "aug" | "08" | "8" => Some("AUG"),
        "september" | "sep" | "09" | "9" => Some("SEP"),
        "oktober" | "october" | "oct" | "10" => Some("OCT"),
        "november" | "nov" | "11" => Some("NOV"),
        "dezember" | "december" | "dec" | "12" => Some("DEC"),
        _ => None,
    }
}

/// Parses a date string into (Year, Month, Day)
/// Supports: 13.01.2026, 13. Januar 2026, 13 JAN 2026
fn parse_gedcom_date(date_str: &str) -> (Option<i32>, Option<&'static str>, Option<i32>) {
    let s = date_str.trim().replace('.', " ");
    let parts: Vec<&str> = s.split_whitespace().collect();

    // Try to find a 4-digit year first
    let year_re = regex::Regex::new(r"\b(\d{4})\b").unwrap();
    let year = year_re
        .find(date_str)
        .and_then(|m| m.as_str().parse::<i32>().ok());

    let mut day = None;
    let mut month = None;

    if parts.len() >= 2 {
        for part in &parts {
            if let Ok(d) = part.parse::<i32>() {
                if d > 0 && d <= 31 && day.is_none() {
                    day = Some(d);
                }
            } else if month.is_none() {
                month = map_month(part);
            }
        }
    }

    (year, month, day)
}

/// Parses age string like "56y 5m 3w 2d" or "56"
fn parse_age_to_years(age_str: &str) -> Option<f32> {
    if age_str.is_empty() {
        return None;
    }

    let re = regex::Regex::new(r"(\d+)\s*([ymwd]?)").unwrap();
    let mut total_years = 0.0;
    let mut found = false;

    for cap in re.captures_iter(age_str) {
        let val = cap[1].parse::<f32>().unwrap_or(0.0);
        let unit = cap.get(2).map(|m| m.as_str()).unwrap_or("y");

        match unit {
            "m" => total_years += val / 12.0,
            "w" => total_years += val / 52.0,
            "d" => total_years += val / 365.0,
            _ => total_years += val, // Default to years
        }
        found = true;
    }

    if found { Some(total_years) } else { None }
}

fn is_date_plausible(
    target_yr: i32,
    candidate_yr: i32,
    context_age: Option<f32>,
    cli: &Cli,
) -> bool {
    let diff = (target_yr - candidate_yr).abs();

    // Logic: Higher age = higher uncertainty
    let max_diff = if let Some(age) = context_age {
        if age > 80.0 {
            cli.fuzzy_diff_high_age
        } else {
            cli.fuzzy_diff_default
        }
    } else {
        cli.fuzzy_diff_default // Default (Baptism/Marriage usually more accurate)
    };

    diff <= max_diff
}

fn run_interactive_mode(cli: &Cli, conn: &mut PooledConn) {
    let given = cli.given_name.as_deref().unwrap_or("");
    let surname = cli.surname.as_deref().unwrap_or("");
    let full_input = format!("{} {}", given, surname);
    let normalized_input = normalize_name(&full_input);

    let encoder = Cologne;
    let input_phonetic = encoder.encode(&normalized_input);

    // 1. Search in wt_name for candidates
    let name_table = format!("{}name", cli.db_prefix);
    let indi_table = format!("{}individuals", cli.db_prefix);

    // We search for candidates with the same surname or similar
    let query = format!(
        "SELECT n_id, n_full FROM {} WHERE i_file = ? AND n_surname LIKE ?",
        name_table
    );

    let surname_pattern = format!("%{}%", surname);
    let rows: Vec<(String, String)> = match conn.exec(query, (cli.tree_id, surname_pattern)) {
        Ok(r) => r,
        Err(e) => {
            print_error(&format!("Name Query Error: {}", e));
            return;
        }
    };

    let mut possible_duplicates = Vec::new();
    let (target_year, _, _) = parse_gedcom_date(cli.birth_date.as_deref().unwrap_or(""));

    for (id, full_name) in rows {
        let normalized_candidate = normalize_name(&full_name);
        let dist = levenshtein(&normalized_input, &normalized_candidate);
        let candidate_phonetic = encoder.encode(&normalized_candidate);
        let phonetic_match = input_phonetic == candidate_phonetic && !input_phonetic.is_empty();

        if dist < 5 || phonetic_match {
            // Fetch GEDCOM to check dates
            let ged_query = format!(
                "SELECT i_gedcom FROM {} WHERE i_file = ? AND i_id = ?",
                indi_table
            );
            let ged_rows: Vec<String> =
                conn.exec(ged_query, (cli.tree_id, &id)).unwrap_or_default();

            let mut birth_match = true;
            if let Some(target_yr) = target_year {
                if let Some(ged) = ged_rows.first() {
                    // Extract birth and death years from GEDCOM
                    let birth_re = regex::Regex::new(r"1 BIRT\n2 DATE (.*)").unwrap();
                    let deat_re = regex::Regex::new(r"1 DEAT\n2 DATE (.*)").unwrap();

                    let candidate_birth = birth_re
                        .captures(ged)
                        .and_then(|c| c.get(1))
                        .map(|m| parse_gedcom_date(m.as_str()));

                    let candidate_death = deat_re
                        .captures(ged)
                        .and_then(|c| c.get(1))
                        .map(|m| parse_gedcom_date(m.as_str()));

                    let age_re = regex::Regex::new(r"2 AGE (.*)").unwrap();
                    let explicit_age = age_re
                        .captures(ged)
                        .and_then(|cap| cap.get(1))
                        .map(|m| parse_age_to_years(m.as_str()))
                        .flatten();

                    let mut cb_yr_val = candidate_birth.and_then(|(y, _, _)| y);
                    let cd_yr_val = candidate_death.and_then(|(y, _, _)| y);

                    // Estimate birth year from death date and age if birth year is missing
                    if cb_yr_val.is_none() {
                        if let (Some(cd_yr), Some(age)) = (cd_yr_val, explicit_age) {
                            cb_yr_val = Some(cd_yr - age.round() as i32);
                        }
                    }

                    if let Some(cb_yr) = cb_yr_val {
                        // Calculate age at death for uncertainty logic
                        let death_age = if let Some(age) = explicit_age {
                            Some(age)
                        } else if let (Some(cd_yr), Some(cb_yr_val)) = (cd_yr_val, Some(cb_yr)) {
                            Some((cd_yr - cb_yr_val) as f32)
                        } else {
                            None
                        };

                        if !is_date_plausible(target_yr, cb_yr, death_age, cli) {
                            birth_match = false;
                        }
                    }
                }
            }

            if birth_match {
                possible_duplicates.push(DuplicateStart {
                    id1: "NEW".to_string(),
                    name1: full_input.clone(),
                    id2: id,
                    name2: full_name,
                    distance: dist,
                    phonetic_match,
                });
            }
        }
    }

    let result = AnalysisResult {
        check_type: "interactive_duplicates".to_string(),
        description: format!("Found {} potential matches", possible_duplicates.len()),
        data: serde_json::to_value(possible_duplicates).unwrap(),
    };
    println!("{}", serde_json::to_string_pretty(&result).unwrap());
}

fn run_family_check(cli: &Cli, conn: &mut PooledConn) {
    let husb = cli.husband_id.as_deref().unwrap_or("").trim_matches('@');
    let wife = cli.wife_id.as_deref().unwrap_or("").trim_matches('@');

    let fam_table = format!("{}families", cli.db_prefix);
    let query = format!(
        "SELECT f_id FROM {} WHERE f_file = ? AND f_husb = ? AND f_wife = ?",
        fam_table
    );

    let rows: Vec<String> = match conn.exec(query, (cli.tree_id, husb, wife)) {
        Ok(r) => r,
        Err(e) => {
            print_error(&format!("Family Query Error: {}", e));
            return;
        }
    };

    let result = AnalysisResult {
        check_type: "family_check".to_string(),
        description: format!("Found {} existing families", rows.len()),
        data: serde_json::to_value(rows).unwrap(),
    };
    println!("{}", serde_json::to_string_pretty(&result).unwrap());
}

fn run_bulk_mode(cli: &Cli, conn: &mut PooledConn) {
    let table_name = format!("{}individuals", cli.db_prefix);
    let query = format!("SELECT i_gedcom FROM {} WHERE i_file = ?", table_name);

    let raw_gedcom_rows: Vec<String> = match conn.exec(query, (cli.tree_id,)) {
        Ok(rows) => rows,
        Err(e) => {
            print_error(&format!("DB Query Error: {}", e));
            return;
        }
    };

    if raw_gedcom_rows.is_empty() {
        print_error("No individuals found for this tree ID.");
        return;
    }

    let mut full_gedcom = String::from("0 HEAD\n1 SOUR WEBTREES\n");
    for row in raw_gedcom_rows {
        full_gedcom.push_str(&row);
        full_gedcom.push('\n');
    }
    full_gedcom.push_str("0 TRLR\n");

    let mut parser = match Gedcom::new(full_gedcom.chars()) {
        Ok(p) => p,
        Err(e) => {
            print_error(&format!("Parser Init Error: {:?}", e));
            return;
        }
    };

    if let Ok(data) = parser.parse_data() {
        let mut people = Vec::new();

        for indi in &data.individuals {
            let id = indi.xref.as_deref().unwrap_or("?").to_string();

            let mut birth_year = None;
            let mut death_year = None;

            for event in &indi.events {
                let event_type = format!("{:?}", event.event).to_uppercase();
                if event_type.contains("BIRTH") || event_type.contains("BIRT") {
                    if let Some(date) = &event.date {
                        let (y, _, _) = parse_gedcom_date(date.value.as_deref().unwrap_or(""));
                        birth_year = y;
                    }
                } else if event_type.contains("DEATH") || event_type.contains("DEAT") {
                    if let Some(date) = &event.date {
                        let (y, _, _) = parse_gedcom_date(date.value.as_deref().unwrap_or(""));
                        death_year = y;
                    }
                    // Try to catch AGE from the value or other fields if ged_io supports it
                    // Based on ged_io structure, AGE often ends up in value for events if not parsed specifically
                    if let Some(val) = &event.value {
                        if let Some(age) = parse_age_to_years(val) {
                            if birth_year.is_none() && death_year.is_some() {
                                birth_year = Some(death_year.unwrap() - age.round() as i32);
                            }
                        }
                    }
                }
            }

            if let Some(name_struct) = &indi.name {
                if let Some(name_val) = &name_struct.value {
                    people.push(Person {
                        id,
                        name: name_val.clone(),
                        birth_year,
                        death_year,
                    });
                }
            }
        }

        let mut duplicates = Vec::new();
        let encoder = Cologne;

        for i in 0..people.len() {
            for j in (i + 1)..people.len() {
                let p1 = &people[i];
                let p2 = &people[j];

                let n1 = normalize_name(&p1.name);
                let n2 = normalize_name(&p2.name);

                let dist = levenshtein(&n1, &n2);
                let max_len = n1.len().max(n2.len());

                let p1_phonetic = encoder.encode(&n1);
                let p2_phonetic = encoder.encode(&n2);
                let phonetic_match = p1_phonetic == p2_phonetic && !p1_phonetic.is_empty();

                if (dist <= 3 && max_len > 3) || phonetic_match {
                    // Refined date check
                    let mut date_match = true;
                    if let (Some(b1), Some(b2)) = (p1.birth_year, p2.birth_year) {
                        let age = match (p1.death_year, p1.birth_year) {
                            (Some(d), Some(b)) => Some((d - b) as f32),
                            _ => None,
                        };
                        if !is_date_plausible(b1, b2, age, cli) {
                            date_match = false;
                        }
                    }

                    if date_match {
                        duplicates.push(DuplicateStart {
                            id1: p1.id.clone(),
                            name1: p1.name.clone(),
                            id2: p2.id.clone(),
                            name2: p2.name.clone(),
                            distance: dist,
                            phonetic_match,
                        });
                    }
                }
            }
        }

        let result = AnalysisResult {
            check_type: "name_duplicates".to_string(),
            description: format!("Found {} possible duplicates", duplicates.len()),
            data: serde_json::to_value(duplicates).unwrap(),
        };

        println!("{}", serde_json::to_string_pretty(&result).unwrap());
    } else {
        print_error("Failed to parse GEDCOM data.");
    }
}

fn run_sibling_check(cli: &Cli, conn: &mut PooledConn) {
    let husb = cli.husband_id.as_deref().unwrap_or("").trim_matches('@');
    let wife = cli.wife_id.as_deref().unwrap_or("").trim_matches('@');
    let given = cli.child_given.as_deref().unwrap_or("");
    let surname = cli.child_surname.as_deref().unwrap_or("");
    let birth_date = cli.child_birth.as_deref().unwrap_or("");

    if (husb.is_empty() && wife.is_empty()) || (given.is_empty() && surname.is_empty()) {
        return;
    }

    let fam_table = format!("{}families", cli.db_prefix);
    let link_table = format!("{}links", cli.db_prefix);
    let indi_table = format!("{}individuals", cli.db_prefix);

    // 1. Find the family
    let mut query = format!("SELECT f_id FROM {} WHERE f_file = ?", fam_table);
    let mut params = vec![Value::from(cli.tree_id)];

    if !husb.is_empty() && !wife.is_empty() {
        query.push_str(" AND f_husb = ? AND f_wife = ?");
        params.push(Value::from(husb));
        params.push(Value::from(wife));
    } else if !husb.is_empty() {
        query.push_str(" AND f_husb = ?");
        params.push(Value::from(husb));
    } else {
        query.push_str(" AND f_wife = ?");
        params.push(Value::from(wife));
    }

    let fam_ids: Vec<String> = conn.exec(query, params).unwrap_or_default();
    if fam_ids.is_empty() {
        return;
    }

    let encoder = Cologne;
    let input_name = format!("{} {}", given, surname);
    let normalized_input = normalize_name(&input_name);
    let input_phonetic = encoder.encode(&normalized_input);
    let (target_year, _, _) = parse_gedcom_date(birth_date);

    let mut matches = Vec::new();

    for fam_id in fam_ids {
        // 2. Find all children of this family
        let child_query = format!(
            "SELECT l_from FROM {} WHERE l_file = ? AND l_to = ? AND l_role = 'CHIL'",
            link_table
        );
        let child_ids: Vec<String> = conn
            .exec(child_query, (cli.tree_id, &fam_id))
            .unwrap_or_default();

        for cid in child_ids {
            let ged_query = format!(
                "SELECT i_gedcom FROM {} WHERE i_file = ? AND i_id = ?",
                indi_table
            );
            let ged: Option<String> = conn.exec_first(ged_query, (cli.tree_id, &cid)).unwrap();

            if let Some(ged_content) = ged {
                // Check name and birth
                let name_re = regex::Regex::new(r"1 NAME (.*)").unwrap();
                let birth_re = regex::Regex::new(r"1 BIRT\n2 DATE (.*)").unwrap();

                let candidate_name = name_re
                    .captures(&ged_content)
                    .and_then(|c| c.get(1))
                    .map(|m| m.as_str())
                    .unwrap_or("");
                let normalized_candidate = normalize_name(candidate_name);
                let dist = levenshtein(&normalized_input, &normalized_candidate);
                let candidate_phonetic = encoder.encode(&normalized_candidate);
                let phonetic_match =
                    input_phonetic == candidate_phonetic && !input_phonetic.is_empty();

                if dist < 5 || phonetic_match {
                    let mut date_match = true;
                    if let Some(ty) = target_year {
                        let candidate_birth = birth_re
                            .captures(&ged_content)
                            .and_then(|c| c.get(1))
                            .map(|m| parse_gedcom_date(m.as_str()));

                        if let Some((Some(cy), _, _)) = candidate_birth {
                            if !is_date_plausible(ty, cy, None, cli) {
                                date_match = false;
                            }
                        }
                    }

                    if date_match {
                        matches.push(DuplicateStart {
                            id1: "NEW".to_string(),
                            name1: input_name.clone(),
                            id2: cid,
                            name2: candidate_name.to_string(),
                            distance: dist,
                            phonetic_match,
                        });
                    }
                }
            }
        }
    }

    let result = AnalysisResult {
        check_type: "sibling_check".to_string(),
        description: format!("Found {} potential duplicate siblings", matches.len()),
        data: serde_json::to_value(matches).unwrap(),
    };
    println!("{}", serde_json::to_string_pretty(&result).unwrap());
}

fn print_error(msg: &str) {
    let error = AnalysisResult {
        check_type: "error".to_string(),
        description: msg.to_string(),
        data: serde_json::json!({}),
    };
    println!("{}", serde_json::to_string(&error).unwrap());
}
