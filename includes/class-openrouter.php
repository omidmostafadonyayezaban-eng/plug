<?php
if (!defined('ABSPATH')) exit;

class Plug_Monitor_OpenRouter {

    const API_BASE = 'https://openrouter.ai/api/v1';
    private $api_key;
    private $options;

    public function __construct($api_key = null) {
        $this->options = Plug_Monitor_Defaults::get_options();
        $this->api_key = $api_key ?? $this->options['api_key'] ?? '';
    }

    private function get_headers() {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        ];
        $referer = $this->options['custom_http_referer'] ?? home_url();
        if ($referer) $headers['HTTP-Referer'] = $referer;
        $title = $this->options['custom_x_title'] ?? 'Plug Monitor';
        if ($title) $headers['X-Title'] = $title;
        return $headers;
    }

    /**
     * دریافت لیست مدل‌ها
     */
    public function fetch_models($force_refresh = false) {
        $transient_key = Plug_Monitor_Defaults::get_models_transient_key();

        if (!$force_refresh) {
            $cached = get_transient($transient_key);
            if ($cached !== false) return $cached;
        }

        if (empty($this->api_key)) {
            // بدون کلید هم می‌توان مدل‌ها را گرفت (public endpoint)
            $response = wp_remote_get(self::API_BASE . '/models', [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);
        } else {
            $response = wp_remote_get(self::API_BASE . '/models', [
                'timeout' => 20,
                'headers' => $this->get_headers(),
            ]);
        }

        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', 'خطا در دریافت مدل‌ها: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? 'کد پاسخ: ' . $code;
            return new WP_Error('api_error', 'خطای API اوپن‌روتر: ' . $msg);
        }

        // ساختار: { data: [ {id, name, pricing, context_length, ...} ] }
        $models = [];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $model) {
                $models[] = [
                    'id' => $model['id'] ?? '',
                    'name' => $model['name'] ?? $model['id'] ?? '',
                    'context_length' => $model['context_length'] ?? 0,
                    'pricing_prompt' => $model['pricing']['prompt'] ?? '',
                    'pricing_completion' => $model['pricing']['completion'] ?? '',
                    'description' => $model['description'] ?? '',
                    'top_provider' => $model['top_provider'] ?? [],
                ];
            }
            // مرتب‌سازی: مدل‌های محبوب و رایگان اول
            usort($models, function($a,$b){
                // gpt, claude, gemini اول
                $score = function($id){
                    $id = strtolower($id);
                    if (strpos($id, 'gpt-4o-mini') !== false) return 0;
                    if (strpos($id, 'gpt-4o') !== false) return 1;
                    if (strpos($id, 'claude') !== false) return 2;
                    if (strpos($id, 'gemini') !== false) return 3;
                    if (strpos($id, 'llama') !== false) return 4;
                    if (strpos($id, ':free') !== false) return 5;
                    return 10;
                };
                return $score($a['id']) <=> $score($b['id']);
            });
        }

        set_transient($transient_key, $models, HOUR_IN_SECONDS * 6);
        return $models;
    }

    /**
     * تست اتصال به API
     */
    public function test_connection($model = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_key', 'کلید API وارد نشده است.');
        }

        $options = $this->options;
        $test_model = $model ?: ($options['model'] ?? 'openai/gpt-4o-mini');

        // یک درخواست ساده chat completion
        $messages = [
            ['role' => 'user', 'content' => 'سلام! این یک تست اتصال است. فقط بگو "اتصال موفق" به فارسی.']
        ];

        $result = $this->chat_completion($messages, $test_model, [
            'max_tokens' => 20,
            'temperature' => 0.3,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'model' => $test_model,
            'response' => $result['choices'][0]['message']['content'] ?? 'پاسخی دریافت شد',
            'usage' => $result['usage'] ?? [],
        ];
    }

    /**
     * ارسال درخواست چت
     */
    public function chat_completion($messages, $model = null, $overrides = []) {
        if (empty($this->api_key)) {
            return new WP_Error('no_key', 'کلید API تنظیم نشده است. لطفا از تنظیمات، کلید OpenRouter را وارد کنید.');
        }

        $options = $this->options;
        $model = $model ?: ($options['model'] ?? 'openai/gpt-4o-mini');

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => isset($overrides['temperature']) ? floatval($overrides['temperature']) : floatval($options['temperature'] ?? 0.7),
            'max_tokens' => isset($overrides['max_tokens']) ? intval($overrides['max_tokens']) : intval($options['max_tokens'] ?? 2000),
        ];

        // اگر response_format لازم بود
        if (isset($overrides['response_format'])) {
            $payload['response_format'] = $overrides['response_format'];
        }

        $response = wp_remote_post(self::API_BASE . '/chat/completions', [
            'timeout' => 60,
            'headers' => $this->get_headers(),
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('http_failed', 'خطا در ارتباط HTTP: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $err_msg = $data['error']['message'] ?? $body;
            // ترجمه برخی خطاهای رایج
            $friendly = $this->translate_error($code, $err_msg);
            return new WP_Error('api_error', $friendly, ['code' => $code, 'body' => $body]);
        }

        return $data;
    }

    /**
     * تحلیل گزارش با AI
     */
    public function analyze_report($report_summary_for_ai, $model = null, $system_prompt = null) {
        $options = $this->options;
        $model = $model ?: ($options['model'] ?? 'openai/gpt-4o-mini');
        $system_prompt = $system_prompt ?: ($options['system_prompt'] ?? Plug_Monitor_Defaults::get_default_options()['system_prompt']);

        $user_prompt = $this->build_user_prompt($report_summary_for_ai);

        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt],
        ];

        $result = $this->chat_completion($messages, $model, [
            'max_tokens' => intval($options['max_tokens'] ?? 3000),
            'temperature' => floatval($options['temperature'] ?? 0.7),
        ]);

        return $result;
    }

    private function build_user_prompt($summary) {
        // خلاصه داده‌ها را به فرمت خوانا برای AI تبدیل کن
        // $summary انتظار می‌رود آرایه گزارش کامل یا فشرده باشد

        $prompt = "لطفا گزارش عملکرد زیر را تحلیل کن و پاسخ فارسی بده:\n\n";
        $prompt .= "```json\n" . wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n```\n\n";
        $prompt .= "نیازمندی‌ها:\n";
        $prompt .= "- تحلیل را به فارسی تخصصی و قابل فهم بنویس.\n";
        $prompt .= "- مشکلات را اولویت‌بندی کن (بحرانی/متوسط/کم).\n";
        $prompt .= "- راهکارهای عملی، کدهای اصلاحی و افزونه‌های جایگزین پیشنهاد بده.\n";
        $prompt .= "- اگر کوئری کند هست، تحلیل کن و ایندکس پیشنهادی بده.\n";
        $prompt .= "- در پایان چک‌لیست ۵ مرحله‌ای بهبود فوری را بگذار.\n";

        return $prompt;
    }

    private function translate_error($code, $message) {
        $msg = strtolower($message);
        if ($code === 401) return "کلید API نامعتبر است (401). لطفا کلید را بررسی کنید. پیام: $message";
        if ($code === 402) return "اعتبار حساب OpenRouter کافی نیست (402). لطفا حساب خود را شارژ کنید. پیام: $message";
        if ($code === 403) return "دسترسی ممنوع (403): $message";
        if ($code === 429) return "محدودیت نرخ درخواست (429). کمی صبر کنید و دوباره تلاش کنید. پیام: $message";
        if ($code === 404 && strpos($msg, 'model') !== false) return "مدل یافت نشد (404). لطفا مدل دیگری انتخاب کنید. پیام: $message";
        return "خطای OpenRouter ($code): $message";
    }

    /**
     * گرفتن اطلاعات اعتبار و محدودیت
     */
    public function get_key_info() {
        if (empty($this->api_key)) return new WP_Error('no_key', 'کلید وارد نشده');
        $response = wp_remote_get(self::API_BASE . '/key', [
            'timeout' => 15,
            'headers' => $this->get_headers(),
        ]);
        if (is_wp_error($response)) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($code !== 200) {
            return new WP_Error('api_error', 'خطا در دریافت اطلاعات کلید: ' . ($data['error']['message'] ?? $body));
        }
        return $data;
    }
}
