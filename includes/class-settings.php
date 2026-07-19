<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Settings {
    private static $instance = null;
    const PAGE_SLUG = 'plug-monitor';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // پیام SAVEQUERIES
        add_action('admin_notices', [$this, 'savequeries_notice']);
    }

    public function admin_menu() {
        $cap = 'manage_options';

        add_menu_page(
            __('مانیتور هوشمند', 'plug-monitor'),
            __('مانیتور هوشمند', 'plug-monitor'),
            $cap,
            self::PAGE_SLUG,
            [$this, 'render_dashboard'],
            'dashicons-chart-bar',
            80
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('گزارش‌ها', 'plug-monitor'),
            __('گزارش‌ها', 'plug-monitor'),
            $cap,
            self::PAGE_SLUG,
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('تحلیل تک گزارش', 'plug-monitor'),
            __('تحلیل گزارش', 'plug-monitor'),
            $cap,
            self::PAGE_SLUG . '-report',
            [$this, 'render_single_report']
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('تنظیمات', 'plug-monitor'),
            __('تنظیمات', 'plug-monitor'),
            $cap,
            self::PAGE_SLUG . '-settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('راهنما و وضعیت سیستم', 'plug-monitor'),
            __('راهنما', 'plug-monitor'),
            $cap,
            self::PAGE_SLUG . '-help',
            [$this, 'render_help']
        );
    }

    public function register_settings() {
        register_setting('plug_monitor_settings_group', Plug_Monitor_Defaults::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_options'],
        ]);
    }

    public function sanitize_options($input) {
        $current = Plug_Monitor_Defaults::get_options();
        $out = $current;

        // متنی
        if (isset($input['api_key'])) $out['api_key'] = sanitize_text_field(trim($input['api_key']));
        if (isset($input['model'])) $out['model'] = sanitize_text_field(trim($input['model']));
        if (isset($input['custom_http_referer'])) $out['custom_http_referer'] = esc_url_raw($input['custom_http_referer']);
        if (isset($input['custom_x_title'])) $out['custom_x_title'] = sanitize_text_field($input['custom_x_title']);
        if (isset($input['system_prompt'])) $out['system_prompt'] = wp_kses_post($input['system_prompt']);

        // عددی
        if (isset($input['temperature'])) $out['temperature'] = max(0, min(2, floatval($input['temperature'])));
        if (isset($input['max_tokens'])) $out['max_tokens'] = max(100, min(8000, intval($input['max_tokens'])));
        if (isset($input['slow_query_threshold'])) $out['slow_query_threshold'] = max(0.001, floatval($input['slow_query_threshold']));
        if (isset($input['slow_http_threshold'])) $out['slow_http_threshold'] = max(0.1, floatval($input['slow_http_threshold']));
        if (isset($input['max_reports'])) $out['max_reports'] = max(10, intval($input['max_reports']));
        if (isset($input['keep_reports_days'])) $out['keep_reports_days'] = max(1, intval($input['keep_reports_days']));

        // چک‌باکس‌ها
        $bools = ['collect_hooks','collect_http','collect_php_errors','collect_env','collect_assets','collect_template','admin_bar','admin_bar_frontend','auto_log_admin','auto_log_frontend_admin_users','enable_query_log','duplicate_query_detection','highlight_slow','auto_analyze'];
        foreach ($bools as $key) {
            $out[$key] = !empty($input[$key]) ? true : false;
        }

        // نقش‌ها
        if (isset($input['enable_for_roles']) && is_array($input['enable_for_roles'])) {
            $out['enable_for_roles'] = array_map('sanitize_text_field', $input['enable_for_roles']);
        }

        return $out;
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, self::PAGE_SLUG) === false && $hook !== 'index.php' && strpos($hook, 'dashboard') === false) {
            // برای نوار ادمین و داشبورد هم ممکن است نیاز باشد
            if (!is_admin_bar_showing()) return;
        }

        $screen = get_current_screen();
        $is_our_page = $screen && strpos($screen->id, self::PAGE_SLUG) !== false;

        if ($is_our_page) {
            wp_enqueue_style('plug-monitor-admin', PLUG_MONITOR_URL . 'assets/css/admin.css', [], PLUG_MONITOR_VERSION);
            wp_enqueue_script('plug-monitor-admin', PLUG_MONITOR_URL . 'assets/js/admin.js', ['jquery'], PLUG_MONITOR_VERSION, true);
            wp_localize_script('plug-monitor-admin', 'PlugMonitor', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('plug_monitor_nonce'),
                'i18n' => [
                    'testing' => __('در حال تست...', 'plug-monitor'),
                    'fetching_models' => __('در حال دریافت مدل‌ها...', 'plug-monitor'),
                    'analyzing' => __('در حال تحلیل...', 'plug-monitor'),
                    'confirm_delete' => __('آیا مطمئن هستید؟', 'plug-monitor'),
                ]
            ]);
        }
    }

    public function render_dashboard() {
        // مدیریت حذف گزارش‌ها
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['report_id'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'plug_delete_' . $_GET['report_id'])) {
                wp_die('Nonce invalid');
            }
            global $wpdb;
            $table = Plug_AI_Monitor::get_table();
            $wpdb->delete($table, ['id' => intval($_GET['report_id'])], ['%d']);
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'truncate') {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'plug_truncate')) {
                wp_die('Nonce invalid');
            }
            global $wpdb;
            $table = Plug_AI_Monitor::get_table();
            $wpdb->query("TRUNCATE TABLE $table");
            wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        include PLUG_MONITOR_PATH . 'templates/admin-dashboard.php';
    }

    public function render_single_report() {
        include PLUG_MONITOR_PATH . 'templates/admin-report.php';
    }

    public function render_settings() {
        $options = Plug_Monitor_Defaults::get_options();
        // برای نمایش مدل‌ها
        $client = new Plug_Monitor_OpenRouter($options['api_key'] ?? '');
        $models = $client->fetch_models(false);
        $has_error = is_wp_error($models);
        include PLUG_MONITOR_PATH . 'templates/admin-settings.php';
    }

    public function render_help() {
        include PLUG_MONITOR_PATH . 'templates/admin-help.php';
    }

    public function savequeries_notice() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, self::PAGE_SLUG) === false) return;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo '<strong>Plug Monitor:</strong> برای ردیابی کامل کوئری‌ها، لطفا در فایل <code>wp-config.php</code> خط زیر را اضافه کنید: <code>define(\'SAVEQUERIES\', true);</code> - در غیر این صورت فقط تعداد کوئری نمایش داده می‌شود، نه جزئیات.';
            echo '</p></div>';
        }
    }

    public static function can_view() {
        $options = Plug_Monitor_Defaults::get_options();
        $roles = $options['enable_for_roles'] ?? ['administrator'];
        $user = wp_get_current_user();
        if (empty($user->roles)) return false;
        foreach ($user->roles as $role) {
            if (in_array($role, $roles, true)) return true;
        }
        return current_user_can('manage_options');
    }
}
