<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap plug-monitor-wrap" dir="rtl">
    <h1>📚 راهنما و وضعیت سیستم</h1>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">

        <div class="postbox">
            <div class="inside" style="padding:20px;">
                <h2>🎯 این افزونه چیست؟</h2>
                <p>نسخه ایرانی و هوشمند Query Monitor با تحلیل AI فارسی:</p>
                <ul style="list-style:disc; margin-right:20px; line-height:1.8;">
                    <li><strong>مانیتورینگ کامل:</strong> زمان اجرا، حافظه، کوئری‌های دیتابیس (کند، تکراری، همه)، خطاهای PHP، درخواست‌های HTTP، هوک‌ها، قالب، دارایی‌ها، افزونه‌ها.</li>
                    <li><strong>گزارش‌گیری:</strong> ذخیره خودکار گزارش‌ها در دیتابیس با امکان فیلتر و خروجی JSON.</li>
                    <li><strong>تحلیل AI فارسی:</strong> ارسال خلاصه گزارش به OpenRouter (کلود، جی‌پی‌تی، جمینای، لاما) و دریافت تحلیل هوشمند به زبان فارسی با راهکار عملی.</li>
                    <li><strong>تنظیمات کامل:</strong> API Key، تست اتصال، دریافت مدل‌ها (۲۰۰+ مدل)، انتخاب مدل، Temperature، Max Tokens، پرامپت سیستمی قابل ویرایش.</li>
                    <li><strong>نوار ادمین:</strong> نمایش لحظه‌ای مثل Query Monitor در Toolbar.</li>
                </ul>

                <h3>🚀 شروع سریع</h3>
                <ol style="margin-right:20px; line-height:1.9;">
                    <li><code>wp-config.php</code> را باز کنید و <code>define('SAVEQUERIES', true);</code> اضافه کنید.</li>
                    <li>به <code>مانیتور هوشمند → تنظیمات</code> بروید.</li>
                    <li>کلید OpenRouter را وارد کنید (از openrouter.ai/keys).</li>
                    <li>تست اتصال بزنید، سپس دریافت مدل‌ها.</li>
                    <li>مدل را انتخاب کنید (پیشنهاد: gpt-4o-mini).</li>
                    <li>ذخیره و به <code>گزارش‌ها</code> بروید، یک گزارش جدید بگیرید و تحلیل کنید.</li>
                </ol>
            </div>
        </div>

        <div class="postbox">
            <div class="inside" style="padding:20px;">
                <h2>🖥 وضعیت سیستم</h2>
                <?php
                global $wpdb;
                $options = Plug_Monitor_Defaults::get_options();
                $checks = [
                    'PHP Version' => PHP_VERSION . ' ' . (version_compare(PHP_VERSION,'7.4','>=') ? '✅' : '❌ نیاز به ۷.۴+'),
                    'WP Version' => get_bloginfo('version'),
                    'SAVEQUERIES' => defined('SAVEQUERIES') && SAVEQUERIES ? '✅ فعال' : '⚠️ غیرفعال (جزئیات کوئری ندارید)',
                    'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? '✅ فعال' : 'ℹ️ غیرفعال',
                    'Memory Limit' => ini_get('memory_limit'),
                    'DB Version' => $wpdb->db_version(),
                    'Object Cache' => is_object($GLOBALS['wp_object_cache']) ? get_class($GLOBALS['wp_object_cache']) : 'ندارد',
                    'OpenRouter Key' => !empty($options['api_key']) ? '✅ تنظیم شده' : '❌ تنظیم نشده',
                    'Model' => $options['model'] ?: 'تنظیم نشده',
                    'Reports Count' => Plug_Monitor_Reporter::count_reports(),
                ];
                ?>
                <table class="widefat striped">
                    <?php foreach ($checks as $k=>$v): ?>
                        <tr><th><?php echo esc_html($k); ?></th><td><?php echo esc_html($v); ?></td></tr>
                    <?php endforeach; ?>
                </table>

                <h3 style="margin-top:20px;">🔌 تست‌های داخلی</h3>
                <button id="plug-help-test" class="button">تست OpenRouter + مانیتور</button>
                <div id="plug-help-result" style="margin-top:10px;"></div>

                <h3 style="margin-top:20px;">📦 افزونه‌های فعال سنگین</h3>
                <p style="font-size:12px; color:#666;">بر اساس تعداد فایل و حجم (تقریبی):</p>
                <ul style="font-size:12px;">
                    <?php
                    $plugins = get_option('active_plugins', []);
                    foreach ($plugins as $p) {
                        echo '<li><code>'.esc_html($p).'</code></li>';
                    }
                    ?>
                </ul>
            </div>
        </div>

        <div class="postbox">
            <div class="inside" style="padding:20px;">
                <h2>❓ سوالات متداول</h2>
                <details open style="margin-bottom:10px;"><summary><strong>تفاوت با Query Monitor چیست؟</strong></summary><p>امکانات مشابه QM دارد (کوئری، حافظه، هوک، HTTP) به علاوه تحلیل هوشمند فارسی با AI و ذخیره تاریخچه گزارش‌ها. QM تحلیل AI ندارد.</p></details>
                <details style="margin-bottom:10px;"><summary><strong>آیا نیاز به کلید OpenRouter پولی است؟</strong></summary><p>خیر، می‌توانید از مدل‌های :free رایگان استفاده کنید (محدودیت دارند). برای مصرف بالا، شارژ OpenRouter ارزان است.</p></details>
                <details style="margin-bottom:10px;"><summary><strong>SAVEQUERIES سرعت را کم می‌کند؟</strong></summary><p>بله، کمی. فقط در محیط توسعه یا موقتا فعال کنید. در پروداکشن با احتیاط.</p></details>
                <details style="margin-bottom:10px;"><summary><strong>تحلیل AI دقیق است؟</strong></summary><p>بله، با پرامپت بهینه فارسی و ارسال خلاصه دقیق (کندترین کوئری‌ها، خطاها، حافظه) پیشنهادهای عملی می‌دهد. اما همیشه کد را قبل از اعمال تست کنید.</p></details>
                <details style="margin-bottom:10px;"><summary><strong>گزارش‌ها کجا ذخیره می‌شوند؟</strong></summary><p>در جدول <code><?php echo esc_html(Plug_AI_Monitor::get_table()); ?></code> و به صورت خودکار بعد از <?php echo $options['keep_reports_days']; ?> روز پاک می‌شوند.</p></details>
            </div>
        </div>

        <div class="postbox">
            <div class="inside" style="padding:20px;">
                <h2>🛠 عیب‌یابی سریع</h2>
                <ul style="line-height:1.9;">
                    <li><strong>مدل‌ها لود نمی‌شوند:</strong> مطمئن شوید هاست به openrouter.ai دسترسی دارد (فایروال/ping). از VPN سرور چک کنید.</li>
                    <li><strong>تست اتصال 401:</strong> کلید اشتباه یا منقضی است.</li>
                    <li><strong>تحلیل کند است:</strong> مدل سبک‌تر (gpt-4o-mini یا gemini-flash) انتخاب کنید و max_tokens را ۱۵۰۰ بگذارید.</li>
                    <li><strong>کوئری‌ها خالی:</strong> SAVEQUERIES را فعال کنید.</li>
                    <li><strong>نوار ادمین نمایش ندارد:</strong> در تنظیمات تیک Toolbar و Frontend را بزنید.</li>
                </ul>
                <h3>📞 پشتیبانی</h3>
                <p>گیت‌هاب: <a href="https://github.com/omidmostafadonyayezaban-eng/plug" target="_blank">omidmostafadonyayezaban-eng/plug</a></p>
                <p>ایمیل: از طریق Issues گیت‌هاب</p>

                <h3>🔐 حریم خصوصی</h3>
                <p style="font-size:12px;">گزارش‌های شما فقط وقتی روی «تحلیل» کلیک کنید به OpenRouter ارسال می‌شوند. خلاصه فشرده ارسال می‌شود، نه کل دیتابیس. کلید API فقط در دیتابیس وردپرس ذخیره می‌شود، نه جای دیگر.</p>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(function($){
    $('#plug-help-test').on('click', function(){
        var btn=$(this); btn.prop('disabled',true).text('در حال تست...');
        $.post(PlugMonitor.ajax_url, {action:'plug_get_live_stats', nonce:PlugMonitor.nonce}, function(res){
            btn.prop('disabled',false).text('تست OpenRouter + مانیتور');
            if(res.success){
                $('#plug-help-result').html('<div style="background:#d4edda; padding:10px; border-radius:6px;">✅ مانیتور فعال است<br>زمان: '+res.data.summary.time+'s | کوئری: '+res.data.summary.queries+' | حافظه: '+res.data.summary.memory+'MB</div>');
            } else {
                $('#plug-help-result').html('<div style="background:#f8d7da; padding:10px;">❌ '+res.data.message+'</div>');
            }
        });
    });
});
</script>
