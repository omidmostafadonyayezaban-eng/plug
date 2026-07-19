<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Analyzer {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ساخت خلاصه فشرده برای ارسال به AI (برای کاهش توکن)
     */
    public function build_compact_summary($full_report) {
        if (is_string($full_report)) {
            $full_report = json_decode($full_report, true);
        }
        if (!is_array($full_report)) return [];

        $compact = [
            'url' => $full_report['request']['url'] ?? '',
            'method' => $full_report['request']['method'] ?? '',
            'generated_at' => $full_report['generated_at'] ?? '',
            'execution' => [
                'total_time_sec' => round($full_report['execution']['total_time'] ?? 0, 4),
                'peak_memory_mb' => $full_report['execution']['peak_memory_mb'] ?? 0,
                'memory_usage_mb' => $full_report['execution']['memory_usage_mb'] ?? 0,
            ],
            'database' => [
                'query_count' => $full_report['database']['query_count'] ?? 0,
                'total_query_time_sec' => round($full_report['database']['total_query_time'] ?? 0, 4),
                'slow_threshold' => $full_report['database']['slow_threshold'] ?? 0,
                'slow_queries_top5' => array_slice(array_map(function($q){
                    return [
                        'time_sec' => round($q['time'] ?? 0, 4),
                        'sql' => substr($q['sql'] ?? '', 0, 400),
                        'caller' => substr($q['caller'] ?? $q['trace'] ?? '', 0, 200),
                    ];
                }, $full_report['database']['slow_queries'] ?? []), 0, 5),
                'duplicate_queries_top5' => array_slice($full_report['database']['duplicate_queries'] ?? [], 0, 5),
            ],
            'php_errors' => array_slice(array_map(function($e){
                return [
                    'type' => $e['type'] ?? '',
                    'message' => substr($e['message'] ?? '', 0, 300),
                    'file' => $e['file'] ?? '',
                    'line' => $e['line'] ?? '',
                ];
            }, $full_report['php_errors'] ?? []), 0, 10),
            'http_requests' => [
                'count' => $full_report['http_requests']['count'] ?? 0,
                'total_time_sec' => round($full_report['http_requests']['total_time'] ?? 0, 4),
                'slow' => array_slice(array_map(function($h){
                    return [
                        'url' => substr($h['url'] ?? '', 0, 200),
                        'duration_sec' => round($h['duration'] ?? 0, 4),
                        'status' => $h['status'] ?? '',
                        'caller' => substr($h['caller'] ?? '', 0, 150),
                    ];
                }, $full_report['http_requests']['slow'] ?? []), 0, 5),
                'all_sample' => array_slice(array_map(function($h){
                    return [
                        'url' => substr($h['url'] ?? '', 0, 120),
                        'dur' => round($h['duration'] ?? 0, 4),
                    ];
                }, $full_report['http_requests']['all'] ?? []), 0, 5),
            ],
            'plugins' => [
                'total' => count($full_report['plugins'] ?? []),
                'active' => array_values(array_filter(array_map(function($p){
                    return $p['active'] ? $p['name'] . ' v' . $p['version'] : null;
                }, $full_report['plugins'] ?? []))),
            ],
            'theme' => $full_report['theme'] ?? [],
            'assets' => [
                'scripts_enqueued' => count($full_report['assets']['scripts']['queue'] ?? []),
                'styles_enqueued' => count($full_report['assets']['styles']['queue'] ?? []),
                'scripts_list_sample' => array_slice($full_report['assets']['scripts']['queue'] ?? [], 0, 20),
                'styles_list_sample' => array_slice($full_report['assets']['styles']['queue'] ?? [], 0, 20),
            ],
            'environment' => [
                'php' => $full_report['environment']['php_version'] ?? '',
                'wp' => $full_report['environment']['wp_version'] ?? '',
                'memory_limit' => $full_report['environment']['memory_limit'] ?? '',
                'savequeries' => $full_report['environment']['savequeries'] ?? false,
                'object_cache' => $full_report['environment']['object_cache'] ?? '',
                'multisite' => $full_report['environment']['multisite'] ?? false,
            ],
            'hooks' => [
                'count' => $full_report['hooks']['count'] ?? 0,
            ],
            'constants' => $full_report['constants'] ?? [],
        ];

        return $compact;
    }

    /**
     * تحلیل و ذخیره
     */
    public function analyze_and_store($report_id, $model_override = null) {
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $report_id), ARRAY_A);

        if (!$row) {
            return new WP_Error('not_found', 'گزارش یافت نشد.');
        }

        $full_data = json_decode($row['data'], true);
        if (!$full_data) {
            return new WP_Error('invalid_data', 'داده گزارش خراب است.');
        }

        $compact = $this->build_compact_summary($full_data);

        $options = Plug_Monitor_Defaults::get_options();
        $api_key = $options['api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'کلید API OpenRouter تنظیم نشده است. به تنظیمات بروید و کلید را وارد کنید.');
        }

        $client = new Plug_Monitor_OpenRouter($api_key);
        $model = $model_override ?: ($options['model'] ?? 'openai/gpt-4o-mini');

        // تحلیل
        $result = $client->analyze_report($compact, $model, $options['system_prompt'] ?? null);

        if (is_wp_error($result)) {
            return $result;
        }

        $analysis_text = $result['choices'][0]['message']['content'] ?? '';
        if (empty($analysis_text)) {
            return new WP_Error('empty_response', 'پاسخ تحلیل خالی بود.');
        }

        // ذخیره تحلیل
        $wpdb->update($table, [
            'analysis' => wp_kses_post($analysis_text),
            'model' => sanitize_text_field($model),
            'status' => 'analyzed',
        ], ['id' => $report_id], ['%s','%s','%s'], ['%d']);

        return [
            'analysis' => $analysis_text,
            'model' => $model,
            'usage' => $result['usage'] ?? [],
            'report_id' => $report_id,
        ];
    }

    /**
     * تحلیل سریع بدون ذخیره (برای نمایش زنده)
     */
    public function quick_analyze_current_page($live_data, $model = null) {
        $options = Plug_Monitor_Defaults::get_options();
        $api_key = $options['api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'کلید API تنظیم نشده');
        }
        $compact = $this->build_compact_summary($live_data);
        $client = new Plug_Monitor_OpenRouter($api_key);
        $result = $client->analyze_report($compact, $model);
        if (is_wp_error($result)) return $result;
        return $result['choices'][0]['message']['content'] ?? '';
    }
}
