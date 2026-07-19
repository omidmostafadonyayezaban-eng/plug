<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_Defaults {
    const OPTION_KEY = 'plug_monitor_options';

    public static function get_default_options() {
        return [
            // OpenRouter
            'api_key' => '',
            'model' => 'openai/gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'system_prompt' => "تو یک متخصص ارشد بهینه‌سازی وردپرس، PHP و MySQL هستی. وظیفه تو تحلیل گزارش عملکرد سایت وردپرسی است.\n\nقواعد پاسخ‌دهی:\n- فقط فارسی روان و تخصصی بنویس.\n- مشکلات را به ترتیب اولویت (بحرانی، متوسط، کم) دسته‌بندی کن.\n- برای هر مشکل: علت احتمالی، تاثیر روی سرعت/سئو/امنیت، و راهکار دقیق کدی یا تنظیمی ارائه بده.\n- اگر کوئری کند وجود دارد، نسخه بهینه‌شده کوئری یا پیشنهاد ایندکس را بده.\n- اگر مصرف حافظه بالاست، افزونه‌ها یا کدهای مشکوک را مشخص کن.\n- در پایان یک چک‌لیست عملیاتی ۵ مرحله‌ای برای بهبود فوری بده.\n- از ایموجی استفاده نکن، از مارک‌داون تمیز استفاده کن.",
            'custom_http_referer' => home_url(),
            'custom_x_title' => 'Plug Monitor',

            // Monitoring
            'slow_query_threshold' => 0.05,
            'slow_http_threshold' => 0.8,
            'collect_hooks' => false,
            'collect_http' => true,
            'collect_php_errors' => true,
            'collect_env' => true,
            'collect_assets' => true,
            'collect_template' => true,
            'admin_bar' => true,
            'admin_bar_frontend' => true,
            'auto_log_admin' => true,
            'auto_log_frontend_admin_users' => true,
            'enable_query_log' => true,
            'duplicate_query_detection' => true,
            'max_reports' => 200,
            'keep_reports_days' => 14,
            'enable_for_roles' => ['administrator'],

            // UI
            'highlight_slow' => true,
            'auto_analyze' => false,
        ];
    }

    public static function get_options() {
        $opts = get_option(self::OPTION_KEY, []);
        $defaults = self::get_default_options();
        return wp_parse_args($opts, $defaults);
    }

    public static function get_option($key, $default = null) {
        $opts = self::get_options();
        if (isset($opts[$key])) return $opts[$key];
        return $default;
    }

    public static function update_options($new_options) {
        $current = self::get_options();
        $merged = wp_parse_args($new_options, $current);
        return update_option(self::OPTION_KEY, $merged, false);
    }

    public static function maybe_install() {
        $existing = get_option(self::OPTION_KEY, null);
        if (null === $existing) {
            add_option(self::OPTION_KEY, self::get_default_options(), '', false);
        }
        // ایجاد جدول هم اینجا اگر لازم شد
        $instance = Plug_AI_Monitor::instance();
        if (method_exists($instance, 'create_table')) {
            $instance->create_table();
        }
    }

    public static function get_models_transient_key() {
        return 'plug_monitor_openrouter_models';
    }
}
