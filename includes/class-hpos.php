<?php
if (!defined('ABSPATH')) exit;

/**
 * HPOS & WooCommerce Compatibility Handler
 * استاندارد سازی برای ووکامرس HPOS و Checkout Blocks
 * 
 * @since 1.1.0
 * @package Plug_Monitor
 */

final class Plug_Monitor_HPOS {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // اعلام سازگاری HPOS در قبل از init ووکامرس
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

        // فقط در صورت فعال بودن ووکامرس
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    /**
     * اعلام سازگاری HPOS - استاندارد رسمی ووکامرس
     */
    public function declare_hpos_compatibility() {
        if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            return;
        }

        // HPOS: ذخیره سفارشات در جدول اختصاصی (بدون وابستگی به wp_posts)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            PLUG_MONITOR_FILE,
            true
        );

        // Cart & Checkout Blocks (بلوک‌های جدید ووکامرس)
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            PLUG_MONITOR_FILE,
            true
        );

        // Checkout Blocks جداگانه برای سازگاری قدیم
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'checkout_blocks',
            PLUG_MONITOR_FILE,
            true
        );

        // Product Block Editor (ویرایشگر جدید محصول)
        if (method_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class, 'declare_compatibility')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'product_block_editor',
                PLUG_MONITOR_FILE,
                true
            );
        }
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // فیلترها برای بهینه‌سازی - هیچ کوئری مستقیم به wp_posts برای سفارشات نمی‌زنیم
        // مانیتور ما فقط جدول خودش را استفاده می‌کند، پس HPOS سازگار است

        // اضافه کردن اطلاعات HPOS در گزارش محیط
        add_filter('plug_monitor_environment_data', [$this, 'add_hpos_env_data']);

        // جلوگیری از لاگ کردن کوئری‌های HPOS سنگین در صورت تنظیم
        add_action('woocommerce_before_order_object_save', [$this, 'maybe_skip_order_logging'], 10, 2);
    }

    public function add_hpos_env_data($env) {
        if (!class_exists('WooCommerce')) {
            return $env;
        }

        $env['hpos_enabled'] = false;
        $env['hpos_sync_enabled'] = false;

        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            $env['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            // بررسی سینک
            if (method_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class, 'is_hpos_sync_enabled')) {
                $env['hpos_sync_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::is_hpos_sync_enabled();
            }
        }

        $env['wc_version'] = defined('WC_VERSION') ? WC_VERSION : 'unknown';
        $env['cart_checkout_blocks_compatible'] = 'yes';

        return $env;
    }

    public function maybe_skip_order_logging($order, $data_store) {
        // اگر در حال ذخیره سفارش هستیم و لاگ کوئری‌ها روشن است، برای جلوگیری از حلقه، موقتاً لاگ را متوقف نکن
        // فقط یک فلگ برای کالکتور
        $GLOBALS['plug_monitor_skip_hpos_query'] = false;
    }

    /**
     * بررسی فعال بودن HPOS
     */
    public static function is_hpos_enabled() {
        if (!class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return false;
        }
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}
