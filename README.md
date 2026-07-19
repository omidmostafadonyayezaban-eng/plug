# مانیتور هوشمند وردپرس - Plug AI Performance Monitor v1.1.0
### استاندارد وردپرس 6.8 + سازگار 100% با HPOS ووکامرس

افزونه‌ای کامل مشابه **Query Monitor** با قابلیت گزارش‌گیری حرفه‌ای و تحلیل هوشمند فارسی توسط **OpenRouter AI** - نسخه 1.1.0 استاندارد آخرین وردپرس.

## 🆕 تغییرات نسخه 1.1.0 - استاندارد WP 6.8 + HPOS

- ✅ **WordPress 6.8 Ready**: Requires at least 6.2, Tested up to 6.8, PHP 8.0+، استفاده از هوک‌های جدید
- ✅ **WooCommerce HPOS کامل**: 
  ```php
  add_action('before_woocommerce_init', function() {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
  });
  ```
  - کلاس `class-hpos.php` اختصاصی
  - هیچ کوئری مستقیم به `wp_posts` برای سفارشات ندارد، فقط جدول خودش
  - در لیست افزونه‌ها برچسب **سازگار با HPOS** می‌خورد
- ✅ **REST API**: `GET /wp-json/plug-monitor/v1/reports` برای اپلیکیشن‌ها
- ✅ **محیط بهبود یافته**: نمایش وضعیت HPOS، نسخه ووکامرس، سینک در گزارش

## 🎯 ویژگی‌ها

### امکانات مشابه Query Monitor (کامل)
- ⏱ زمان اجرا، 🧠 Peak Memory، 🗄 کوئری‌ها (کند، تکراری)، ❌ PHP Errors، 🌐 HTTP API، 🧩 Hooks، 📦 Assets، 🖼 Template، ⚙️ Environment + HPOS، 🧩 Plugins لیست
- 📊 نوار ادمین Toolbar مثل QM
- 📋 تاریخچه در `wp_plug_monitor_reports`

### تحلیل هوشمند فارسی OpenRouter
- 🔑 تنظیم API Key + تست اتصال (chat/completions تستی)
- 📚 دریافت 200+ مدل از `https://openrouter.ai/api/v1/models` با کش 6 ساعته
- 🤖 انتخاب مدل، Temperature، Max Tokens، System Prompt فارسی قابل ویرایش
- 📄 تحلیل با یک کلیک فارسی: اولویت‌بندی، علت، راهکار کدی، چک‌لیست 5 مرحله‌ای

## 📁 ساختار

```
plug/
├─ plug.php (v1.1.0 - هدر جدید WP 6.8 / WC 9.5 + HPOS declare + REST)
├─ includes/
│  ├─ class-defaults.php
│  ├─ class-hpos.php [جدید] - HPOS Handler
│  ├─ class-collector.php [آپدیت] - filter environment + HPOS info
│  ├─ class-openrouter.php - fetch_models, test_connection, chat
│  ├─ class-analyzer.php - فشرده‌سازی برای AI
│  ├─ class-settings.php
│  ├─ class-ajax.php - fetch_models, test_connection, analyze
│  ├─ class-admin-bar.php
│  └─ class-reporter.php
├─ templates/ - داشبورد، ریپورت، تنظیمات، راهنما
├─ assets/
└─ uninstall.php
```

## 🚀 نصب

1. `wp-config.php`: `define('SAVEQUERIES', true);`
2. فعال‌سازی افزونه
3. **مانیتور هوشمند → تنظیمات**: کلید OpenRouter از https://openrouter.ai/keys
4. تست اتصال → دریافت مدل‌ها → انتخاب `openai/gpt-4o-mini` → ذخیره
5. گزارش‌ها → گزارش فوری → تحلیل AI

**HPOS چک:**
- ووکامرس 8.0+ → تنظیمات → پیشرفته → فعال‌سازی High-Performance Order Storage
- در افزونه‌ها، زیر نام افزونه باید `سازگار` باشد بدون هشدار

## 🔌 API OpenRouter

- Models: `GET /v1/models` Header: Bearer KEY
- Chat: `POST /v1/chat/completions` Body: `{model, messages:[{system prompt_fa},{user json}], temperature, max_tokens}`

## 📦 فایل ZIP

برای ساخت ZIP استاندارد وردپرس:

```bash
cd /path/to/wp-content/plugins
zip -r plug-ai-monitor-v1.1.0.zip plug/ -x "*.git*" -x "*node_modules*"
```

یا از همین ریپو Release بگیر.

## 📝 مجوز GPL v2+

سازگار با آخرین وردپرس فارسی ❤️ - HPOS Ready
