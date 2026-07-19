<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Ajax {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_plug_fetch_models', [$this, 'fetch_models']);
        add_action('wp_ajax_plug_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_plug_analyze_report', [$this, 'analyze_report']);
        add_action('wp_ajax_plug_delete_report', [$this, 'delete_report']);
        add_action('wp_ajax_plug_export_report', [$this, 'export_report']);
        add_action('wp_ajax_plug_get_live_stats', [$this, 'get_live_stats']);
        add_action('wp_ajax_plug_manual_collect', [$this, 'manual_collect']);
        add_action('wp_ajax_plug_clear_cache', [$this, 'clear_cache']);
    }

    private function check_perm() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }
        check_ajax_referer('plug_monitor_nonce', 'nonce');
    }

    public function fetch_models() {
        $this->check_perm();
        $force = !empty($_POST['force']);
        $options = Plug_Monitor_Defaults::get_options();
        $api_key = $options['api_key'] ?? '';
        $client = new Plug_Monitor_OpenRouter($api_key);
        $models = $client->fetch_models($force);

        if (is_wp_error($models)) {
            wp_send_json_error(['message' => $models->get_error_message()]);
        }

        wp_send_json_success(['models' => $models, 'count' => count($models)]);
    }

    public function test_connection() {
        $this->check_perm();
        $model = sanitize_text_field($_POST['model'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        // اگر api_key از فرم آمده، موقت استفاده کن
        if (!empty($api_key)) {
            $client = new Plug_Monitor_OpenRouter($api_key);
        } else {
            $options = Plug_Monitor_Defaults::get_options();
            $client = new Plug_Monitor_OpenRouter($options['api_key'] ?? '');
        }

        $result = $client->test_connection($model);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public function analyze_report() {
        $this->check_perm();
        $report_id = intval($_POST['report_id'] ?? 0);
        $model = sanitize_text_field($_POST['model'] ?? '');

        if (!$report_id) {
            wp_send_json_error(['message' => 'شناسه گزارش نامعتبر است']);
        }

        $analyzer = Plug_Monitor_Analyzer::instance();
        $result = $analyzer->analyze_and_store($report_id, $model ?: null);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message(), 'details' => $result->get_error_data()]);
        }

        wp_send_json_success($result);
    }

    public function delete_report() {
        $this->check_perm();
        $id = intval($_POST['report_id'] ?? 0);
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        if ($deleted !== false) {
            wp_send_json_success(['message' => 'حذف شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف']);
        }
    }

    public function export_report() {
        $this->check_perm();
        $id = intval($_GET['report_id'] ?? $_POST['report_id'] ?? 0);
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            wp_send_json_error(['message' => 'گزارش یافت نشد']);
        }
        wp_send_json_success(['report' => $row]);
    }

    public function get_live_stats() {
        $this->check_perm();
        $collector = Plug_Monitor_Collector::instance();
        $data = $collector->get_last_collected();
        if (!$data) {
            // اگر داده زنده نداریم، یک کالکشن دستی فیک بساز از globals
            $data = [
                'execution' => [
                    'total_time' => microtime(true) - ($GLOBALS['plug_monitor_start_time'] ?? microtime(true)),
                    'peak_memory_mb' => round(memory_get_peak_usage(true)/1024/1024, 2),
                ],
                'database' => [
                    'query_count' => $GLOBALS['wpdb']->num_queries ?? 0,
                ]
            ];
        }
        wp_send_json_success(['stats' => $data, 'summary' => $collector->summarize_for_frontend($data)]);
    }

    public function manual_collect() {
        $this->check_perm();
        $collector = Plug_Monitor_Collector::instance();
        $data = $collector->collect_data(false);
        // ذخیره
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        $wpdb->insert($table, [
            'url' => substr($data['request']['url'] ?? '', 0, 500),
            'user_id' => get_current_user_id(),
            'exec_time' => floatval($data['execution']['total_time'] ?? 0),
            'query_count' => intval($data['database']['query_count'] ?? 0),
            'query_time' => floatval($data['database']['total_query_time'] ?? 0),
            'memory_peak' => intval($data['execution']['peak_memory'] ?? 0),
            'memory_usage' => intval($data['execution']['current_memory'] ?? 0),
            'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => 'new',
        ]);

        $insert_id = $wpdb->insert_id;

        // اگر auto_analyze فعال است
        $options = Plug_Monitor_Defaults::get_options();
        $analysis = null;
        if (!empty($options['auto_analyze']) && !empty($options['api_key'])) {
            $analyzer = Plug_Monitor_Analyzer::instance();
            $res = $analyzer->analyze_and_store($insert_id);
            if (!is_wp_error($res)) {
                $analysis = $res['analysis'] ?? null;
            }
        }

        wp_send_json_success([
            'report_id' => $insert_id,
            'summary' => $collector->summarize_for_frontend($data),
            'analysis' => $analysis,
        ]);
    }

    public function clear_cache() {
        $this->check_perm();
        delete_transient(Plug_Monitor_Defaults::get_models_transient_key());
        wp_send_json_success(['message' => 'کش پاک شد']);
    }
}
