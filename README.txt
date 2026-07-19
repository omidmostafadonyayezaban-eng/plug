=== مانیتور هوشمند وردپرس - Plug AI Performance Monitor ===
Contributors: plugteam
Tags: performance, query monitor, openrouter, ai, debug, optimization, فارسی, hpos, woocommerce
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.0
WC requires at least: 8.0
WC tested up to: 9.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

افزونه‌ای کامل مشابه Query Monitor با گزارش‌گیری حرفه‌ای و تحلیل هوشمند فارسی توسط OpenRouter AI - سازگار با آخرین وردپرس 6.8 و HPOS ووکامرس.

== Description ==

**مانیتور هوشمند وردپرس v1.1.0** نسخه استاندارد، بهینه برای وردپرس 6.8 و سازگار 100% با HPOS ووکامرس.

### 🟢 سازگار با آخرین استانداردها (v1.1.0 جدید)
- ✅ **WordPress 6.2 تا 6.8**: تست شده با آخرین هسته، استفاده از HOOKهای جدید، REST API
- ✅ **PHP 8.0+**: سازگار با PHP 8.0/8.1/8.2/8.3، تایپ‌هینت‌ها و بهینه‌سازی
- ✅ **WooCommerce HPOS**: اعلام رسمی سازگاری با `custom_order_tables` + `cart_checkout_blocks` + `product_block_editor` از طریق `before_woocommerce_init`
- ✅ **High-Performance Order Storage**: هیچ کوئری مستقیم به `wp_posts` برای سفارشات نزده، فقط جدول خودش `wp_plug_monitor_reports` را استفاده می‌کند
- ✅ **Checkout Blocks**: سازگار با بلوک‌های جدید تسویه حساب (Cart/Checkout Block)

### امکانات مشابه Query Monitor:
- ⏱ زمان اجرای صفحه (Execution Time)
- 🧠 حافظه مصرفی (Peak Memory, Usage)
- 🗄 کوئری‌های دیتابیس: تعداد، زمان کل، کندترین‌ها، تکراری‌ها، Caller
- ❌ خطاهای PHP: Warning, Notice, Fatal با فایل و خط
- 🌐 درخواست‌های HTTP API: URL, مدت زمان، وضعیت، خطاها، کندها
- 🧩 هوک‌ها: تعداد و لیست هوک‌های اجرا شده (قابل فعال‌سازی)
- 📦 اسکریپت‌ها و استایل‌ها: لیست Enqueue شده
- 🖼 قالب: فایل قالب فعلی
- ⚙️ محیط: PHP, WP, MySQL, Memory Limit, Object Cache + **اطلاعات HPOS**
- 📊 نوار ادمین: نمایش زنده مثل QM در Toolbar
- 📋 تاریخچه گزارش‌ها: ذخیره در دیتابیس با قابلیت مدیریت و خروجی JSON
- 🔌 REST API: `GET /wp-json/plug-monitor/v1/reports`

### تحلیل هوشمند با OpenRouter (فارسی):
- 🔑 تنظیم API Key اوپن‌روتر
- 🔌 تست اتصال مستقیم
- 📚 دریافت لیست 200+ مدل (GPT-4o, Claude 3.5, Gemini, Llama رایگان)
- 🤖 انتخاب مدل دلخواه
- ⚙️ تنظیم Temperature, Max Tokens, System Prompt فارسی
- 📄 تحلیل گزارش با یک کلیک و پاسخ فارسی کامل

### پیاده‌سازی HPOS چگونه است؟
در فایل اصلی `plug.php`:
```
add_action('before_woocommerce_init', function() {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
});
```
و کلاس اختصاصی `class-hpos.php` برای چک کردن وضعیت HPOS و افزودن به گزارش محیط.

هیچ کوئری مستقیمی به `wp_posts` برای سفارشات نداریم، پس کاملاً سازگار است.

== Installation ==

1. فایل zip را در `/wp-content/plugins/` آپلود و استخراج کنید یا از طریق پیشخوان نصب کنید.
2. افزونه را فعال کنید.
3. برای فعال‌سازی کامل، در `wp-config.php` اضافه کنید: `define('SAVEQUERIES', true);`
4. به `مانیتور هوشمند → تنظیمات` بروید، کلید OpenRouter را از https://openrouter.ai/keys بگیرید و وارد کنید.
5. تست اتصال بزنید، مدل‌ها را دریافت کنید، مدل را انتخاب و ذخیره کنید.
6. به گزارش‌ها بروید و اولین گزارش را بگیرید و تحلیل کنید.

**برای ووکامرس HPOS:**
- ووکامرس 8.0+ نصب باشد
- به ووکامرس → تنظیمات → پیشرفته → ویژگی‌ها → فعال‌سازی HPOS
- این افزونه به صورت خودکار سازگار شناخته می‌شود (بدون هشدار ناسازگاری)

== Frequently Asked Questions ==

= آیا با HPOS سازگار است؟ =
بله، نسخه 1.1.0 به صورت کامل سازگار است. در صفحه افزونه‌ها، زیر نام افزونه، `سازگار با HPOS` نشان داده می‌شود.

= آیا نیاز به کلید OpenRouter پولی دارد؟ =
خیر، مدل‌های :free رایگان هستند.

= تفاوت با Query Monitor؟ =
امکانات مشابه + تاریخچه + تحلیل AI فارسی + سازگار با HPOS و WP 6.8 + REST API

== Screenshots ==

1. داشبورد گزارش‌ها با وضعیت زنده
2. تنظیمات کامل OpenRouter: API, تست, مدل‌ها
3. صفحه جزئیات گزارش + تحلیل فارسی AI
4. نوار ادمین شبیه QM
5. وضعیت HPOS در محیط
6. تب‌های کوئری، HTTP، خطا، محیط

== Changelog ==

= 1.1.0 (2025-07-19) - استاندارد WP 6.8 + HPOS =
- [NEW] سازگاری کامل با وردپرس 6.2 تا 6.8
- [NEW] سازگاری کامل با WooCommerce HPOS (custom_order_tables, cart_checkout_blocks, product_block_editor)
- [NEW] کلاس اختصاصی Plug_Monitor_HPOS
- [NEW] REST API: /wp-json/plug-monitor/v1/reports
- [NEW] فیلتر plug_monitor_environment_data و نمایش وضعیت HPOS در گزارش
- [UPDATE] Requires PHP 8.0+, Tested up to 6.8, WC 9.5
- [UPDATE] بهینه‌سازی کالکتور برای PHP 8.x
- [FIX] حذف فیلترهای غیر استاندارد

= 1.0.0 =
- انتشار اولیه با مانیتورینگ کامل QM + OpenRouter AI فارسی

== Upgrade Notice ==

= 1.1.0 =
اگر از ووکامرس استفاده می‌کنید، این نسخه سازگاری کامل HPOS دارد - حتما آپدیت کنید.
