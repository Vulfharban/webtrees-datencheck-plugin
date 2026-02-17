<?php

namespace Wolfrum\Datencheck\Helpers;

/**
 * Helper class for name comparison and normalization.
 * Handles common name variations across German, Polish, Latin, English, Dutch,
 * Czech, Russian, French, Spanish, Italian, and Scandinavian regions.
 */
class NameHelper
{
    /**
     * Groups of equivalent names.
     */
    private static array $equivalents = [
        // Abraham variants
        ['Abraham', 'Abramo', 'Abrahám'],
        // Adalbert variants
        ['Adalbert', 'Wojciech', 'Albertus', 'Albert', 'Alberto', 'Albrecht'],
        // Alois/Aloysius variants
        ['Alois', 'Aloys', 'Aloysius', 'Alouise', 'Louis', 'Ludwig', 'Ludovicus'],
        // Adam variants
        ['Adam', 'Adamo', 'Ádám'],
        // Adrian variants
        ['Adrian', 'Adriano', 'Adriaan'],
        // Agathe variants
        ['Agathe', 'Agatha', 'Agata'],
        // Agnes variants
        ['Agnes', 'Agnieszka', 'Ines', 'Anežka', 'Anezka', 'Agnese'],
        // Alexander variants
        ['Alexander', 'Aleksander', 'Olek', 'Aleksandr', 'Alessandro', 'Alejandro', 'Alexandre'],
        // Alice variants
        ['Alice', 'Alicja', 'Adelheid'],
        // Alois variants
        ['Alois', 'Aloys', 'Aloysius', 'Aloisio'],
        // Amalie variants
        ['Amalie', 'Amalia', 'Amelia', 'Mali', 'Amálie'],
        // Andrew variants
        ['Andreas', 'Andrzej', 'Andrew', 'Andrija', 'Andre', 'Ondřej', 'Ondrej', 'Andrea', 'Andrés', 'Andres', 'Anders'],
        // Angela variants
        ['Angela', 'Angelika', 'Angelina', 'Angèle'],
        // Anna variants
        ['Anna', 'Anne', 'Hanna', 'Hannah', 'Ania', 'Ann', 'Ana', 'Anneke', 'Annie', 'Annette'],
        // Anthony variants
        ['Anton', 'Antoni', 'Antonius', 'Anthony', 'Antonín', 'Antonin', 'Antonio', 'Antoine', 'Tony'],
        // Apolonia variants
        ['Apolonia', 'Polly', 'Pauline', 'Paulina', 'Apollonia'],
        // Arnold variants
        ['Arnold', 'Arno', 'Arnoald', 'Arnault'],
        // August variants
        ['August', 'Augustus', 'Augustin', 'Augusto', 'Agostino'],
        // Barbara variants
        ['Barbara', 'Basia', 'Barbora', 'Bärbel', 'Babette', 'Barbel', 'Bärbli', 'Wawerl'],
        // Bartholomew variants
        ['Bartholomew', 'Bartłomiej', 'Bartosz', 'Bartholomaeus', 'Bartolomeo', 'Bartolomé', 'Bartolome', 'Bernhard', 'Barthel'],
        // Beate variants
        ['Beate', 'Beata', 'Beatrice', 'Beatrix'],
        // Benedict variants
        ['Benedict', 'Benedikt', 'Benedykt', 'Benedictus', 'Benedetto', 'Benito'],
        // Bernard variants
        ['Bernhard', 'Bernard', 'Bernardo', 'Bernat'],
        // Bridget variants
        ['Bridget', 'Birgitta', 'Berit', 'Brita', 'Brigitte', 'Gitta', 'Brida', 'Birte'],
        // Bruno variants
        ['Bruno', 'Brunone'],
        // Casimir variants
        ['Kasmier', 'Casmier', 'Casimir', 'Kazimierz', 'Casimir', 'Kazimír', 'Casimer'],
        // Caspar variants
        ['Caspar', 'Kaspar', 'Kacper', 'Caspian', 'Gaspare', 'Gaspar'],
        // Charles variants
        ['Karl', 'Carl', 'Carolus', 'Charles', 'Karol', 'Karel', 'Carlo', 'Carlos', 'Kalle'],
        // Charlotte variants
        ['Charlotte', 'Charlene', 'Charline', 'Charlotta', 'Carlotta', 'Carlota', 'Šarlota', 'Sarlota', 'Sharlotta', 'Lotte', 'Lotti', 'Lottie', 'Caroline', 'Carolina', 'Carola', 'Karoline', 'Karolina', 'Carolin', 'Karola', 'Karolina', 'Lina', 'Line', 'Kathleen'],
        // Christian variants
        ['Christian', 'Chrystyan', 'Chystian', 'Kristian', 'Christianus', 'Krystian', 'Cristiano', 'Carsten', 'Karsten', 'Chrétien'],
        // Christina variants
        ['Christina', 'Kristina', 'Chrystyna', 'Krystyna', 'Kristine', 'Christine', 'Cristina', 'Kirsten', 'Kiersten', 'Kerstin', 'Stina', 'Christiana', 'Christianna'],
        // Christopher variants
        ['Christoph', 'Christopherus', 'Christophorus', 'Krzysztof', 'Christopher', 'Cristoforo', 'Cristóbal', 'Cristobal'],
        // Claire variants
        ['Claire', 'Clara', 'Klara', 'Chiara'],
        // Clement variants
        ['Clement', 'Klemens', 'Clemens', 'Clemente'],
        // David variants
        ['David', 'Davide'],
        // Denis variants
        ['Denis', 'Dennis', 'Dionysius', 'Dionigi'],
        // Dominic variants
        ['Dominic', 'Dominik', 'Dominicus', 'Domenico', 'Domingo'],
        // Dolores variants
        ['Dolores', 'Delores', 'Deloris', 'Dolors', 'Addolorata', 'Dolorosa', 'Lola', 'Lolita', 'Loli', 'Dores'],
        // Dorothy variants
        ['Dorothea', 'Dorothy', 'Dorota', 'Dora', 'Dorotea', 'Dörthe'],
        // Edward variants
        ['Eduard', 'Eduardus', 'Edward', 'Edvard', 'Edoardo', 'Eduardo'],
        // Elizabeth variants
        ['Elsbeth', 'Elisabeth', 'Elizabeth', 'Elżbieta', 'Elzbieta', 'Alžběta', 'Alyzbeta', 'Yelizaveta', 'Elisabetta', 'Elisa', 'Elise', 'Lisa', 'Liesbeth', 'Liesel', 'Betty', 'Bessie', 'Beth', 'Sisi', 'Sissi', 'Lisi', 'Else', 'Lisbeth', 'Betka', 'Elize'],
        // Emil variants
        ['Emil', 'Emilius', 'Emilio', 'Émile'],
        // Emmanuel variants
        ['Emmanuel', 'Emanuel', 'Emanuele', 'Manuel'],
        // Erik variants
        ['Erik', 'Eirik', 'Erich', 'Eric'],
        // Ernest variants
        ['Ernst', 'Ernest', 'Ernesto'],
        // Eugene variants
        ['Eugen', 'Eugene', 'Eugenio', 'Eugène'],
        // Fabian variants
        ['Fabian', 'Fabianus', 'Fabien', 'Fabiano', 'Fabián', 'Fabijn', 'Fabijan', 'Fabio', 'Fabiana', 'Fabienne', 'Fabiane', 'Fabiola'],
        // Ferdinand variants
        ['Ferdinand', 'Fernando', 'Hernando'],
        // Francis variants
        ['Franciszek', 'Franz', 'Francis', 'Franciscus', 'Frank', 'František', 'Frantisek', 'Francesco', 'Francisco', 'François', 'Francois'],
        // Frederick variants
        ['Friedrich', 'Fryderyk', 'Frederic', 'Frederick', 'Fredericus', 'Fritz', 'Bedřich', 'Bedrich', 'Federico'],
        // Gabriel variants
        ['Gabriel', 'Gabriele', 'Gábor'],
        // Genevieve variants
        ['Genevieve', 'Genowefa', 'Genowefa', 'Genoveva', 'Genovana', 'Ginette'],
        // George variants
        ['Georg', 'George', 'Georgius', 'Jörg', 'Juergen', 'Jürgen', 'Jerzy', 'Yury', 'Jiří', 'Jiri', 'Giorgio', 'Jorge', 'Jørgen', 'Jörgen'],
        // Gerald variants
        ['Gerald', 'Gerhard', 'Gerard', 'Gherardo', 'Gérard'],
        // Gertrude variants
        ['Gertrud', 'Gertrude', 'Gertraude'],
        // Gilbert variants
        ['Gilbert', 'Gilberto'],
        // Godfrey variants
        ['Gottfried', 'Godfrey', 'Geoffrey', 'Goffredo'],
        // Gottlieb/Theophilus variants
        ['Gottlieb', 'Bogumił', 'Bogumil', 'Boguslaw', 'Bogusław', 'Theophilus', 'Amadeus', 'Amedeo'],
        // Gregory variants
        ['Gregory', 'Gregor', 'Grzegorz', 'Gregorius', 'Gregorio'],
        // Hans variants
        ['Hans', 'Hannes'],
        // Hedwig variants
        ['Hedwig', 'Jadwiga', 'Hadwig'],
        // Helen variants
        ['Helena', 'Helen', 'Helene', 'Elena', 'Yelena'],
        // Henry variants
        ['Heinrich', 'Henry', 'Henryk', 'Henricus', 'Henri', 'Enrico', 'Enrique', 'Indřich', 'Jindrich', 'Heinz', 'Heini'],
        // Herbert variants
        ['Herbert', 'Heribert'],
        // Herman variants
        ['Hermann', 'Herman', 'Ermanno'],
        // Hubert variants
        ['Hubert', 'Hubertus', 'Uberto'],
        // Hugo variants
        ['Hugo', 'Hugh', 'Ugo'],
        // Ignatius variants
        ['Ignatius', 'Ignacy', 'Ignatz', 'Ignazio', 'Ignacio'],
        // Isaac variants
        ['Isaac', 'Isaak', 'Isacco'],
        // Jacob variants
        ['Jacob', 'Jakob', 'Jacobus', 'James', 'Kuba', 'Iacobus', 'Jakub', 'Giacomo', 'Diego', 'Jaime', 'Jacques', 'Ib'],
        // Joachim variants
        ['Joachim', 'Gioacchino', 'Joaquin'],
        // John/Johannes variants
        ['Jan', 'Johann', 'Johannes', 'John', 'Ivan', 'Janek', 'Janko', 'Joannes', 'Jean', 'Janina', 'Giovanni', 'Juan', 'Ioannes', 'Iwan', 'Jens', 'Johan'],
        // Joseph variants
        ['Joseph', 'Josef', 'Józef', 'Joe', 'Giuseppe', 'José', 'Jose', 'Sepp', 'Pepi', 'Beppo'],
        // Judith variants
        ['Judith', 'Jutta', 'Giuditta'],
        // Julian variants
        ['Julian', 'Julius', 'Giuliano', 'Giulio', 'Jules'],
        // Justine variants
        ['Justyna', 'Justine', 'Justina', 'Giustina'],
        // Katarina variants
        ['Trin', 'Katrin', 'Cathrin', 'Katharina', 'Catharina', 'Katarzyna', 'Katarina', 'Kateřina', 'Katerina', 'Katherine', 'Catherine', 'Kate', 'Katie', 'Kasia', 'Ekaterina', 'Katherina', 'Caterina', 'Catherina', 'Kati', 'Käthi', 'Kaia', 'Kaja', 'Cathryn'],
        // Konrad/Cornelius variants
        ['Conrad', 'Konrad', 'Cornelius', 'Corrado', 'Koni', 'Kurt'],
        // Ladislaus variants
        ['Władysław', 'Wladyslaw', 'Ladislaus', 'Vladislav', 'Ladislao', 'Walter', 'Waurzyniec', 'Wawrzyniec', 'Lawrence', 'Laurentius'],
        // Lawrence variants (merged into above for regional swaps)
        ['Lorenz', 'Lawrence', 'Laurentius', 'Laurent', 'Lorenzo'],
        // Leo variants
        ['Leo', 'Leon', 'Leonard', 'Leonardo', 'Léon'],
        // Leopold variants
        ['Leopold', 'Luitpold'],
        // Louis variants
        ['Ludwig', 'Ludovicus', 'Ludwik', 'Louis', 'Ludvík', 'Ludvik', 'Luigi', 'Luis', 'Lutz'],
        // Louise variants
        ['Louisa', 'Louise', 'Ludovica', 'Ludwika', 'Luise', 'Ludovika', 'Luisa', 'Eloise', 'Lovisa'],
        // Lucy variants
        ['Lucia', 'Lucy', 'Lucie', 'Luzia'],
        // Luke variants
        ['Luke', 'Lukas', 'Lucas', 'Łukasz', 'Luca', 'Lucas'],
        // Magdalena variants
        ['Magdalena', 'Magdalene', 'Madeline', 'Madeleine'],
        // Margaret variants
        ['Margret', 'Margareth', 'Margarethe', 'Margareta', 'Małgorzata', 'Malgorzata', 'Markéta', 'Marketa', 'Margaret', 'Maggie', 'Greta', 'Gretchen', 'Margot', 'Margherita', 'Margarita', 'Margrethe', 'Margit', 'Mette', 'Berit', 'Marit', 'Marguerite', 'Bernice', 'Bertha'],
        // Mark variants
        ['Mark', 'Marcus', 'Marek', 'Marco', 'Marcos'],
        // Martha variants
        ['Martha', 'Marthe', 'Marta'],
        // Martin variants
        ['Martin', 'Marcin', 'Martinus', 'Martinius', 'Martino', 'Merten'],
        // Mary variants
        ['Maria', 'Mary', 'Marie', 'Maryja', 'Mariam', 'Marija', 'Mariya', 'Mitzi', 'Ria', 'Mari', 'Marianna', 'Marianne', 'Maria Anna'],
        // Matthias variants
        ['Maciej', 'Matthias', 'Mathias', 'Matyas', 'Maciek', 'Matej', 'Mateusz', 'Matthew', 'Matthäus', 'Matteo', 'Mateo', 'Matěj', 'Mats'],
        // Maurice variants
        ['Moritz', 'Maurice', 'Mauritius', 'Maurizio', 'Mauricio'],
        // Maximilian variants
        ['Maximilian', 'Max', 'Massimiliano'],
        // Michael variants
        ['Michael', 'Michał', 'Michel', 'Mihail', 'Mikael', 'Michaelis', 'Mikhail', 'Michele', 'Miguel', 'Mikkel'],
        // Nicholas variants
        ['Nicholas', 'Niklaus', 'Nicolaus', 'Mikołaj', 'Nick', 'Nico', 'Niccolò', 'Nicolò', 'Nicolás', 'Nicolas', 'Niels', 'Nils'],
        // Olaf variants
        ['Olaf', 'Olav', 'Olof', 'Oluf'],
        // Oscar variants
        ['Oscar', 'Oskar', 'Oscarre'],
        // Ottilie variants
        ['Ottilie', 'Otylja', 'Ottilia', 'Otylia'],
        // Otto variants
        ['Otto', 'Oton', 'Oddone'],
        // Patrick variants
        ['Patrick', 'Patriz', 'Patrizio', 'Patricio'],
        // Paul variants
        ['Paul', 'Paulus', 'Paweł', 'Pawel', 'Pavel', 'Paolo', 'Pablo'],
        // Peter variants
        ['Peter', 'Piotr', 'Petrus', 'Piers', 'Pyotr', 'Pietro', 'Pedro', 'Pierre', 'Per', 'Peder', 'Petter'],
        // Philip variants
        ['Philip', 'Philipp', 'Filip', 'Philippus', 'Filippo', 'Felipe'],
        // Raymond variants
        ['Raymond', 'Raimund', 'Raimondo', 'Ramón'],
        // Richard variants
        ['Richard', 'Riccardo', 'Ricardo'],
        // Robert variants
        ['Robert', 'Roberto', 'Rupert', 'Ruprecht'],
        // Roger variants
        ['Roger', 'Rüdiger', 'Ruggero', 'Rogelio'],
        // Roland variants
        ['Roland', 'Orlando'],
        // Rosemary variants
        ['Rosemary', 'Rosemary', 'Rose-Mary', 'Rose Mary'],
        // Rudolph variants
        ['Rudolf', 'Rudolph', 'Rodolfo'],
        // Rosina variants
        ['Rosina', 'Rosine', 'Rozyna', 'Rozina', 'Rosa', 'Rose', 'Rosalie', 'Rosalia', 'Róża', 'Roza'],
        // Samuel variants
        ['Samuel', 'Samuele'],
        // Sebastian variants
        ['Sebastian', 'Sebastiano'],
        // Sigismund variants
        ['Siegmund', 'Zygmunt', 'Sigismund', 'Sigismundus', 'Zmago'],
        // Simon variants
        ['Simon', 'Szymon', 'Simeon', 'Simone'],
        // Salome/Sally variants
        ['Salome', 'Sally', 'Salomea', 'Sarah', 'Sara', 'Sally Mary', 'Salli', 'Sallie'],
        // Sophia variants
        ['Sofia', 'Zofia', 'Sophie', 'Sophia', 'Žofie', 'Žofia', 'Sofiya', 'Siri'],
        // Stephen variants
        ['Stephan', 'Stefan', 'Stephanus', 'Szczepan', 'Stephen', 'Štěpán', 'Stepan', 'Stefano', 'Esteban'],
        // Thaddeus variants
        ['Thaddeus', 'Thaddäus', 'Tadeusz', 'Taddeo'],
        // Theodore variants
        ['Theodor', 'Theodore', 'Teodoro'],
        // Theresa variants
        ['Theresa', 'Therese', 'Teresa', 'Thérèse'],
        // Thomas variants
        ['Thomas', 'Tomasz', 'Tom', 'Tomáš', 'Tomas', 'Tommaso', 'Tomás'],
        // Thor variants
        ['Thor', 'Tor', 'Tore'],
        // Timothy variants
        ['Timothy', 'Timotheus', 'Timoteo'],
        // Urban variants
        ['Urban', 'Urbanus', 'Urbano'],
        // Ursula variants
        ['Ursula', 'Orsola'],
        // Valentine variants
        ['Valentine', 'Valentin', 'Walenty', 'Valentinus', 'Valentino'],
        // Victor variants
        ['Victor', 'Viktor', 'Wiktor', 'Vittorio'],
        // Vincent variants
        ['Vincent', 'Wincenty', 'Vincentius', 'Vincenc', 'Vincenzo', 'Vicente'],
        // Walter variants
        ['Walter', 'Walther', 'Gualtiero'],
        // Wenzel variants
        ['Vaclav', 'Wacław', 'Waclaw', 'Wenzel', 'Wenceslaus', 'Václav'],
        // William variants
        ['Wilhelm', 'William', 'Willem', 'Gulielmus', 'Guillaume', 'Guglielmo', 'Guillermo'],
        // Xavier variants
        ['Xaver', 'Xavier', 'Saverio', 'Javier'],
        // Combined names
        ['Maria Anna', 'Marianna', 'Marianne'],
        ['Anne Marie', 'Annemarie'],
        ['Rose Mary', 'Rosemarie'],
    ];

