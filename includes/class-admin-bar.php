<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Admin_Bar {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_bar_menu', [$this, 'add_nodes'], 9999);
        add_action('wp_enqueue_scripts', [$this, 'frontend_styles']);
        add_action('admin_enqueue_scripts', [$this, 'frontend_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_bar_script']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_bar_script']);
    }

    public function enqueue_bar_script() {
        if (!is_admin_bar_showing()) return;
        $options = Plug_Monitor_Defaults::get_options();
        if (empty($options['admin_bar'])) return;
        if (!is_admin() && empty($options['admin_bar_frontend'])) return;
        if (!Plug_Monitor_Settings::can_view()) return;

        wp_enqueue_script('plug-monitor-bar', PLUG_MONITOR_URL . 'assets/js/admin-bar.js', ['jquery'], PLUG_MONITOR_VERSION, true);
        wp_localize_script('plug-monitor-bar', 'PlugMonitorBar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plug_monitor_nonce'),
            'admin_url' => admin_url('admin.php?page=plug-monitor-report&report_id='),
        ]);
    }

    public function frontend_styles() {
        if (!is_admin_bar_showing()) return;
        $options = Plug_Monitor_Defaults::get_options();
        if (empty($options['admin_bar'])) return;
        if (!is_admin() && empty($options['admin_bar_frontend'])) return;
        if (!Plug_Monitor_Settings::can_view()) return;

        // استایل inline ساده برای admin bar
        wp_add_inline_style('admin-bar', '
            #wp-admin-bar-plug-monitor .ab-icon:before { content: "\f185"; top: 2px; }
            #wp-admin-bar-plug-monitor-slow { color: #ff6b6b !important; }
            #wp-admin-bar-plug-monitor .plug-badge { background:#ff4757; color:#fff; border-radius:10px; padding:1px 6px; font-size:10px; margin-left:4px; }
            #wp-admin-bar-plug-monitor .plug-ok { background:#2ed573; }
        ');
    }

    public function add_nodes($admin_bar) {
        if (!is_admin_bar_showing()) return;
        $options = Plug_Monitor_Defaults::get_options();
        if (empty($options['admin_bar'])) return;
        if (!is_admin() && empty($options['admin_bar_frontend'])) return;
        if (!Plug_Monitor_Settings::can_view()) return;

        $data = $GLOBALS['plug_monitor_collected'] ?? null;
        if (!$data) {
            // تلاش برای گرفتن داده کالکتور singleton
            $collector = Plug_Monitor_Collector::instance();
            $data = $collector->get_last_collected();
        }

        if (!$data) {
            // اگر هنوز داده نداریم، مقادیر تقریبی
            $time = microtime(true) - ($GLOBALS['plug_monitor_start_time'] ?? microtime(true));
            $memory = round(memory_get_peak_usage(true)/1024/1024, 1);
            $queries = $GLOBALS['wpdb']->num_queries ?? 0;
            $title = sprintf('⏱ %.3fs | 🧠 %sMB | 🗄 %dQ', $time, $memory, $queries);
            $main = [
                'id' => 'plug-monitor',
                'title' => $title,
                'href' => admin_url('admin.php?page=plug-monitor'),
                'meta' => ['title' => 'مانیتور هوشمند - در حال جمع‌آوری داده...']
            ];
            $admin_bar->add_node($main);
            return;
        }

        $exec = round($data['execution']['total_time'] ?? 0, 3);
        $mem = $data['execution']['peak_memory_mb'] ?? 0;
        $qcount = $data['database']['query_count'] ?? 0;
        $qtime = round($data['database']['total_query_time'] ?? 0, 3);
        $errors = count($data['php_errors'] ?? []);
        $http = $data['http_requests']['count'] ?? 0;
        $slow_q = count($data['database']['slow_queries'] ?? []);

        // تشخیص وضعیت بحرانی
        $is_slow = $exec > 2 || $mem > 100 || $slow_q > 0 || $errors > 0;
        $badge_class = $is_slow ? '' : 'plug-ok';
        $badge = $is_slow ? '<span class="plug-badge">!</span>' : '<span class="plug-badge plug-ok">✓</span>';

        $title = sprintf('%s %.2fs | %sMB | %dQ', $badge, $exec, $mem, $qcount);

        $admin_bar->add_node([
            'id' => 'plug-monitor',
            'title' => $title,
            'href' => admin_url('admin.php?page=plug-monitor'),
            'meta' => ['title' => 'مانیتور هوشمند']
        ]);

        $admin_bar->add_node([
            'id' => 'plug-monitor-overview',
            'parent' => 'plug-monitor',
            'title' => sprintf('زمان اجرا: %s ثانیه | حافظه: %s MB | کوئری: %d (%s ثانیه)', $exec, $mem, $qcount, $qtime),
            'href' => false,
        ]);

        if ($slow_q > 0) {
            $admin_bar->add_node([
                'id' => 'plug-monitor-slow',
                'parent' => 'plug-monitor',
                'title' => sprintf('⚠️ کوئری کند: %d مورد (آستانه %s sec)', $slow_q, $data['database']['slow_threshold'] ?? '0.05'),
                'href' => admin_url('admin.php?page=plug-monitor-report&report_id=live#slow'),
            ]);
        }

        if ($errors > 0) {
            $admin_bar->add_node([
                'id' => 'plug-monitor-errors',
                'parent' => 'plug-monitor',
                'title' => sprintf('❌ خطای PHP: %d مورد', $errors),
                'href' => admin_url('admin.php?page=plug-monitor-report&report_id=live#errors'),
            ]);
        }

        if ($http > 0) {
            $admin_bar->add_node([
                'id' => 'plug-monitor-http',
                'parent' => 'plug-monitor',
                'title' => sprintf('🌐 درخواست HTTP: %d مورد (مجموع %.2f ثانیه)', $http, $data['http_requests']['total_time'] ?? 0),
                'href' => admin_url('admin.php?page=plug-monitor-report&report_id=live#http'),
            ]);
        }

        $admin_bar->add_node([
            'id' => 'plug-monitor-env',
            'parent' => 'plug-monitor',
            'title' => sprintf('PHP %s | WP %s | %s', $data['environment']['php_version'] ?? PHP_VERSION, $data['environment']['wp_version'] ?? '', $data['theme']['template_file'] ?? ''),
            'href' => false,
        ]);

        $admin_bar->add_node([
            'id' => 'plug-monitor-actions',
            'parent' => 'plug-monitor',
            'title' => '📊 مشاهده گزارش‌ها',
            'href' => admin_url('admin.php?page=plug-monitor'),
        ]);

        $admin_bar->add_node([
            'id' => 'plug-monitor-generate',
            'parent' => 'plug-monitor',
            'title' => '⚡ گزارش فوری + تحلیل AI',
            'href' => '#',
            'meta' => ['onclick' => 'if(window.plugManualCollect){plugManualCollect(); return false;}']
        ]);

        $admin_bar->add_node([
            'id' => 'plug-monitor-settings',
            'parent' => 'plug-monitor',
            'title' => '⚙️ تنظیمات OpenRouter',
            'href' => admin_url('admin.php?page=plug-monitor-settings'),
        ]);
    }
}
