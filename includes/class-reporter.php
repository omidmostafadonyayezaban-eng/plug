<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Reporter {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // برای خروجی json
        add_action('init', [$this, 'maybe_export']);
    }

    public function maybe_export() {
        if (isset($_GET['plug_export']) && current_user_can('manage_options')) {
            check_admin_referer('plug_export_' . ($_GET['report_id'] ?? ''));
            $id = intval($_GET['report_id'] ?? 0);
            global $wpdb;
            $table = Plug_AI_Monitor::get_table();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                nocache_headers();
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="plug-report-' . $id . '-' . gmdate('Y-m-d') . '.json"');
                echo wp_json_encode(json_decode($row['data'], true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
        }
    }

    public static function get_reports($limit = 50, $offset = 0, $orderby = 'created_at DESC') {
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        $allowed_order = ['created_at DESC','created_at ASC','exec_time DESC','query_count DESC','memory_peak DESC'];
        if (!in_array($orderby, $allowed_order, true)) $orderby = 'created_at DESC';
        $sql = "SELECT * FROM $table ORDER BY $orderby LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset), ARRAY_A);
    }

    public static function get_report($id) {
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
    }

    public static function count_reports() {
        global $wpdb;
        $table = Plug_AI_Monitor::get_table();
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
    }

    public static function render_analysis_html($analysis_text) {
        if (empty($analysis_text)) return '<p>هنوز تحلیلی انجام نشده است.</p>';
        // تبدیل مارک‌داون ساده به HTML
        $text = esc_html($analysis_text);
        // bold
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        // headings
        $text = preg_replace('/^###\s*(.+)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^##\s*(.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^#\s*(.+)$/m', '<h2>$1</h2>', $text);
        // لیست
        $text = preg_replace('/^\s*-\s*(.+)$/m', '<li>$1</li>', $text);
        $text = str_replace("\n", "<br>", $text);
        // بسته‌بندی li ها
        $text = preg_replace('/((?:<li>.*<\/li><br>)+)/', '<ul>$1</ul>', $text);
        $text = str_replace('<br><ul>', '<ul>', $text);
        $text = str_replace('</li><br>', '</li>', $text);

        return '<div class="plug-analysis-content" style="line-height:1.9; font-size:14px;">' . $text . '</div>';
    }
}
