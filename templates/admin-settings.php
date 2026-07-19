<?php
if (!defined('ABSPATH')) exit;

$options = Plug_Monitor_Defaults::get_options();
$models = is_wp_error($models) ? [] : $models;
$has_models_error = is_wp_error($models) || empty($models);
?>
<div class="wrap plug-monitor-wrap" dir="rtl">
    <h1>⚙️ تنظیمات مانیتور هوشمند</h1>

    <div style="display:grid; grid-template-columns: 1fr 350px; gap:20px; margin-top:20px;">
        <div>
            <form method="post" action="options.php" id="plug-settings-form">
                <?php settings_fields('plug_monitor_settings_group'); ?>
                <?php $opts = get_option(Plug_Monitor_Defaults::OPTION_KEY, []); ?>

                <!-- OpenRouter Settings -->
                <div class="postbox">
                    <div class="inside" style="padding:20px;">
                        <h2 style="margin-top:0;">🔑 تنظیمات OpenRouter API</h2>
                        <p>برای تحلیل هوشمند، از <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a> کلید API تهیه کنید (رایگان و پولی دارد).</p>

                        <table class="form-table">
                            <tr>
                                <th><label for="api_key">کلید API</label></th>
                                <td>
                                    <input type="password" id="api_key" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[api_key]" value="<?php echo esc_attr($options['api_key']); ?>" class="regular-text" style="width:400px;" placeholder="sk-or-v1-...">
                                    <button type="button" id="btn-toggle-key" class="button">👁 نمایش</button>
                                    <p class="description">کلید را از openrouter.ai/keys بگیرید. به صورت امن ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>ارتباط</th>
                                <td>
                                    <button type="button" id="plug-test-connection-btn" class="button button-primary">🔌 تست اتصال</button>
                                    <button type="button" id="plug-fetch-models-btn" class="button">🔄 دریافت لیست مدل‌ها</button>
                                    <span id="plug-connection-status" style="margin-right:10px;"></span>
                                    <div id="plug-test-result-box" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="model">مدل هوش مصنوعی</label></th>
                                <td>
                                    <select id="model" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[model]" style="width:400px;">
                                        <?php
                                        $selected = $options['model'];
                                        $popular = [
                                            'openai/gpt-4o-mini' => 'GPT-4o Mini (سریع و ارزان - پیشنهادی)',
                                            'openai/gpt-4o' => 'GPT-4o (قدرتمند)',
                                            'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (تحلیل عالی)',
                                            'google/gemini-flash-1.5' => 'Gemini Flash 1.5 (سریع)',
                                            'meta-llama/llama-3.1-8b-instruct:free' => 'Llama 3.1 8B Free (رایگان)',
                                            'google/gemma-2-9b-it:free' => 'Gemma 2 9B Free (رایگان)',
                                        ];
                                        foreach ($popular as $id => $label) {
                                            echo '<option value="'.esc_attr($id).'" '.selected($selected, $id, false).'>'.esc_html($label).'</option>';
                                        }
                                        // مدل‌های داینامیک
                                        if (!empty($models)) {
                                            echo '<optgroup label="--- همه مدل‌ها (از API) ---">';
                                            foreach ($models as $m) {
                                                $mid = $m['id'];
                                                if (isset($popular[$mid])) continue;
                                                $name = $m['name'] ?: $mid;
                                                echo '<option value="'.esc_attr($mid).'" '.selected($selected, $mid, false).'>'.esc_html($name.' ('.$mid.')').'</option>';
                                            }
                                            echo '</optgroup>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description">
                                        پیشنهاد: <code>openai/gpt-4o-mini</code> برای سرعت و هزینه کم، یا مدل‌های <code>:free</code> برای تست رایگان.
                                        <br><a href="#" id="plug-refresh-models-link">بارگذاری مجدد لیست مدل‌ها از API</a>
                                    </p>
                                    <div id="plug-models-info" style="margin-top:5px; font-size:12px; color:#666;">
                                        <?php if (!empty($models)): ?>تعداد مدل‌های بارگذاری شده: <?php echo count($models); ?> | آخرین بروزرسانی: <?php echo human_time_diff(get_option('_transient_timeout_'.Plug_Monitor_Defaults::get_models_transient_key()) - 6*HOUR_IN_SECONDS); ?> پیش<?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="temperature">Temperature</label></th>
                                <td>
                                    <input type="range" id="temperature" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[temperature]" min="0" max="2" step="0.1" value="<?php echo esc_attr($options['temperature']); ?>" style="width:200px;">
                                    <span id="temp-value"><?php echo $options['temperature']; ?></span>
                                    <p class="description">۰ = دقیق و ثابت، ۱ = خلاق، ۲ = خیلی خلاق (۰.۷ پیشنهادی)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="max_tokens">Max Tokens</label></th>
                                <td>
                                    <input type="number" id="max_tokens" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[max_tokens]" value="<?php echo esc_attr($options['max_tokens']); ?>" min="100" max="8000" step="100" style="width:120px;">
                                    <p class="description">حداکثر طول پاسخ AI (۲۰۰۰ پیشنهادی)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="system_prompt">پرامپت سیستمی (فارسی)</label></th>
                                <td>
                                    <textarea id="system_prompt" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[system_prompt]" rows="10" style="width:100%; max-width:600px; direction:rtl; text-align:right; font-family:Tahoma;"><?php echo esc_textarea($options['system_prompt']); ?></textarea>
                                    <p class="description">این متن به AI می‌گوید چگونه تحلیل کند. پیش‌فرض برای پاسخ فارسی بهینه است.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="custom_http_referer">HTTP-Referer</label></th>
                                <td>
                                    <input type="url" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[custom_http_referer]" value="<?php echo esc_attr($options['custom_http_referer']); ?>" class="regular-text" style="width:400px;">
                                    <p class="description">برای OpenRouter اختیاری است، بهتر است دامنه سایت باشد.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="custom_x_title">X-Title</label></th>
                                <td>
                                    <input type="text" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[custom_x_title]" value="<?php echo esc_attr($options['custom_x_title']); ?>" class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Monitoring Settings -->
                <div class="postbox" style="margin-top:20px;">
                    <div class="inside" style="padding:20px;">
                        <h2 style="margin-top:0;">📊 تنظیمات مانیتورینگ (مثل Query Monitor)</h2>

                        <table class="form-table">
                            <tr>
                                <th>آستانه‌ها</th>
                                <td>
                                    <label>کوئری کند (ثانیه): <input type="number" step="0.01" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[slow_query_threshold]" value="<?php echo esc_attr($options['slow_query_threshold']); ?>" style="width:80px;"></label><br>
                                    <label>HTTP کند (ثانیه): <input type="number" step="0.1" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[slow_http_threshold]" value="<?php echo esc_attr($options['slow_http_threshold']); ?>" style="width:80px;"></label>
                                </td>
                            </tr>
                            <tr>
                                <th>جمع‌آوری داده</th>
                                <td>
                                    <?php
                                    $checks = [
                                        'collect_php_errors' => 'ردیابی خطاهای PHP (Warning/Notice/Fatal)',
                                        'collect_http' => 'ردیابی درخواست‌های HTTP API',
                                        'collect_assets' => 'اسکریپت‌ها و استایل‌های لود شده',
                                        'collect_template' => 'فایل قالب و سلسله مراتب',
                                        'collect_env' => 'اطلاعات محیط (PHP/WP/MySQL)',
                                        'enable_query_log' => 'ذخیره متن کامل کوئری‌ها (نیاز به SAVEQUERIES)',
                                        'duplicate_query_detection' => 'تشخیص کوئری‌های تکراری',
                                        'collect_hooks' => 'ردیابی هوک‌ها (ممکن است سنگین باشد - فقط ۵۰۰ اول)',
                                    ];
                                    foreach ($checks as $key => $label) {
                                        $checked = !empty($options[$key]) ? 'checked' : '';
                                        echo '<label style="display:block; margin-bottom:6px;"><input type="checkbox" name="'.Plug_Monitor_Defaults::OPTION_KEY.'['.$key.']" value="1" '.$checked.'> '.$label.'</label>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>نمایش</th>
                                <td>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[admin_bar]" value="1" <?php checked($options['admin_bar'], true); ?>> نمایش در نوار ادمین (Toolbar)</label><br>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[admin_bar_frontend]" value="1" <?php checked($options['admin_bar_frontend'], true); ?>> نمایش در فرانت‌اند برای ادمین‌ها</label><br>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[highlight_slow]" value="1" <?php checked($options['highlight_slow'], true); ?>> هایلایت موارد کند</label>
                                </td>
                            </tr>
                            <tr>
                                <th>ذخیره خودکار</th>
                                <td>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[auto_log_admin]" value="1" <?php checked($options['auto_log_admin'], true); ?>> ذخیره خودکار گزارش در پیشخوان ادمین</label><br>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[auto_log_frontend_admin_users]" value="1" <?php checked($options['auto_log_frontend_admin_users'], true); ?>> ذخیره خودکار در فرانت‌اند وقتی ادمین لاگین است</label><br>
                                    <label><input type="checkbox" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[auto_analyze]" value="1" <?php checked($options['auto_analyze'], true); ?>> تحلیل خودکار با AI بعد از هر گزارش جدید</label>
                                </td>
                            </tr>
                            <tr>
                                <th>مدیریت گزارش‌ها</th>
                                <td>
                                    <label>حداکثر تعداد گزارش: <input type="number" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[max_reports]" value="<?php echo esc_attr($options['max_reports']); ?>" style="width:80px;"></label><br>
                                    <label>حذف خودکار بعد از (روز): <input type="number" name="<?php echo Plug_Monitor_Defaults::OPTION_KEY; ?>[keep_reports_days]" value="<?php echo esc_attr($options['keep_reports_days']); ?>" style="width:80px;"></label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button('💾 ذخیره تنظیمات'); ?>
            </form>
        </div>

        <!-- Sidebar help -->
        <div>
            <div class="postbox">
                <div class="inside" style="padding:15px;">
                    <h3>📚 راهنمای سریع OpenRouter</h3>
                    <ol style="margin-right:15px;">
                        <li>به <a href="https://openrouter.ai/" target="_blank">openrouter.ai</a> بروید و ثبت‌نام کنید.</li>
                        <li>از بخش <a href="https://openrouter.ai/keys" target="_blank">Keys</a> یک کلید جدید بسازید.</li>
                        <li>کلید را اینجا وارد کنید.</li>
                        <li>دکمه تست اتصال را بزنید.</li>
                        <li>لیست مدل‌ها را دریافت کنید و مدل دلخواه را انتخاب کنید.</li>
                        <li>ذخیره کنید و به گزارش‌ها بروید.</li>
                    </ol>
                    <p><strong>مدل‌های پیشنهادی:</strong></p>
                    <ul style="list-style:disc; margin-right:15px; font-size:13px;">
                        <li><code>openai/gpt-4o-mini</code>: سریع، ارزان، کیفیت خوب فارسی</li>
                        <li><code>anthropic/claude-3.5-sonnet</code>: تحلیل عمیق‌تر (گران‌تر)</li>
                        <li><code>meta-llama/llama-3.1-8b-instruct:free</code>: رایگان برای تست</li>
                    </ul>
                    <hr>
                    <h4>🔧 عیب‌یابی</h4>
                    <ul style="font-size:13px;">
                        <li>خطای 401: کلید اشتباه است.</li>
                        <li>خطای 402: اعتبار تمام شده.</li>
                        <li>خطای 429: درخواست زیاد، کمی صبر کنید.</li>
                        <li>اگر مدل‌ها لود نشد، «دریافت لیست» را با Force بزنید.</li>
                    </ul>
                    <button id="plug-clear-cache" class="button">🧹 پاک کردن کش مدل‌ها</button>
                </div>
            </div>

            <div class="postbox" style="margin-top:20px;">
                <div class="inside" style="padding:15px;">
                    <h3>⚡ فعال‌سازی کامل QM</h3>
                    <p>برای امکانات کامل مشابه Query Monitor:</p>
                    <pre style="background:#f6f8fa; padding:10px; direction:ltr; text-align:left; overflow:auto;">define('SAVEQUERIES', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
                    <p style="font-size:12px;">این خطوط را به <code>wp-config.php</code> اضافه کنید.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($){
    $('#btn-toggle-key').on('click', function(){
        var $input = $('#api_key');
        $input.attr('type', $input.attr('type')==='password' ? 'text' : 'password');
    });
    $('#temperature').on('input', function(){ $('#temp-value').text($(this).val()); });

    $('#plug-test-connection-btn').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true).text('در حال تست...');
        $('#plug-connection-status').text('');
        $.post(PlugMonitor.ajax_url, {
            action: 'plug_test_connection',
            nonce: PlugMonitor.nonce,
            api_key: $('#api_key').val(),
            model: $('#model').val()
        }, function(res){
            btn.prop('disabled', false).text('🔌 تست اتصال');
            if(res.success){
                $('#plug-connection-status').html('<span style="color:green;">✅ موفق</span>');
                $('#plug-test-result-box').html('<div style="background:#d4edda; padding:10px; border-radius:6px;">✅ اتصال موفق<br>مدل: '+res.data.model+'<br>پاسخ: '+res.data.response+'</div>');
            } else {
                $('#plug-connection-status').html('<span style="color:red;">❌ خطا</span>');
                $('#plug-test-result-box').html('<div style="background:#f8d7da; padding:10px; border-radius:6px;">❌ '+res.data.message+'</div>');
            }
        }).fail(function(xhr){
            btn.prop('disabled', false).text('🔌 تست اتصال');
            $('#plug-test-result-box').html('<div style="background:#f8d7da; padding:10px;">خطای ارتباط</div>');
        });
    });

    $('#plug-fetch-models-btn, #plug-refresh-models-link').on('click', function(e){
        e.preventDefault();
        var btn = $(this);
        btn.prop('disabled', true).text('در حال دریافت...');
        $.post(PlugMonitor.ajax_url, {
            action: 'plug_fetch_models',
            nonce: PlugMonitor.nonce,
            force: 1
        }, function(res){
            btn.prop('disabled', false).text('🔄 دریافت لیست مدل‌ها');
            if(res.success){
                var models = res.data.models;
                var $select = $('#model');
                var current = $select.val();
                $select.find('optgroup').remove();
                var $group = $('<optgroup label="--- همه مدل‌ها (از API) '+models.length+' عدد ---"></optgroup>');
                models.forEach(function(m){
                    // اگر قبلا در لیست محبوب هست نادیده بگیر
                    if($select.find('option[value="'+m.id+'"]').length===0){
                        $group.append('<option value="'+m.id+'">'+m.name+' ('+m.id+')</option>');
                    }
                });
                $select.append($group);
                $select.val(current);
                $('#plug-models-info').text('تعداد مدل‌ها: '+models.length+' | بروز شد');
                alert('✅ '+models.length+' مدل دریافت شد.');
            } else {
                alert('❌ '+res.data.message);
            }
        });
    });

    $('#plug-clear-cache').on('click', function(){
        $.post(PlugMonitor.ajax_url, {action:'plug_clear_cache', nonce:PlugMonitor.nonce}, function(){
            alert('کش پاک شد');
            location.reload();
        });
    });
});
</script>
