<?php
/**
 * Plugin Name: مانیتور هوشمند وردپرس - Plug AI Performance Monitor
 * Plugin URI: https://github.com/omidmostafadonyayezaban-eng/plug
 * Description: افزونه ای کامل مشابه Query Monitor با قابلیت گزارش گیری از عملکرد، کوئری‌ها، حافظه، هوک‌ها، درخواست‌های HTTP و تحلیل هوشمند مشکلات با OpenRouter AI و پاسخ فارسی. شامل تنظیمات کامل API، تست اتصال، انتخاب مدل و دریافت لیست مدل‌ها. سازگار با آخرین وردپرس 6.8 و HPOS ووکامرس.
 * Version: 1.1.0
 * Author: Plug Team
 * Author URI: https://github.com/omidmostafadonyayezaban-eng/plug
 * Text Domain: plug-monitor
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/omidmostafadonyayezaban-eng/plug
 */

if (!defined('ABSPATH')) exit;

/**
 * HPOS Compatibility Declaration - استاندارد ووکامرس
 * این اعلام باید قبل از plugins_loaded باشد تا ووکامرس آن را بخواند
 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
 */
add_action('before_woocommerce_init', function() {
    // اطمینان از وجود کلاس FeaturesUtil (ووکامرس 7.1+)
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
    // برای نسخه‌های قدیمی‌تر ووکامرس که checkout_blocks جدا بود
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        // به صورت شرطی - اگر متد وجود داشت، مشکلی ندارد دوباره اعلام شود
        try {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
        } catch (\Exception $e) {}
    }
});

define('PLUG_MONITOR_VERSION', '1.1.0');
define('PLUG_MONITOR_FILE', __FILE__);
define('PLUG_MONITOR_PATH', plugin_dir_path(__FILE__));
define('PLUG_MONITOR_URL', plugin_dir_url(__FILE__));
define('PLUG_MONITOR_BASENAME', plugin_basename(__FILE__));
define('PLUG_MONITOR_TABLE', 'plug_monitor_reports');

// شروع تایمر هر چه زودتر
if (!isset($GLOBALS['plug_monitor_start_time'])) {
    $GLOBALS['plug_monitor_start_time'] = microtime(true);
}
if (!isset($GLOBALS['plug_monitor_start_memory'])) {
    $GLOBALS['plug_monitor_start_memory'] = memory_get_usage(false);
}

// اگر SAVEQUERIES فعال نیست هشدار بده ولی خودمان هم لاگ می‌گیریم
if (!defined('SAVEQUERIES')) {
    // برای اینکه $wpdb->queries پر باشد کاربر باید در wp-config.php تعریف کند
    // اما ما برای سازگاری یک فال‌بک داریم
}

// بارگذاری کلاس‌ها
require_once PLUG_MONITOR_PATH . 'includes/class-defaults.php';
require_once PLUG_MONITOR_PATH . 'includes/class-hpos.php';
require_once PLUG_MONITOR_PATH . 'includes/class-collector.php';
require_once PLUG_MONITOR_PATH . 'includes/class-openrouter.php';
require_once PLUG_MONITOR_PATH . 'includes/class-analyzer.php';
require_once PLUG_MONITOR_PATH . 'includes/class-settings.php';
require_once PLUG_MONITOR_PATH . 'includes/class-ajax.php';
require_once PLUG_MONITOR_PATH . 'includes/class-admin-bar.php';
require_once PLUG_MONITOR_PATH . 'includes/class-reporter.php';

final class Plug_AI_Monitor {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(PLUG_MONITOR_FILE, [$this, 'activate']);
        register_deactivation_hook(PLUG_MONITOR_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init'], 1);
        add_action('init', [$this, 'load_textdomain']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('plug-monitor', false, dirname(PLUG_MONITOR_BASENAME) . '/languages');
    }

    public function init() {
        // تنظیمات پیش‌فرض
        Plug_Monitor_Defaults::maybe_install();

        // HPOS Handler - باید زودتر لود شود
        Plug_Monitor_HPOS::instance();

        // کالکتور: همیشه فعال برای جمع‌آوری
        Plug_Monitor_Collector::instance()->init_hooks();

        // ادمین
        if (is_admin()) {
            Plug_Monitor_Settings::instance();
            Plug_Monitor_Ajax::instance();
            Plug_Monitor_Reporter::instance();
        }

        // نوار ادمین
        Plug_Monitor_Admin_Bar::instance();

        // کرون پاکسازی - استاندارد WP
        add_action('plug_monitor_cleanup', [$this, 'cleanup']);
        if (!wp_next_scheduled('plug_monitor_cleanup')) {
            wp_schedule_event(time(), 'daily', 'plug_monitor_cleanup');
        }

        // اعلام REST API سازگار با جدیدترین وردپرس 6.8
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes() {
        // REST برای گزارش‌ها - اختیاری برای اپلیکیشن‌های خارجی
        register_rest_route('plug-monitor/v1', '/reports', [
            'methods' => 'GET',
            'callback' => function($request) {
                if (!current_user_can('manage_options')) {
                    return new WP_Error('forbidden', 'دسترسی غیرمجاز', ['status' => 403]);
                }
                $limit = $request->get_param('limit') ?? 20;
                $reports = Plug_Monitor_Reporter::get_reports(min(100, intval($limit)), 0);
                return rest_ensure_response($reports);
            },
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function activate() {
        Plug_Monitor_Defaults::maybe_install();
        $this->create_table();

        if (!wp_next_scheduled('plug_monitor_cleanup')) {
            wp_schedule_event(time(), 'daily', 'plug_monitor_cleanup');
        }

        // بررسی SAVEQUERIES
        // نمی‌توانیم wp-config را ویرایش کنیم، فقط یادآوری می‌کنیم
    }

    public function deactivate() {
        wp_clear_scheduled_hook('plug_monitor_cleanup');
    }

    public function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . PLUG_MONITOR_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            url varchar(500) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            exec_time float DEFAULT 0,
            query_count int DEFAULT 0,
            query_time float DEFAULT 0,
            memory_peak bigint(20) DEFAULT 0,
            memory_usage bigint(20) DEFAULT 0,
            data longtext,
            analysis longtext,
            model varchar(200) DEFAULT '',
            status varchar(20) DEFAULT 'new',
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function cleanup() {
        $options = Plug_Monitor_Defaults::get_options();
        $keep = isset($options['keep_reports_days']) ? intval($options['keep_reports_days']) : 7;
        if ($keep <= 0) $keep = 7;

        global $wpdb;
        $table = $wpdb->prefix . PLUG_MONITOR_TABLE;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            gmdate('Y-m-d H:i:s', time() - $keep * DAY_IN_SECONDS)
        ));

        // حفظ حداکثر 200 گزارش آخر
        $max = isset($options['max_reports']) ? intval($options['max_reports']) : 200;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > $max) {
            $to_delete = $count - $max;
            $wpdb->query("DELETE FROM $table ORDER BY created_at ASC LIMIT $to_delete");
        }
    }

    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . PLUG_MONITOR_TABLE;
    }
}

function plug_monitor() {
    return Plug_AI_Monitor::instance();
}

plug_monitor();