    /**
     * Map of names to their typical gender.
     */
    private static array $knownGenders = [
        // Male
        'Abraham' => 'M', 'Adam' => 'M', 'Adrian' => 'M', 'Alexander' => 'M', 'Alois' => 'M',
        'Andreas' => 'M', 'Anton' => 'M', 'Arnold' => 'M', 'August' => 'M', 'Bartholomäus' => 'M',
        'Benedikt' => 'M', 'Bernhard' => 'M', 'Bruno' => 'M', 'Caspar' => 'M', 'Christian' => 'M',
        'Christoph' => 'M', 'Clemens' => 'M', 'David' => 'M', 'Dennis' => 'M', 'Dominik' => 'M',
        'Eduard' => 'M', 'Emil' => 'M', 'Emanuel' => 'M', 'Erich' => 'M', 'Ernst' => 'M',
        'Eugen' => 'M', 'Fabian' => 'M', 'Ferdinand' => 'M', 'Franz' => 'M', 'Friedrich' => 'M',
        'Gabriel' => 'M', 'Georg' => 'M', 'Gerhard' => 'M', 'Gottfried' => 'M', 'Gottlieb' => 'M',
        'Gregor' => 'M', 'Hans' => 'M', 'Heinrich' => 'M', 'Herbert' => 'M', 'Hermann' => 'M',
        'Hubert' => 'M', 'Hugo' => 'M', 'Ignaz' => 'M', 'Isaac' => 'M', 'Jakob' => 'M',
        'Joachim' => 'M', 'Johann' => 'M', 'Johannes' => 'M', 'Josef' => 'M', 'Julian' => 'M',
        'Julius' => 'M', 'Karl' => 'M', 'Konrad' => 'M', 'Ladislaus' => 'M', 'Lorenz' => 'M',
        'Leo' => 'M', 'Leopold' => 'M', 'Ludwig' => 'M', 'Lukas' => 'M', 'Markus' => 'M',
        'Martin' => 'M', 'Matthias' => 'M', 'Michael' => 'M', 'Moritz' => 'M', 'Maximilian' => 'M',
        'Nikolaus' => 'M', 'Olaf' => 'M', 'Oskar' => 'M', 'Otto' => 'M', 'Patrick' => 'M',
        'Paul' => 'M', 'Peter' => 'M', 'Philipp' => 'M', 'Raimund' => 'M', 'Richard' => 'M',
        'Robert' => 'M', 'Roger' => 'M', 'Roland' => 'M', 'Rudolf' => 'M', 'Samuel' => 'M',
        'Sebastian' => 'M', 'Siegmund' => 'M', 'Simon' => 'M', 'Stefan' => 'M', 'Stephan' => 'M',
        'Thaddäus' => 'M', 'Theodor' => 'M', 'Thomas' => 'M', 'Thor' => 'M', 'Timothy' => 'M',
        'Urban' => 'M', 'Valentin' => 'M', 'Viktor' => 'M', 'Vinzenz' => 'M', 'Walter' => 'M',
        'Wenzel' => 'M', 'Wilhelm' => 'M', 'Xaver' => 'M',
        
        'Agathe' => 'F', 'Agnes' => 'F', 'Alice' => 'F', 'Amalie' => 'F', 'Angela' => 'F',
        'Anna' => 'F', 'Anne' => 'F', 'Apolonia' => 'F', 'Barbara' => 'F', 'Beate' => 'F', 'Birgitta' => 'F',
        'Brigitte' => 'F', 'Charlotte' => 'F', 'Christina' => 'F', 'Christine' => 'F',
        'Clara' => 'F', 'Dolores' => 'F', 'Dorothea' => 'F', 'Elisabeth' => 'F', 'Genevieve' => 'F',
        'Gertrud' => 'F', 'Gisela' => 'F', 'Giesela' => 'F', 'Hedwig' => 'F', 'Helena' => 'F', 'Hanna' => 'F', 'Hannah' => 'F',
        'Judith' => 'F', 'Justine' => 'F', 'Katharina' => 'F', 'Karolina' => 'F', 'Karoline' => 'F',
        'Louise' => 'F', 'Lucia' => 'F', 'Magdalena' => 'F', 'Margarethe' => 'F', 'Margaretha' => 'F',
        'Martha' => 'F', 'Maria' => 'F', 'Marianna' => 'F', 'Ottilie' => 'F', 'Regina' => 'F',
        'Rosemary' => 'F', 'Rosina' => 'F', 'Salome' => 'F', 'Sophia' => 'F', 'Sophie' => 'F', 
        'Theresa' => 'F', 'Therese' => 'F', 'Ursula' => 'F', 'Zofia' => 'F'
    ];

