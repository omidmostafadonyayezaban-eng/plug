<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Collector {
    private static $instance = null;
    public $start_time;
    public $start_memory;

    private $php_errors = [];
    private $http_requests = [];
    private $http_requests_timings = [];
    private $hooks_fired = [];
    private $hooks_count = 0;
    private $template_file = '';
    private $template_hierarchy = [];
    private $collected_data = null;
    private $previous_error_handler = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->start_time = $GLOBALS['plug_monitor_start_time'] ?? microtime(true);
        $this->start_memory = $GLOBALS['plug_monitor_start_memory'] ?? memory_get_usage(false);
    }

    public function init_hooks() {
        // خطاها
        $options = Plug_Monitor_Defaults::get_options();
        if (!empty($options['collect_php_errors'])) {
            $this->previous_error_handler = set_error_handler([$this, 'error_handler'], E_ALL);
            register_shutdown_function([$this, 'handle_fatal']);
        }

        // HTTP
        if (!empty($options['collect_http'])) {
            add_filter('pre_http_request', [$this, 'pre_http_request'], 9999, 3);
            add_action('http_api_debug', [$this, 'http_api_debug'], 10, 5);
        }

        // هوک‌ها
        if (!empty($options['collect_hooks'])) {
            // ردیابی همه هوک‌ها - می‌تواند سنگین باشد، اما فقط وقتی فعال است
            add_action('all', [$this, 'track_hook'], 9999);
        }

        // قالب
        if (!empty($options['collect_template'])) {
            add_filter('template_include', [$this, 'capture_template'], 9999);
        }

        // شات‌داون برای جمع‌آوری نهایی
        add_action('shutdown', [$this, 'on_shutdown'], 9999);

        // برای AJAX لاگ کردن
        add_action('wp_ajax_plug_manual_collect', [$this, 'ajax_manual_collect']);
    }

    // --- Error Handling ---
    public function error_handler($errno, $errstr, $errfile, $errline) {
        // فقط خطاهای قابل نمایش
        if (!(error_reporting() & $errno)) {
            // با وجود @ سرکوب شده
            if ($this->previous_error_handler) {
                return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
            }
            return false;
        }

        $this->php_errors[] = [
            'type' => $this->friendly_error_type($errno),
            'code' => $errno,
            'message' => $errstr,
            'file' => $this->short_path($errfile),
            'full_file' => $errfile,
            'line' => $errline,
            'time' => microtime(true) - $this->start_time,
        ];

        // جلوگیری از انباشت بی‌نهایت
        if (count($this->php_errors) > 200) {
            array_shift($this->php_errors);
        }

        if ($this->previous_error_handler) {
            return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
        }
        return false; // اجازه اجرای هندلر پیش‌فرض
    }

    public function handle_fatal() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $this->php_errors[] = [
                'type' => $this->friendly_error_type($error['type']) . ' (Fatal)',
                'code' => $error['type'],
                'message' => $error['message'],
                'file' => $this->short_path($error['file']),
                'full_file' => $error['file'],
                'line' => $error['line'],
                'time' => microtime(true) - $this->start_time,
            ];
            // تلاش برای ذخیره حتی در Fatal
            $this->collect_and_store(true);
        }
    }

    private function friendly_error_type($type) {
        $map = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];
        return $map[$type] ?? 'UNKNOWN('.$type.')';
    }

    // --- HTTP Tracking ---
    public function pre_http_request($pre, $args, $url) {
        $key = md5($url . microtime(true) . wp_rand());
        $this->http_requests_timings[$key] = [
            'start' => microtime(true),
            'url' => $url,
            'args' => $args,
            'key' => $key,
        ];
        // ذخیره کلید برای تطبیق در http_api_debug
        $GLOBALS['plug_last_http_key'] = $key;
        return $pre;
    }

    public function http_api_debug($response, $context, $class, $args, $url) {
        $key = $GLOBALS['plug_last_http_key'] ?? null;
        $start = 0;
        if ($key && isset($this->http_requests_timings[$key])) {
            $start = $this->http_requests_timings[$key]['start'];
            unset($this->http_requests_timings[$key]);
        } else {
            // تلاش برای یافتن آخرین تایمینگ همین URL
            $found = null;
            foreach (array_reverse($this->http_requests_timings, true) as $k => $timing) {
                if ($timing['url'] === $url) {
                    $found = $k;
                    $start = $timing['start'];
                    break;
                }
            }
            if ($found) unset($this->http_requests_timings[$found]);
            else $start = microtime(true) - 0.01; // fallback
        }

        $duration = microtime(true) - ($start ?: microtime(true));
        $is_error = is_wp_error($response);

        $this->http_requests[] = [
            'url' => $url,
            'method' => $args['method'] ?? 'GET',
            'duration' => $duration,
            'status' => $is_error ? 'error' : (wp_remote_retrieve_response_code($response) ?: 0),
            'is_error' => $is_error,
            'error_message' => $is_error ? $response->get_error_message() : '',
            'args' => [
                'timeout' => $args['timeout'] ?? null,
                'blocking' => $args['blocking'] ?? null,
            ],
            'caller' => $this->get_caller(6),
        ];

        if (count($this->http_requests) > 100) {
            array_shift($this->http_requests);
        }
    }

    // --- Hooks ---
    public function track_hook($hook) {
        // جلوگیری از حلقه بی‌نهایت برای هوک‌های خودمان
        if (strpos($hook, 'plug_') === 0) return;
        $this->hooks_count++;
        if ($this->hooks_count <= 500) { // فقط 500 اول برای جلوگیری از مصرف حافظه
            $this->hooks_fired[] = $hook;
        }
    }

    // --- Template ---
    public function capture_template($template) {
        $this->template_file = $template;
        $this->template_hierarchy = [];
        // تلاش برای تشخیص hierarchy
        global $wp_query;
        if (isset($wp_query)) {
            // wp_query debug -> templates?
            // ساده: استفاده از current filter list?
        }
        return $template;
    }

    // --- Shutdown collection ---
    public function on_shutdown() {
        if ($this->collected_data !== null) return; // قبلا جمع‌آوری شده
        $this->collect_and_store(false);
    }

    public function collect_and_store($is_fatal = false) {
        $options = Plug_Monitor_Defaults::get_options();
        // بررسی اینکه آیا باید لاگ کنیم
        $should_log = false;

        if (defined('DOING_CRON') && DOING_CRON) $should_log = false;
        elseif (defined('DOING_AJAX') && DOING_AJAX) {
            // برای AJAX فقط اگر کاربر ادمین است و تنظیم فعال است
            if (current_user_can('manage_options')) $should_log = true;
        } else {
            if (!empty($options['auto_log_admin']) && is_admin() && current_user_can('manage_options')) {
                $should_log = true;
            }
            if (!empty($options['auto_log_frontend_admin_users']) && !is_admin() && current_user_can('manage_options')) {
                $should_log = true;
            }
            // اگر درخواست با پارامتر خاص باشد
            if (isset($_GET['plug_monitor_log']) && current_user_can('manage_options')) {
                $should_log = true;
            }
        }

        // همیشه داده را جمع‌آوری کن برای نمایش در نوار ادمین، حتی اگر ذخیره نکنیم
        $data = $this->collect_data($is_fatal);
        $this->collected_data = $data;
        $GLOBALS['plug_monitor_collected'] = $data;

        if (!$should_log) {
            return $data;
        }

        // ذخیره در DB
        $this->store_report($data);
        return $data;
    }

    public function collect_data($is_fatal = false) {
        global $wpdb, $wp_object_cache, $wp_scripts, $wp_styles;

        $end_time = microtime(true);
        $exec_time = $end_time - $this->start_time;
        $peak_memory = memory_get_peak_usage(true);
        $current_memory = memory_get_usage(true);
        $used_memory = memory_get_usage(false);

        // Queries
        $queries = [];
        $query_count = 0;
        $query_time = 0;
        $slow_queries = [];
        $duplicate_queries = [];

        if (isset($wpdb->queries) && is_array($wpdb->queries)) {
            $queries = $wpdb->queries;
            $query_count = count($queries);
        } elseif (isset($wpdb->num_queries)) {
            $query_count = $wpdb->num_queries;
        }

        $options = Plug_Monitor_Defaults::get_options();
        $slow_threshold = floatval($options['slow_query_threshold'] ?? 0.05);

        // آنالیز کوئری‌ها
        $query_map = [];
        if (!empty($queries)) {
            foreach ($queries as $i => $q) {
                // ساختار $wpdb->queries: [query, time, trace]
                if (is_array($q)) {
                    $sql = $q[0] ?? '';
                    $time = floatval($q[1] ?? 0);
                    $trace = $q[2] ?? '';
                } else {
                    $sql = (string)$q;
                    $time = 0;
                    $trace = '';
                }
                $query_time += $time;

                // نرمال‌سازی برای تشخیص کوئری تکراری
                $normalized = $this->normalize_query($sql);
                if (!isset($query_map[$normalized])) {
                    $query_map[$normalized] = ['count' => 0, 'time' => 0, 'example' => $sql, 'indexes' => []];
                }
                $query_map[$normalized]['count']++;
                $query_map[$normalized]['time'] += $time;
                $query_map[$normalized]['indexes'][] = $i;

                if ($time >= $slow_threshold) {
                    $slow_queries[] = [
                        'sql' => $sql,
                        'time' => $time,
                        'trace' => $this->parse_trace($trace ?: $this->get_caller(8)),
                        'caller' => $this->extract_caller_from_trace($trace),
                    ];
                }
            }

            // تشخیص تکراری‌ها
            if (!empty($options['duplicate_query_detection'])) {
                foreach ($query_map as $norm => $info) {
                    if ($info['count'] > 1) {
                        $duplicate_queries[] = [
                            'sql' => $info['example'],
                            'normalized' => $norm,
                            'count' => $info['count'],
                            'total_time' => $info['time'],
                        ];
                    }
                }
                // مرتب‌سازی بر اساس count نزولی
                usort($duplicate_queries, function($a,$b){ return $b['count'] <=> $a['count']; });
                $duplicate_queries = array_slice($duplicate_queries, 0, 20);
            }

            // مرتب‌سازی کندترین‌ها
            usort($slow_queries, function($a,$b){ return $b['time'] <=> $a['time']; });
            $slow_queries = array_slice($slow_queries, 0, 20);
        }

        // HTTP slow
        $slow_http = [];
        $http_threshold = floatval($options['slow_http_threshold'] ?? 0.8);
        foreach ($this->http_requests as $req) {
            if ($req['duration'] >= $http_threshold) {
                $slow_http[] = $req;
            }
        }

        // محیط
        $env = [];
        if (!empty($options['collect_env'])) {
            $env = [
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
                'db_version' => $wpdb->db_version(),
                'db_type' => method_exists($wpdb, 'db_server_info') ? $wpdb->db_server_info() : 'mysql',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'sapi' => php_sapi_name(),
                'extensions' => get_loaded_extensions(),
                'opcache' => function_exists('opcache_get_status') ? @opcache_get_status(false) : null,
                'object_cache' => is_object($wp_object_cache) ? get_class($wp_object_cache) : 'none',
                'multisite' => is_multisite(),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
                'savequeries' => defined('SAVEQUERIES') && SAVEQUERIES,
                'wp_68_compat' => true,
            ];

            // فیلتر برای HPOS و سایر افزونه‌ها - استاندارد 6.8
            $env = apply_filters('plug_monitor_environment_data', $env);

            // اطلاعات ووکامرس HPOS اگر فعال است
            if (class_exists('WooCommerce')) {
                $env['wc_version'] = defined('WC_VERSION') ? WC_VERSION : 'unknown';
                if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
                    $env['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                    $env['hpos_note'] = $env['hpos_enabled'] ? 'HPOS فعال - سازگار' : 'HPOS غیرفعال - استفاده از wp_posts';
                }
                $env['hpos_declare'] = '✅ سازگار (custom_order_tables + cart_checkout_blocks)';
            }
        }

        // افزونه‌ها
        $plugins = [];
        if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $active_plugins = is_array($active_plugins) ? $active_plugins : [];
        foreach ($all_plugins as $file => $info) {
            $plugins[] = [
                'file' => $file,
                'name' => $info['Name'] ?? $file,
                'version' => $info['Version'] ?? '',
                'active' => in_array($file, $active_plugins, true),
            ];
        }

        // قالب
        $theme_info = [
            'template_file' => $this->short_path($this->template_file),
            'full_template' => $this->template_file,
            'stylesheet' => get_stylesheet(),
            'template' => get_template(),
            'theme_version' => wp_get_theme()->get('Version'),
            'is_child' => is_child_theme(),
        ];

        // اسکریپت‌ها و استایل‌ها
        $assets = ['scripts' => [], 'styles' => []];
        if (!empty($options['collect_assets'])) {
            if ($wp_scripts instanceof WP_Scripts) {
                $assets['scripts'] = [
                    'queue' => $wp_scripts->queue ?? [],
                    'done' => $wp_scripts->done ?? [],
                    'count_registered' => count($wp_scripts->registered ?? []),
                ];
            }
            if ($wp_styles instanceof WP_Styles) {
                $assets['styles'] = [
                    'queue' => $wp_styles->queue ?? [],
                    'done' => $wp_styles->done ?? [],
                    'count_registered' => count($wp_styles->registered ?? []),
                ];
            }
        }

        // درخواست
        $request = [
            'url' => $this->get_current_url(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_id' => get_current_user_id(),
            'is_admin' => is_admin(),
            'is_ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'is_cron' => defined('DOING_CRON') && DOING_CRON,
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
            'is_cli' => defined('WP_CLI') && WP_CLI,
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // هوک‌ها
        $hooks_data = [
            'count' => $this->hooks_count,
            'fired' => array_slice($this->hooks_fired, 0, 300),
        ];

        // نتیجه نهایی
        $data = [
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'is_fatal' => $is_fatal,
            'execution' => [
                'total_time' => $exec_time,
                'peak_memory' => $peak_memory,
                'peak_memory_mb' => round($peak_memory / 1024 / 1024, 2),
                'current_memory' => $current_memory,
                'start_memory' => $this->start_memory,
                'memory_usage_mb' => round($used_memory / 1024 / 1024, 2),
            ],
            'database' => [
                'query_count' => $query_count,
                'total_query_time' => $query_time,
                'slow_queries' => $slow_queries,
                'duplicate_queries' => $duplicate_queries,
                'queries' => $options['enable_query_log'] ? array_slice($queries, 0, 100) : [], // فقط ۱۰۰ اول اگر لاگ فعال
                'slow_threshold' => $slow_threshold,
            ],
            'php_errors' => $this->php_errors,
            'http_requests' => [
                'count' => count($this->http_requests),
                'total_time' => array_sum(array_column($this->http_requests, 'duration')),
                'all' => $this->http_requests,
                'slow' => $slow_http,
            ],
            'hooks' => $hooks_data,
            'environment' => $env,
            'plugins' => $plugins,
            'theme' => $theme_info,
            'assets' => $assets,
            'request' => $request,
            'constants' => $this->collect_constants(),
        ];

        return $data;
    }

    private function collect_constants() {
        $important = ['WP_DEBUG','WP_DEBUG_LOG','WP_DEBUG_DISPLAY','SCRIPT_DEBUG','SAVEQUERIES','WP_CACHE','WP_MEMORY_LIMIT','WP_MAX_MEMORY_LIMIT','WP_POST_REVISIONS','WP_AUTO_UPDATE_CORE','DISALLOW_FILE_EDIT','DISALLOW_FILE_MODS','FORCE_SSL_ADMIN'];
        $out = [];
        foreach ($important as $c) {
            if (defined($c)) $out[$c] = constant($c);
        }
        return $out;
    }

    private function normalize_query($sql) {
        $sql = trim($sql);
        // حذف مقادیر عددی و رشته‌ای برای تشخیص الگو
        $sql = preg_replace("/'[^']*'/", "'?'", $sql);
        $sql = preg_replace('/"[^"]*"/', '"?"', $sql);
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = strtolower($sql);
        return substr($sql, 0, 500);
    }

    private function short_path($path) {
        if (!$path) return '';
        $path = str_replace([ABSPATH, WP_CONTENT_DIR], ['{ABSPATH}/', '{WP_CONTENT}/'], $path);
        return $path;
    }

    private function get_caller($depth = 5) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 5);
        // فیلتر فایل‌های wp-db و خود کلکتور
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '';
            if (strpos($file, 'wp-db.php') !== false) continue;
            if (strpos($file, 'class-collector.php') !== false) continue;
            if ($i < 2) continue;
            return $this->short_path($file) . ':' . ($frame['line'] ?? '?') . ' ' . ($frame['function'] ?? '');
        }
        $last = end($trace);
        return $this->short_path($last['file'] ?? 'unknown') . ':' . ($last['line'] ?? '?');
    }

    private function parse_trace($trace_str) {
        if (empty($trace_str)) return '';
        return substr($trace_str, 0, 1000);
    }

    private function extract_caller_from_trace($trace_str) {
        if (empty($trace_str)) return $this->get_caller();
        // $wpdb->queries trace معمولاً رشته‌ای از توابع است
        $lines = explode(',', $trace_str);
        // آخرین مورد قبل از wp-db
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (stripos($line, 'wpdb') !== false) continue;
            if (stripos($line, 'query') === 0) continue;
            return $line;
        }
        return $trace_str;
    }

    private function get_current_url() {
        if (is_admin()) {
            global $pagenow;
            $url = admin_url($pagenow ?? '');
            if (!empty($_GET)) $url = add_query_arg($_GET, $url);
            return $url;
        }
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $scheme = is_ssl() ? 'https://' : 'http://';
            return $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        return home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    }

    private function store_report($data) {
        global $wpdb;
        $table = $wpdb->prefix . PLUG_MONITOR_TABLE;

        $wpdb->insert($table, [
            'url' => substr($data['request']['url'] ?? '', 0, 500),
            'user_id' => get_current_user_id(),
            'exec_time' => floatval($data['execution']['total_time'] ?? 0),
            'query_count' => intval($data['database']['query_count'] ?? 0),
            'query_time' => floatval($data['database']['total_query_time'] ?? 0),
            'memory_peak' => intval($data['execution']['peak_memory'] ?? 0),
            'memory_usage' => intval($data['execution']['current_memory'] ?? 0),
            'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'new',
        ], ['%s','%d','%f','%d','%f','%d','%d','%s','%s']);
    }

    public function ajax_manual_collect() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        check_ajax_referer('plug_monitor_nonce', 'nonce');
        $data = $this->collect_data(false);
        $this->store_report($data);
        wp_send_json_success(['report' => $this->summarize_for_frontend($data)]);
    }

    public function summarize_for_frontend($data) {
        return [
            'time' => round($data['execution']['total_time'] ?? 0, 4),
            'memory' => $data['execution']['peak_memory_mb'] ?? 0,
            'queries' => $data['database']['query_count'] ?? 0,
            'query_time' => round($data['database']['total_query_time'] ?? 0, 4),
            'errors' => count($data['php_errors'] ?? []),
            'http' => $data['http_requests']['count'] ?? 0,
        ];
    }

    public function get_last_collected() {
        return $this->collected_data ?? ($GLOBALS['plug_monitor_collected'] ?? null);
    }
}
