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
        require $file;
    }
});

class DatencheckModule extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleFooterInterface, ModuleConfigInterface
{
    use ModuleConfigTrait;

    private int $menu_order = 0;
    private int $footer_order = 0;

    public function title(): string
    {
        return 'Datencheck';
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
        return '0.9.1';
    }

    public function customModuleLatestVersionUrl(): string
    {
        // URL where the user can download the update
        return 'https://github.com/Vulfharban/webtrees-datencheck-plugin/releases';
    }

    public function customModuleLatestVersion(): string
    {
        // URL to the raw file containing the version number
        // TODO: Replace USERNAME/REPO with your actual GitHub repository
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
        return [];
    }

    public function setMenuOrder(int $order): void
    {
        $this->menu_order = $order;
    }

    public function getMenuOrder(): int
    {
        return 99;
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
            $tree = Validator::attributes($request)->tree();
        } catch (\Throwable $e) {
            return '';
        }

        if ($tree === null) {
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
            'xref' => 'XREF_PLACEHOLDER'
        ]);

        return view($this->name() . '::modules/datencheck/interaction', [
            'check_url'      => $check_url,
            'sibling_url'    => $sibling_url,
            'family_url'     => $family_url,
            'details_url'    => $details_url,
            'validation_url' => $validation_url,
            'add_task_url'   => $add_task_url,
            'ignore_error_url' => $ignore_error_url,
            'individual_url' => $individual_url,
        ]);
    }

    public function getMenu(Tree $tree): ?Menu
    {
        $id   = 'menu-datencheck';
        $file = __DIR__ . '/resources/images/datencheck_icon.png';
        $icon = '<i class="menu-icon fa fa-check-double"></i>'; // Fallback
        
        if (file_exists($file)) {
            $data   = file_get_contents($file);
            $base64 = base64_encode($data);
            // Use 58px size (perfectly between 50 and 64)
            $icon   = '<img src="data:image/png;base64,' . $base64 . '" class="wt-icon-menu" style="width:58px; height:58px; object-fit:contain; display:block; margin:0 auto 0;">';
        }

        $label = $icon . '<span>' . $this->title() . '</span>';

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
            '<i class="fas fa-stethoscope fa-fw" style="margin-right:8px; vertical-align:middle;"></i> <span style="vertical-align:middle; line-height:24px;">Übersicht & Analyse</span>', 
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
                '<i class="fas fa-eye-slash fa-fw" style="margin-right:8px; vertical-align:middle;"></i> <span style="vertical-align:middle; line-height:24px;">Ignorierte Einträge</span>', 
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
                case 'Admin':
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
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
            $params = $request->getQueryParams();
            
            if ($tree === null) {
                 return response(json_encode(['error' => 'Tree not found', 'params' => $params]))
                    ->withHeader('Content-Type', 'application/json');
            }

            $xref = $params['xref'] ?? '';
            $person = null;

            if (!empty($xref)) {
                $person = Registry::individualFactory()->make($xref, $tree);
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

            $result = ValidationService::validatePerson($person, $this, $birth, $death, $burial, $husb, $wife, $fam, $tree, $marrFormatted, $relType, $given, $surname);

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
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
            $params = $request->getQueryParams();

            $given = $params['given_name'] ?? '';
            $surname = $params['surname'] ?? '';
            $birth = $params['birth_date'] ?? '';

            $fuzzyDiffHighAge = (int)$this->getPreference('fuzzy_diff_high_age', '6');
            $fuzzyDiffDefault = (int)$this->getPreference('fuzzy_diff_default', '2');

            $data = InteractionService::runInteractiveCheck($tree, $given, $surname, $birth, $fuzzyDiffHighAge, $fuzzyDiffDefault);

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
        $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
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
        // Load view from module directory
        $view_file = __DIR__ . '/resources/views/modules/datencheck/admin.phtml';
        
        if (!file_exists($view_file)) {
            return response('<div class="alert alert-danger">View template not found: ' . $view_file . '</div>');
        }
        
        // Prepare variables for the view
        $title = $this->title();
        $fuzzy_diff_high_age = $this->getPreference('fuzzy_diff_high_age', '6');
        $fuzzy_diff_default = $this->getPreference('fuzzy_diff_default', '2');
        $module = $this;
        
        // Generate CSRF token for the form
        $csrf = csrf_field();
        
        // Generate route URLs (cannot be generated in view with require)
        $control_panel_url = route(\Fisharebest\Webtrees\Http\RequestHandlers\ControlPanel::class);
        $modules_all_url = route(\Fisharebest\Webtrees\Http\RequestHandlers\ModulesAllPage::class);
        
        // Generate translations (I18N not available in require'd view)
        $i18n_control_panel = \Fisharebest\Webtrees\I18N::translate('Control panel');
        $i18n_modules = \Fisharebest\Webtrees\I18N::translate('Modules');
        $i18n_save = \Fisharebest\Webtrees\I18N::translate('save');
        $i18n_cancel = \Fisharebest\Webtrees\I18N::translate('cancel');
        
        // Detect tree from request to pass to view
        try {
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
        } catch (\Throwable $e) {
            $tree = null;
        }

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
            // Get first key
            $first_name = array_key_first($trees_list);
            // Try to load full tree object
            try {
                // We need a real Tree object for other functions?
                // Actually, for analysis we only passed $tree_name to view.
                // But view might need $tree object.
                // If we can't load the object, we just pass name.
                // But `getAdminAction` doesn't use $tree object except for $tree->name().
            } catch (\Throwable $e) {}
            
            // Set name for view
            $tree_name = $first_name;
        } else {
             $tree_name = $tree ? $tree->name() : '';
        }
        
        $tree_title = $tree ? $tree->title() : $tree_name;

        // Render view content
        ob_start();
        require $view_file;
        $content = ob_get_clean();
        
        // Wrap in webtrees layout using standard response/view helper
        return response(view('layouts/administration', [
            'title' => $title . ' – Einstellungen',
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

        $this->setPreference('fuzzy_diff_high_age', $params['fuzzy_diff_high_age'] ?? '6');
        $this->setPreference('fuzzy_diff_default', $params['fuzzy_diff_default'] ?? '2');
        
        // Save validation settings
        $this->setPreference('max_marriages_warning', $params['max_marriages_warning'] ?? '5');
        $this->setPreference('enable_missing_data_checks', isset($params['enable_missing_data_checks']) ? '1' : '0');
        $this->setPreference('enable_geographic_checks', isset($params['enable_geographic_checks']) ? '1' : '0');
        $this->setPreference('enable_name_consistency_checks', isset($params['enable_name_consistency_checks']) ? '1' : '0');
        $this->setPreference('enable_source_checks', isset($params['enable_source_checks']) ? '1' : '0');
        
        // Save threshold preferences
        $this->setPreference('min_mother_age', $params['min_mother_age'] ?? '14');
        $this->setPreference('max_mother_age', $params['max_mother_age'] ?? '50');
        $this->setPreference('min_father_age', $params['min_father_age'] ?? '14');
        $this->setPreference('max_father_age', $params['max_father_age'] ?? '80');
        $this->setPreference('max_lifespan', $params['max_lifespan'] ?? '120');
        $this->setPreference('max_marriage_age_warning', $params['max_marriage_age_warning'] ?? '100');
        $this->setPreference('min_marriage_age_warning', $params['min_marriage_age_warning'] ?? '15');
        $this->setPreference('min_sibling_spacing_warning', $params['min_sibling_spacing_warning'] ?? '9');
        
        \Fisharebest\Webtrees\FlashMessages::addMessage('Einstellungen wurden erfolgreich gespeichert.', 'success');
        
        return response('')
            ->withStatus(302)
            ->withHeader('Location', $this->getConfigLink());
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getCheckSiblingAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
        $params = $request->getQueryParams();

        $husb = $params['husb'] ?? '';
        $wife = $params['wife'] ?? '';
        $given = $params['child_given'] ?? '';
        $surname = $params['child_surname'] ?? '';
        $birth = $params['child_birth'] ?? '';

        $fuzzyDiffHighAge = (int)$this->getPreference('fuzzy_diff_high_age', '6');
        $fuzzyDiffDefault = (int)$this->getPreference('fuzzy_diff_default', '2');

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
        $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
        $params = $request->getQueryParams();

        $xref = $params['xref'] ?? '';

        if (empty($xref)) {
            return response(json_encode(['error' => 'Missing xref parameter']))
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
    public function getAddTaskAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
            $params = $request->getQueryParams();

            $xref  = $params['xref'] ?? '';
            $title = $params['title'] ?? '';
            $note  = $params['note'] ?? '';

            if (empty($xref) || empty($title)) {
                return response(json_encode(['success' => false, 'message' => 'Missing parameters']))
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
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
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
        $tree = Validator::attributes($request)->tree();
        
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
            'title'         => 'Ignorierte Fehler',
            'tree'          => $tree,
            'ignoredErrors' => $ignoredErrors,
            'module_name'   => $this->name(),
            'lang'          => $lang,
        ]);

        return response(view('layouts/default', [
            'request' => $request,
            'title'   => 'Ignorierte Fehler',
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
            
            $tree = Validator::attributes($request)->tree() ?? Validator::queryParams($request)->tree();
            $params = $request->getQueryParams();
            
            // Pagination
            $offset = (int) ($params['offset'] ?? 0);
            $limit  = (int) ($params['limit'] ?? 200); // 200 is default fallback
            
            // Fetch XREFs directly from DB for performance
            $query = DB::table('individuals')
                ->where('i_file', '=', $tree->id())
                ->orderBy('i_id') // Ensure consistent order
                ->skip($offset)
                ->take($limit)
                ->select('i_id'); // We need the XREF (i_id)
                
            $rows = $query->get();
            $totalXrefs = $rows->pluck('i_id')->all();
            
            $results = [];
            $registry = Registry::individualFactory();
            $debugRaw = null;
            
            // Count total individuals for progress bar (only on first call ideally, but fast enough)
            $totalCount = DB::table('individuals')->where('i_file', '=', $tree->id())->count();

            foreach ($totalXrefs as $xref) {
                $person = $registry->make($xref, $tree);
                if ($person) {
                    // Run Validation
                    $validationResult = ValidationService::validatePerson($person, $this);
                    
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

            return response(json_encode([
                'offset' => $offset + count($totalXrefs),
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