    private static ?array $normalizedGenderMap = null;
    private static ?array $normalizedMap = null;

    /**
     * Check if two multi-word given names are considered equivalent.
     * Logic: A match is found if all words of the shorter name have an equivalent in the longer name.
     * This handles cases like "Johann Friedrich" vs "Johann" or "Jan Fryderyk" vs "Johann Friedrich".
     */
    public static function areNamesEquivalent(string $name1, string $name2): bool
    {
        if (empty(trim($name1)) || empty(trim($name2))) {
            return false;
        }

        $words1 = preg_split('/[\s,-]+/', $name1, -1, PREG_SPLIT_NO_EMPTY);
        $words2 = preg_split('/[\s,-]+/', $name2, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words1) || empty($words2)) {
            return false;
        }

        // Check matching words in both directions
        $matchesIn1 = 0;
        foreach ($words1 as $w1) {
            foreach ($words2 as $w2) {
                if (self::areSingleNamesEquivalent($w1, $w2)) {
                    $matchesIn1++;
                    break;
                }
            }
        }

        $matchesIn2 = 0;
        foreach ($words2 as $w2) {
            foreach ($words1 as $w1) {
                if (self::areSingleNamesEquivalent($w1, $w2)) {
                    $matchesIn2++;
                    break;
                }
            }
        }

