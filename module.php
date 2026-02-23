<?php

namespace Wolfrum\Datencheck;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleFooterInterface;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\DB;
use Wolfrum\Datencheck\Services\InteractionService;
use Wolfrum\Datencheck\Services\TaskService;
use Wolfrum\Datencheck\Services\ValidationService;
use Wolfrum\Datencheck\Services\SchemaService;
use Wolfrum\Datencheck\Services\IgnoredErrorService;

use function response;
use function route;
use function view;

// PSR-4 Autoloader for module internal classes
spl_autoload_register(static function (string $class): void {
    $prefix   = 'Wolfrum\\Datencheck\\';
    $base_dir = __DIR__ . '/src/';
    $len      = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

class DatencheckModule extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleFooterInterface, ModuleConfigInterface
{
    use ModuleConfigTrait;

    /**
     * Get a setting, preferring user-specific settings if available.
     * Falls back to global module preferences.
     */
    public function getSetting(string $key, $default = null): string
    {
        $user = Auth::user();
        if ($user) {
            $user_val = $user->getPreference('DC_' . $key);
            if ($user_val !== null && $user_val !== '') {
                return (string) $user_val;
            }
        }
        
        return (string) $this->getPreference($key, (string)$default);
    }

    /**
     * Set a setting, preferring user-specific settings if available.
     */
    public function setSetting(string $key, string $value): void
    {
        $user = Auth::user();
        if ($user) {
            $user->setPreference('DC_' . $key, $value);
        } else {
            $this->setPreference($key, $value);
        }
    }

    private int $menu_order = 99;
    private int $footer_order = 0;

    public function title(): string
    {
        return \Fisharebest\Webtrees\I18N::translate('Data Check');
    }

    public function boot(): void
    {
        \Fisharebest\Webtrees\View::registerNamespace($this->name(), __DIR__ . '/resources/views/');
    }

    public function description(): string
    {
        return 'Checks for data inconsistencies.';
    }

    public function customModuleAuthorName(): string
    {
        return 'Christian Wolfrum';
    }

    public function customModuleVersion(): string
    {
        return '1.3.16';
    }

    public function getVersion(): string
    {
        return $this->customModuleVersion();
    }

    public function customModuleLatestVersionUrl(): string
    {
        // URL where the user can download the update
        return 'https://github.com/Vulfharban/webtrees-datencheck-plugin/releases';
    }

    public function customModuleLatestVersion(): string
    {
        // URL to the raw file containing the version number
        $url = 'https://raw.githubusercontent.com/Vulfharban/webtrees-datencheck-plugin/main/latest-version.txt';
        $cacheFile = sys_get_temp_dir() . '/datencheck_version_cache.txt';

        // Cache for 1 hour
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached) {
                return trim($cached);
            }
        }

        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $latest = @file_get_contents($url, false, $ctx);
            
            if ($latest) {
                file_put_contents($cacheFile, $latest);
                return trim($latest);
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return $this->customModuleVersion();
    }

    public function customModuleImage(): string
    {
        $file = __DIR__ . '/resources/images/datencheck_icon.png';
        if (file_exists($file)) {
            $data   = file_get_contents($file);
            $base64 = base64_encode($data);
            return 'data:image/png;base64,' . $base64;
        }

        return '';
    }

    public function customTranslations(string $language): array
    {
        // Try full locale first (e.g. de-DE)
        $file = __DIR__ . '/resources/lang/' . $language . '.php';
        if (file_exists($file)) {
            return (array) include $file;
        }

        // Try language only (e.g. de)
        $lang = explode('-', $language)[0];
        $file = __DIR__ . '/resources/lang/' . $lang . '.php';
        if (file_exists($file)) {
            return (array) include $file;
        }

        return (array) include __DIR__ . '/resources/lang/en.php';
    }

    public function setMenuOrder(int $order): void
    {
        $this->menu_order = $order;
    }

    public function getMenuOrder(): int
    {
        return $this->menu_order;
    }

    public function defaultMenuOrder(): int
    {
        return 99;
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/Vulfharban/webtrees-datencheck-plugin/issues';
    }

    public function setFooterOrder(int $order): void
    {
        $this->footer_order = $order;
    }

    public function getFooterOrder(): int
    {
        return $this->footer_order;
    }

    public function defaultFooterOrder(): int
    {
        return 0;
    }

    public function getFooter(ServerRequestInterface $request): string
    {
        try {
            $tree = $this->getTree($request);
        } catch (\Throwable $e) {
            return '';
        }

        if ($tree === null || !Auth::isEditor($tree)) {
            return '';
        }

        $check_url = route('module', [
            'module' => $this->name(),
            'action' => 'CheckPerson',
            'tree'   => $tree->name(),
        ]);

        $family_url = route('module', [
            'module' => $this->name(),
            'action' => 'CheckFamily',
            'tree'   => $tree->name(),
        ]);

        $sibling_url = route('module', [
            'module' => $this->name(),
            'action' => 'CheckSibling',
            'tree'   => $tree->name(),
        ]);

        $details_url = route('module', [
            'module' => $this->name(),
            'action' => 'PersonDetails',
            'tree'   => $tree->name(),
        ]);

        $fam_details_url = route('module', [
            'module' => $this->name(),
            'action' => 'FamilyDetails',
            'tree'   => $tree->name(),
        ]);

        $validation_url = route('module', [
            'module' => $this->name(),
            'action' => 'Validation',
            'tree'   => $tree->name(),
        ]);

        $add_task_url = route('module', [
            'module' => $this->name(),
            'action' => 'AddTask',
            'tree'   => $tree->name(),
        ]);

        $ignore_error_url = route('module', [
            'module' => $this->name(),
            'action' => 'IgnoreError',
            'tree'   => $tree->name(),
        ]);

        $individual_url = route(IndividualPage::class, [
            'tree' => $tree->name(),
            'xref' => 'I1'
        ]);

        return view($this->name() . '::modules/datencheck/interaction', [
            'check_url'      => $check_url,
            'sibling_url'    => $sibling_url,
            'family_url'     => $family_url,
            'details_url'    => $details_url,
            'family_details_url' => $fam_details_url,
            'validation_url' => $validation_url,
            'add_task_url'   => $add_task_url,
            'ignore_error_url' => $ignore_error_url,
            'individual_url' => $individual_url,
        ]);
    }

    public function getMenu(Tree $tree): ?Menu
    {
        if (!Auth::isModerator($tree)) {
            return null;
        }

        $id   = 'menu-datencheck';
        $file = __DIR__ . '/resources/images/datencheck_icon.png';
        $icon = '<i class="menu-icon fas fa-check-double"></i>'; // Fallback
        
        // Only show icon if enabled in settings (default: enabled)
        $show_icon = $this->getSetting('enable_menu_icon', '1') === '1';

        if ($show_icon && file_exists($file)) {
            $data   = file_get_contents($file);
            $base64 = base64_encode($data);
            // Use 58px size (perfectly between 50 and 64)
            $icon   = '<img src="data:image/png;base64,' . $base64 . '" class="wt-icon-menu" style="width:58px; height:58px; object-fit:contain; display:block; margin:0 auto 0;">';
            $label = $icon . '<span>' . $this->title() . '</span>';
        } else {
            $label = $this->title();
        }

        // Create main menu item (Dropdown)
        // Add bootstrap classes to match native menu behavior
        $menu = new Menu($label, '#', $id, [
            'class' => 'dropdown-toggle menu-datencheck', 
            'data-bs-toggle' => 'dropdown'
        ]);

        // 1. Analyse / Dashboard
        $url_dashboard = route('module', [
            'module' => $this->name(),
            'action' => 'Admin',
            'tree'   => $tree->name(),
        ]);
        
        $menu->addSubmenu(new Menu(
            '<i class="fas fa-stethoscope fa-fw" style="margin-right:8px; vertical-align:middle;"></i> <span style="vertical-align:middle; line-height:24px;">' . \Fisharebest\Webtrees\I18N::translate('Overview & Analysis') . '</span>', 
            $url_dashboard, 
            'menu-datencheck-dashboard',
            ['class' => 'dropdown-item']
        ));

        // 2. Ignorierte Fehler (für Moderatoren)
        if (Auth::isModerator($tree)) {
            $url_ignored = route('module', [
                'module' => $this->name(),
                'action' => 'AdminIgnored',
                'tree'   => $tree->name(),
            ]);
            
            $menu->addSubmenu(new Menu(
                '<i class="fas fa-eye-slash fa-fw" style="margin-right:8px; vertical-align:middle;"></i> <span style="vertical-align:middle; line-height:24px;">' . \Fisharebest\Webtrees\I18N::translate('Ignored Entries') . '</span>', 
                $url_ignored, 
                'menu-datencheck-ignored',
                ['class' => 'dropdown-item']
            ));
        }
        
        return $menu;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws HttpNotFoundException
     */
    public function getAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $action = Validator::attributes($request)->string('action');

            switch ($action) {
                case 'Validation':
                    return $this->getValidationAction($request);
                case 'CheckPerson':
                    return $this->getCheckPersonAction($request);
                case 'CheckFamily':
                    return $this->getCheckFamilyAction($request);
                case 'CheckSibling':
                    return $this->getCheckSiblingAction($request);
                case 'AddTask':
                return $this->getAddTaskAction($request);
            case 'IgnoreError':
                return $this->getIgnoreErrorAction($request);
            case 'AdminIgnored':
                return $this->getAdminIgnoredAction($request);
            case 'PersonDetails':
                return $this->getPersonDetailsAction($request);
            case 'FamilyDetails':
                return $this->getFamilyDetailsAction($request);
            case 'Admin':
                case 'Config':
                    return $this->getAdminAction($request);
                case 'BatchAnalysis':
                    return $this->getBatchAnalysisAction($request);
                default:
                    return response(json_encode(['error' => 'Unknown action: ' . $action]))
                        ->withHeader('Content-Type', 'application/json');
            }
        } catch (\Throwable $e) {
            return response(json_encode(['error' => $e->getMessage()]))
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Robustly retrieve the tree from the request attributes or query parameters.
     * 
     * @param ServerRequestInterface $request
     * @return Tree|null
     */
    private function getTree(ServerRequestInterface $request): ?Tree
    {
        // 1. Try route attribute (resolved by webtrees)
        $tree = $request->getAttribute('tree');
        if ($tree instanceof Tree) {
            return $tree;
        }

        // 2. Try query parameters (fallback)
        $tree_name = $request->getQueryParams()['tree'] ?? '';
        if ($tree_name) {
            return \Fisharebest\Webtrees\Registry::container()->get(\Fisharebest\Webtrees\Services\TreeService::class)->all()->get($tree_name);
        }

        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postAction(ServerRequestInterface $request): ResponseInterface
    {
        $action = Validator::attributes($request)->string('action');

        switch ($action) {
            case 'Admin':
                return $this->postAdminAction($request);
            case 'AdminIgnored':
                return $this->getAdminIgnoredAction($request);
            default:
                throw new \RuntimeException('Route not found');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getValidationAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = $this->getTree($request);
            $params = $request->getQueryParams();
            
            if ($tree === null || !Auth::isEditor($tree)) {
                 throw new HttpAccessDeniedException();
            }

            $xref = $params['xref'] ?? '';
            $person = null;

            if (!empty($xref)) {
                $p = Registry::individualFactory()->make($xref, $tree);
                if ($p && DB::table('individuals')->where('i_id', $xref)->where('i_file', $tree->id())->exists()) {
                    $person = $p;
                }
            }

            $birth = $params['birth_date'] ?? '';
            $death = $params['death_date'] ?? '';
            $burial = $params['burial_date'] ?? '';
            $husb = $params['husb'] ?? '';
            $wife = $params['wife'] ?? '';
            $fam = $params['fam'] ?? '';
            $marrFormatted = $params['marr_date'] ?? '';
            $relType = $params['rel_type'] ?? 'child';
            $given = $params['given_name'] ?? '';
            $surname = $params['surname'] ?? '';
            $bap = $params['baptism_date'] ?? '';
            $sex = strtoupper(trim($params['sex'] ?? ''));

            $result = ValidationService::validatePerson($person, $this, $birth, $death, $burial, $husb, $wife, $fam, $tree, $marrFormatted, $relType, $given, $surname, $bap, [], $sex);

            return response(json_encode($result))
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return response(json_encode(['error' => $e->getMessage()]))
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getCheckPersonAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = $this->getTree($request);
            if ($tree === null || !Auth::isEditor($tree)) {
                throw new HttpAccessDeniedException();
            }
            $params = $request->getQueryParams();

            $given = $params['given_name'] ?? '';
            $surname = $params['surname'] ?? '';
            $birth = $params['birth_date'] ?? '';
            $death = $params['death_date'] ?? '';
            $baptism = $params['baptism_date'] ?? '';
            $sex = $params['sex'] ?? '';

            $marriedSurname = $params['married_surname'] ?? '';

            $fuzzyDiffHighAge = (int)$this->getSetting('fuzzy_diff_high_age', '6');
            $fuzzyDiffDefault = (int)$this->getSetting('fuzzy_diff_default', '2');

            $data = InteractionService::runInteractiveCheck(
                $tree, $given, $surname, $birth,
                $fuzzyDiffHighAge, $fuzzyDiffDefault,
                $death, $baptism, $sex, $marriedSurname
            );

            return response(json_encode($data))
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return response(json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]))
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getCheckFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        if ($tree === null || !Auth::isEditor($tree)) {
            throw new HttpAccessDeniedException();
        }
        $params = $request->getQueryParams();

        $husb = $params['husb'] ?? '';
        $wife = $params['wife'] ?? '';

        $data = InteractionService::runFamilyCheck($tree, $husb, $wife);

        return response(json_encode($data))
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        if (!($tree && Auth::isModerator($tree)) && !Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }
        
        // Load view from module directory
        $view_file = __DIR__ . '/resources/views/modules/datencheck/admin.phtml';
        
        if (!file_exists($view_file)) {
            return response('<div class="alert alert-danger">View template not found: ' . $view_file . '</div>');
        }
        
        // Prepare variables for the view
        $title = $this->title();
        $fuzzy_diff_high_age = $this->getSetting('fuzzy_diff_high_age', '6');
        $fuzzy_diff_default = $this->getSetting('fuzzy_diff_default', '2');
        $enable_scand_patronym = $this->getSetting('enable_scand_patronym', '0');
        $module = $this;
        
        // Generate CSRF token for the form
        $csrf = csrf_field();
        
        // Generate route URLs (cannot be generated in view with require)
        // Detect tree from request to pass to view
        $tree = $this->getTree($request);
        
        // Fallback to Registry (session-based active tree)
        if (!$tree) {
            try {
                $tree = Registry::container()->get(Tree::class);
            } catch (\Throwable $e) {
                $tree = null;
            }
        }

        // Verify access - if not a moderator for this tree, we can't show it as default
        if ($tree && !Auth::isModerator($tree)) {
            $tree = null;
        }

        // Generate route URLs
        $control_panel_url = $tree 
            ? route(\Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel::class, ['tree' => $tree->name()])
            : route(\Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel::class);
            
        $modules_all_url = route(\Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllPage::class);
        
        // Tree-specific breadcrumbs if we have a context
        $breadcrumb_links = [];
        if ($tree) {
            $breadcrumb_links[route(IndividualPage::class, ['tree' => $tree->name()])] = $tree->title();
            $breadcrumb_links[$control_panel_url] = \Fisharebest\Webtrees\I18N::translate('Control panel');
        } else {
            $breadcrumb_links[$control_panel_url] = \Fisharebest\Webtrees\I18N::translate('Control panel');
        }
        $breadcrumb_links[$modules_all_url] = \Fisharebest\Webtrees\I18N::translate('Modules');
        $breadcrumb_links[''] = $title;

        // Simple variables for backward compatibility in view
        $i18n_control_panel = \Fisharebest\Webtrees\I18N::translate('Control panel');
        $i18n_modules = \Fisharebest\Webtrees\I18N::translate('Modules');
        $i18n_save = \Fisharebest\Webtrees\I18N::translate('save');
        $i18n_cancel = \Fisharebest\Webtrees\I18N::translate('cancel');

        // Prepare tree list for dropdown (DB-Based fallback to be safe)
        $trees_list = [];
        try {
            // Using raw DB query to avoid Registry/Factory issues and get proper titles
            $rows = DB::table('gedcom')
                ->join('gedcom_setting', 'gedcom.gedcom_id', '=', 'gedcom_setting.gedcom_id')
                ->where('gedcom_setting.setting_name', '=', 'TITLE')
                ->select('gedcom.gedcom_name', 'gedcom_setting.setting_value as title')
                ->orderBy('gedcom_setting.setting_value')
                ->get();
            
            foreach ($rows as $row) {
                // Key is the internal name (for URL), Value is the display title
                if (!empty($row->gedcom_name)) {
                    $trees_list[$row->gedcom_name] = $row->title;
                }
            }
        } catch (\Throwable $e) {
            // If DB fails, empty list
        }

        // Fallback to first tree if none selected (for analysis default)
        if (!$tree && !empty($trees_list)) {
            $first_name = array_key_first($trees_list);
            $tree_name = $first_name;
            $tree = \Fisharebest\Webtrees\Registry::container()->get(\Fisharebest\Webtrees\Services\TreeService::class)->all()->get($tree_name);
        } else {
            $tree_name = $tree ? $tree->name() : '';
        }
        
        $tree_title = $tree ? $tree->title() : $tree_name;
        $tree_url = $tree ? route(\Fisharebest\Webtrees\Http\RequestHandlers\TreePage::class, ['tree' => $tree->name()]) : '#';

        // Render view content
        ob_start();
        require $view_file;
        $content = ob_get_clean();
        
        // Wrap in webtrees layout using standard response/view helper
        return response(view('layouts/administration', [
            'title' => $title . ' – ' . \Fisharebest\Webtrees\I18N::translate('Settings'),
            'content' => $content,
        ]));
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getParsedBody();

        $this->setSetting('fuzzy_diff_high_age', $params['fuzzy_diff_high_age'] ?? '6');
        $this->setSetting('fuzzy_diff_default', $params['fuzzy_diff_default'] ?? '2');
        
        // Save validation settings
        $this->setSetting('max_marriages_warning', $params['max_marriages_warning'] ?? '5');
        $this->setSetting('enable_missing_data_checks', isset($params['enable_missing_data_checks']) ? '1' : '0');
        $this->setSetting('enable_geographic_checks', isset($params['enable_geographic_checks']) ? '1' : '0');
        $this->setSetting('enable_name_checks', isset($params['enable_name_consistency_checks']) ? '1' : '0');
        $this->setSetting('enable_scand_patronym', isset($params['enable_scandinavian_patronymics']) ? '1' : '0');
        $this->setSetting('enable_slavic_surnames', isset($params['enable_slavic_surnames']) ? '1' : '0');
        $this->setSetting('enable_spanish_surnames', isset($params['enable_spanish_surnames']) ? '1' : '0');
        $this->setSetting('enable_dutch_tussenvoegsels', isset($params['enable_dutch_tussenvoegsels']) ? '1' : '0');
        $this->setSetting('enable_greek_surnames', isset($params['enable_greek_surnames']) ? '1' : '0');
        $this->setSetting('enable_genannt_names', isset($params['enable_genannt_names']) ? '1' : '0');
        $this->setSetting('enable_source_checks', isset($params['enable_source_checks']) ? '1' : '0');
        $this->setSetting('enable_imprecise_dates', isset($params['enable_imprecise_dates']) ? '1' : '0');
        $this->setSetting('enable_menu_icon', isset($params['enable_menu_icon']) ? '1' : '0');
        
        // Save threshold preferences
        $this->setSetting('min_mother_age', $params['min_mother_age'] ?? '14');
        $this->setSetting('max_mother_age', $params['max_mother_age'] ?? '50');
        $this->setSetting('min_father_age', $params['min_father_age'] ?? '14');
        $this->setSetting('max_father_age', $params['max_father_age'] ?? '80');
        $this->setSetting('max_lifespan', $params['max_lifespan'] ?? '120');
        $this->setSetting('max_marriage_age_warning', $params['max_marriage_age_warning'] ?? '100');
        $this->setSetting('min_marriage_age_warning', $params['min_marriage_age_warning'] ?? '15');
        $this->setSetting('min_sibling_spacing_warning', $params['min_sibling_spacing_warning'] ?? '9');
        
        \Fisharebest\Webtrees\FlashMessages::addMessage(\Fisharebest\Webtrees\I18N::translate('Settings saved successfully.'), 'success');
        
        $tree_name = $params['tree_context'] ?? '';
        
        if ($tree_name) {
            $url = route('module', [
                'module' => $this->name(),
                'action' => 'Admin',
                'tree'   => $tree_name
            ]);
        } else {
            $url = $this->getConfigLink();
        }
        
        return response('')
            ->withStatus(302)
            ->withHeader('Location', $url);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getCheckSiblingAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        if ($tree === null || !Auth::isEditor($tree)) {
            throw new HttpAccessDeniedException();
        }
        $params = $request->getQueryParams();

        $husb = $params['husb'] ?? '';
        $wife = $params['wife'] ?? '';
        $given = $params['child_given'] ?? '';
        $surname = $params['child_surname'] ?? '';
        $birth = $params['child_birth'] ?? '';

        $fuzzyDiffHighAge = (int)$this->getSetting('fuzzy_diff_high_age', '6');
        $fuzzyDiffDefault = (int)$this->getSetting('fuzzy_diff_default', '2');

        $data = InteractionService::runSiblingCheck($tree, $husb, $wife, $given, $surname, $birth, $fuzzyDiffHighAge, $fuzzyDiffDefault);

        return response(json_encode($data))
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getPersonDetailsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        if ($tree === null || !Auth::isEditor($tree)) {
            throw new HttpAccessDeniedException();
        }
        $params = $request->getQueryParams();

        $xref = $params['xref'] ?? '';

        if (empty($xref)) {
            return response(json_encode(['error' => \Fisharebest\Webtrees\I18N::translate('Missing xref parameter')]))
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $data = InteractionService::getPersonDetails($tree, $xref);

        return response(json_encode($data))
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getFamilyDetailsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        if ($tree === null || !Auth::isEditor($tree)) {
            throw new HttpAccessDeniedException();
        }
        $params = $request->getQueryParams();

        $famId = $params['fam'] ?? '';

        if (empty($famId)) {
            return response(json_encode(['error' => 'Missing fam parameter']))
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $data = InteractionService::getFamilyInfo($tree, $famId);

        return response(json_encode($data))
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getAddTaskAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = $this->getTree($request);
            if ($tree === null || !Auth::isEditor($tree)) {
                throw new HttpAccessDeniedException();
            }
            $params = $request->getQueryParams();

            $xref  = $params['xref'] ?? '';
            $title = $params['title'] ?? '';
            $note  = $params['note'] ?? '';

            if (empty($xref) || empty($title)) {
                return response(json_encode(['success' => false, 'message' => \Fisharebest\Webtrees\I18N::translate('Missing parameters')]))
                    ->withHeader('Content-Type', 'application/json');
            }

            $result = TaskService::addTask($tree, $xref, $title, $note);

            return response(json_encode($result))
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return response(json_encode(['success' => false, 'message' => $e->getMessage()]))
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getIgnoreErrorAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = $this->getTree($request);
            if ($tree === null || !Auth::isModerator($tree)) {
                throw new HttpAccessDeniedException();
            }
            $params = $request->getQueryParams();

            $xref  = $params['xref'] ?? '';
            $code  = $params['code'] ?? '';
            $msg   = $params['msg'] ?? ''; // Optional: ignore reason

            if (empty($xref) || empty($code)) {
                return response(json_encode(['success' => false, 'message' => 'Missing parameters']))
                    ->withHeader('Content-Type', 'application/json');
            }

            $success = IgnoredErrorService::ignoreError($tree, $xref, $code, $msg);

            return response(json_encode(['success' => $success]))
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return response(json_encode(['success' => false, 'message' => $e->getMessage()]))
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function postAdminIgnoredAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->getAdminIgnoredAction($request);
    }

    /**
     * Admin page for ignored errors
     */
    public function getAdminIgnoredAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $this->getTree($request);
        
        // Security check: Only editors can manage ignored errors
        if (!Auth::isModerator($tree)) {
            throw new HttpAccessDeniedException('Access denied');
        }

        $params = $request->getQueryParams();
        
        // Handle deletion (Un-Ignore)
        if ($request->getMethod() === 'POST') {
            $params = (array) $request->getParsedBody();
            $xref = $params['xref'] ?? '';
            $code = $params['code'] ?? '';
            
            if ($xref && $code) {
                IgnoredErrorService::unignoreError($tree, $xref, $code);
                // Flash message is tricky here without dedicated session helper, 
                // but page reload will show the item gone.
            }
            
            // Redirect to GET to avoid resubmission
            return redirect(route('module', [
                'module' => $this->name(),
                'action' => 'AdminIgnored',
                'tree'   => $tree->name(),
            ]));
        }

        $ignoredErrors = IgnoredErrorService::getIgnoredErrors($tree);
        
        // Enhance with person names
        $registry = \Fisharebest\Webtrees\Registry::individualFactory();
        foreach ($ignoredErrors as $error) {
            $indiv = $registry->make($error->xref, $tree);
            $error->person_name = $indiv ? $indiv->fullName() : 'Unknown (' . $error->xref . ')';
        }

        // Detect language robustly
        $lang = $request->getAttribute('locale') ?? $request->getAttribute('language');
        if (!$lang && ($user = Auth::user())) {
             $lang = $user->getPreference('language');
        }
        $lang = $lang ?? 'en';

        $content = view($this->name() . '::modules/datencheck/admin_ignored', [
            'title'         => \Fisharebest\Webtrees\I18N::translate('Ignored Errors'),
            'tree'          => $tree,
            'ignoredErrors' => $ignoredErrors,
            'module_name'   => $this->name(),
            'lang'          => $lang,
        ]);

        return response(view('layouts/default', [
            'request' => $request,
            'title'   => \Fisharebest\Webtrees\I18N::translate('Ignored Errors'),
            'tree'    => $tree,
            'content' => $content,
        ]));
    }

    /**
     * API Endpoint for Batch Analysis
     */
    public function getBatchAnalysisAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Increase execution time for larger batches
            set_time_limit(120);
            
            $tree = $this->getTree($request);
            if ($tree === null || !Auth::isModerator($tree)) {
                throw new HttpAccessDeniedException();
            }
            $params = $request->getQueryParams();

            if (!$tree) {
                return response(json_encode(['error' => 'Tree not found']))
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Batch-Limit Einstellung, akt. 100er Schritte
            // Pagination using last ID for better performance on large tables
            $lastId = $params['last_id'] ?? '';
            $limit  = (int) ($params['limit'] ?? 50); 
            
            $query = DB::table('individuals')
                ->where('i_file', '=', $tree->id())
                ->orderBy('i_id')
                ->take($limit);

            if (!empty($lastId)) {
                $query->where('i_id', '>', $lastId);
            }
                
            $rows = $query->get();
            $totalXrefs = $rows->pluck('i_id')->all();
            
            // Pre-warm caches for this batch
            ValidationService::preWarmCache($tree, $totalXrefs);
            
            $results = [];
            $registry = Registry::individualFactory();
            $debugRaw = null;
            
            $totalCount = (int) ($params['total'] ?? 0);
            if ($totalCount === 0) {
                 $totalCount = DB::table('individuals')->where('i_file', '=', $tree->id())->count();
            }

            $categories = !empty($params['categories']) ? explode(',', $params['categories']) : [];

            foreach ($totalXrefs as $xref) {
                $person = $registry->make($xref, $tree);
                if ($person) {
                    // Run Validation with optional category filters
                    $validationResult = ValidationService::validatePerson($person, $this, '', '', '', '', '', '', null, '', 'child', '', '', '', $categories);
                    
                    // Handle structured return ['issues' => [], 'debug' => []]
                    $issues = isset($validationResult['issues']) ? $validationResult['issues'] : $validationResult;
                    
                    if (!empty($issues)) {
                        if ($debugRaw === null) {
                            $debugRaw = reset($issues);
                        }
                        
                        foreach ($issues as $issue) {
                            $results[] = [
                                'xref' => $person->xref(),
                                'name' => $person->fullName(),
                                'type' => $issue['type'] ?? 'unknown',
                                'code' => $issue['code'] ?? 'UNKNOWN',
                                'label'=> $issue['label'] ?? '', // Provide fallback
                                'message' => $issue['message'] ?? 'No description available',
                                'severity' => $issue['severity'] ?? 'info'
                            ];
                        }
                    }
                }
            }

            $lastProcessedId = !empty($totalXrefs) ? end($totalXrefs) : $lastId;
            $processedCount = (int) ($params['processed'] ?? 0) + count($totalXrefs);

            return response(json_encode([
                'last_id' => $lastProcessedId,
                'offset' => $processedCount,
                'processed' => $processedCount,
                'finished' => count($totalXrefs) < $limit,
                'total' => $totalCount,
                'issues' => $results,
                'debug_raw' => $debugRaw
            ]))->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            return response(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]))->withHeader('Content-Type', 'application/json');
        }
    }
}

return new DatencheckModule();