        // Rule: One side must be a complete subset (equivalence-wise) of the other
        return ($matchesIn1 === count($words1)) || ($matchesIn2 === count($words2));
    }

    /**
     * Check if two single words are equivalent.
     */
    private static function areSingleNamesEquivalent(string $n1, string $n2): bool
    {
        $n1 = self::normalize($n1);
        $n2 = self::normalize($n2);

        if ($n1 === $n2) {
            return true;
        }

        $map = self::getNormalizedMap();

        if (isset($map[$n1]) && isset($map[$n2])) {
            $intersect = array_intersect($map[$n1], $map[$n2]);
            return !empty($intersect);
        }

        return false;
    }

    private static function getNormalizedMap(): array
    {
        if (self::$normalizedMap === null) {
            self::$normalizedMap = [];
            foreach (self::$equivalents as $index => $group) {
                foreach ($group as $name) {
                    $norm = self::normalize($name);
                    if (!isset(self::$normalizedMap[$norm])) {
                        self::$normalizedMap[$norm] = [];
                    }
                    if (!in_array($index, self::$normalizedMap[$norm])) {
                        self::$normalizedMap[$norm][] = $index;
                    }
                }
            }
        }
        return self::$normalizedMap;
    }

    public static function getGenderByNames(string $givens, string $surnames = ''): ?string
    {
        $givenWords = preg_split('/[\s,\-\|\\\\\/]+/', $givens, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($givenWords) && empty($surnames)) return null;

        $map = self::getNormalizedGenderMap();
        
        // 1. Check given names against database
        foreach ($givenWords as $word) {
            $norm = self::normalize($word);
            if (isset($map[$norm])) {
                return $map[$norm];
            }
        }

        // 2. Heuristics for Surnames (International Patterns)
        if (!empty($surnames)) {
            $surnameWords = preg_split('/[\s,\-\|\\\\\/]+/', $surnames, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($surnameWords as $word) {
                $clean = mb_strtolower(trim($word), 'UTF-8');
                
                // Polish: -ska (F), -ski (M)
                if (str_ends_with($clean, 'ska')) return 'F';
                if (str_ends_with($clean, 'ski')) return 'M';

                // Russian/Bulgarian: -eva, -ova, -ina (F) vs -ev, -ov, -in (M)
                if (preg_match('/(eva|ova|ina|aya)$/u', $clean)) return 'F';
                if (preg_match('/(ev|ov|in|iy)$/u', $clean)) return 'M';

                // Scandinavian: -datter, -dotter (F) vs -sen, -son (M)
                if (str_ends_with($clean, 'datter') || str_ends_with($clean, 'dotter')) return 'F';
                if (str_ends_with($clean, 'sen') || str_ends_with($clean, 'son') || str_ends_with($clean, 'sson')) return 'M';

                // Lithuanian: -ienė, -ytė, -atė (F) vs -as, -is, -us (M)
                if (preg_match('/(iene|yte|ate|ute)$/u', $clean)) return 'F';
            }
        }

        // 3. Heuristic for Given Names (Fallback)
        foreach ($givenWords as $word) {
            $clean = mb_strtolower(trim($word), 'UTF-8');
            if (mb_strlen($clean) < 3) continue;
            
            $lastChar = mb_substr($clean, -1);
            // In many languages (German, Latin, Slavic), 'a' is a strong indicator for female
            if ($lastChar === 'a') {
                return 'F';
            }
            // 'e' is common for female in German/French, but also male in some languages.
            // We keep it as a weak indicator if no other info is found.
            if ($lastChar === 'e') {
                return 'F';
            }
        }

        return null;
    }

    private static function getNormalizedGenderMap(): array
    {
        if (self::$normalizedGenderMap === null) {
            self::$normalizedGenderMap = [];
            
            // 1. Load basic genders
            foreach (self::$knownGenders as $name => $gender) {
                self::$normalizedGenderMap[self::normalize($name)] = $gender;
            }

            // 2. Expand via equivalents
            $map = self::getNormalizedMap();
            foreach ($map as $normName => $groupIds) {
                if (isset(self::$normalizedGenderMap[$normName])) continue;
                
                foreach ($groupIds as $groupId) {
                    $group = self::$equivalents[$groupId];
                    foreach ($group as $alias) {
                        $normAlias = self::normalize($alias);
                        if (isset(self::$normalizedGenderMap[$normAlias])) {
                            self::$normalizedGenderMap[$normName] = self::$normalizedGenderMap[$normAlias];
                            break 2;
                        }
                    }
                }
            }
        }
        return self::$normalizedGenderMap;
    }

    private static function normalize(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name, 'UTF-8');
        
        $search  = ['á', 'ä', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ö', 'ř', 'š', 'ť', 'ú', 'ů', 'ü', 'ý', 'ž', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'ą', 'ę', 'å', 'ø'];
        $replace = ['a', 'a', 'c', 'd', 'e', 'e', 'i', 'n', 'o', 'o', 'r', 's', 't', 'u', 'u', 'u', 'y', 'z', 'l', 'n', 'o', 's', 'z', 'z', 'a', 'e', 'a', 'o'];
        
        return str_replace($search, $replace, $name);
    }
}
