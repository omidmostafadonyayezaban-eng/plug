<?php
if (!defined('ABSPATH')) exit;

$report_id = intval($_GET['report_id'] ?? 0);
$is_live = ($report_id === 0 && isset($_GET['report_id']) && $_GET['report_id'] === 'live') || (!isset($_GET['report_id']));

if ($is_live) {
    $collector = Plug_Monitor_Collector::instance();
    $live_data = $GLOBALS['plug_monitor_collected'] ?? $collector->get_last_collected();
    if (!$live_data) {
        echo '<div class="wrap" dir="rtl"><h1>گزارش زنده</h1><p>داده زنده یافت نشد. یک بار صفحه را رفرش کنید یا <a href="'.admin_url('admin.php?page=plug-monitor').'">به لیست گزارش‌ها</a> بروید.</p></div>';
        return;
    }
    // ساخت یک شمای گزارش مجازی برای نمایش
    $row = [
        'id' => 'live',
        'created_at' => gmdate('Y-m-d H:i:s'),
        'url' => $live_data['request']['url'] ?? '',
        'exec_time' => $live_data['execution']['total_time'] ?? 0,
        'query_count' => $live_data['database']['query_count'] ?? 0,
        'query_time' => $live_data['database']['total_query_time'] ?? 0,
        'memory_peak' => $live_data['execution']['peak_memory'] ?? 0,
        'data' => wp_json_encode($live_data, JSON_UNESCAPED_UNICODE),
        'analysis' => null,
        'model' => '',
        'status' => 'live',
    ];
    $full_data = $live_data;
} else {
    $row = Plug_Monitor_Reporter::get_report($report_id);
    if (!$row) {
        echo '<div class="wrap" dir="rtl"><h1>گزارش یافت نشد</h1><p>گزارش با شناسه '.$report_id.' وجود ندارد.</p><a href="'.admin_url('admin.php?page=plug-monitor').'" class="button">بازگشت</a></div>';
        return;
    }
    $full_data = json_decode($row['data'], true);
}

$options = Plug_Monitor_Defaults::get_options();
?>
<div class="wrap plug-monitor-wrap" dir="rtl">
    <h1>📄 جزئیات گزارش #<?php echo esc_html($row['id']); ?> 
        <a href="<?php echo admin_url('admin.php?page=plug-monitor'); ?>" class="button">← بازگشت به لیست</a>
        <?php if (!$is_live): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=plug-monitor&plug_export=1&report_id='.$row['id']), 'plug_export_'.$row['id']); ?>" class="button">⬇️ خروجی JSON</a>
            <button class="button button-primary plug-analyze-btn" data-id="<?php echo $row['id']; ?>">🤖 تحلیل با AI (<?php echo esc_html($options['model']); ?>)</button>
        <?php endif; ?>
    </h1>

    <div style="display:grid; grid-template-columns: 1fr 400px; gap:20px; margin-top:20px;">
        <!-- Main Content -->
        <div>
            <!-- Summary Cards -->
            <div style="display:grid; grid-template-columns: repeat(4,1fr); gap:10px;">
                <div class="plug-card"><strong>⏱ زمان</strong><br><span class="plug-big"><?php echo round(floatval($row['exec_time']),4); ?>s</span></div>
                <div class="plug-card"><strong>🗄 کوئری</strong><br><span class="plug-big"><?php echo $row['query_count']; ?></span><br><small><?php echo round(floatval($row['query_time']),4); ?>s</small></div>
                <div class="plug-card"><strong>🧠 حافظه</strong><br><span class="plug-big"><?php echo round($row['memory_peak']/1024/1024,2); ?> MB</span></div>
                <div class="plug-card"><strong>🌐 HTTP</strong><br><span class="plug-big"><?php echo $full_data['http_requests']['count'] ?? 0; ?></span><br><small><?php echo round($full_data['http_requests']['total_time'] ?? 0,3); ?>s</small></div>
            </div>

            <!-- AI Analysis Box -->
            <div class="postbox" style="margin-top:20px; border-right:4px solid #2271b1;">
                <div class="inside" style="padding:20px;">
                    <h2 style="margin-top:0;">🤖 تحلیل هوشمند (فارسی)</h2>
                    <?php if (!empty($row['analysis'])): ?>
                        <div style="background:#f0f6fc; padding:15px; border-radius:8px; margin-bottom:10px;">
                            <small>مدل: <code><?php echo esc_html($row['model']); ?></code> | زمان تحلیل: <?php echo esc_html($row['created_at']); ?></small>
                        </div>
                        <div class="plug-analysis-render">
                            <?php echo Plug_Monitor_Reporter::render_analysis_html($row['analysis']); ?>
                        </div>
                        <hr>
                        <button class="button plug-analyze-btn" data-id="<?php echo $row['id']; ?>">🔄 تحلیل مجدد</button>
                    <?php else: ?>
                        <div id="plug-analysis-placeholder">
                            <p>هنوز تحلیلی انجام نشده است. برای دریافت تحلیل دقیق، مشکلات، علت‌ها و راهکارهای فارسی، روی دکمه زیر کلیک کنید.</p>
                            <?php if (empty($options['api_key'])): ?>
                                <p style="color:#d63638;">⚠️ کلید OpenRouter تنظیم نشده. <a href="<?php echo admin_url('admin.php?page=plug-monitor-settings'); ?>">به تنظیمات بروید</a>.</p>
                            <?php else: ?>
                                <button class="button button-primary button-large plug-analyze-btn" data-id="<?php echo $row['id']; ?>">🤖 تحلیل با <?php echo esc_html($options['model']); ?></button>
                                <p style="font-size:12px; color:#666; margin-top:8px;">تحلیل شامل: اولویت‌بندی مشکلات، علت کندی، کوئری‌های بهینه، چک‌لیست بهبود.</p>
                            <?php endif; ?>
                        </div>
                        <div id="plug-analysis-result" style="display:none; margin-top:15px;"></div>
                    <?php endif; ?>
                    <?php if ($is_live): ?>
                        <div style="margin-top:15px;">
                            <button class="button button-primary" id="plug-analyze-live">🤖 تحلیل گزارش زنده</button>
                            <div id="plug-live-analysis-box" style="margin-top:10px;"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs -->
            <div class="postbox" style="margin-top:20px;">
                <div class="inside" style="padding:0;">
                    <div class="plug-tabs" style="display:flex; border-bottom:1px solid #ccd0d4; background:#f6f7f7;">
                        <button class="plug-tab active" data-tab="queries">🗄 کوئری‌ها (<?php echo count($full_data['database']['queries'] ?? []); ?>)</button>
                        <button class="plug-tab" data-tab="slow">🐌 کند (<?php echo count($full_data['database']['slow_queries'] ?? []); ?>)</button>
                        <button class="plug-tab" data-tab="duplicate">🔁 تکراری (<?php echo count($full_data['database']['duplicate_queries'] ?? []); ?>)</button>
                        <button class="plug-tab" data-tab="errors">❌ خطاها (<?php echo count($full_data['php_errors'] ?? []); ?>)</button>
                        <button class="plug-tab" data-tab="http">🌐 HTTP (<?php echo count($full_data['http_requests']['all'] ?? []); ?>)</button>
                        <button class="plug-tab" data-tab="assets">📦 دارایی‌ها</button>
                        <button class="plug-tab" data-tab="env">⚙️ محیط</button>
                    </div>

                    <div style="padding:15px;">
                        <!-- Queries Tab -->
                        <div class="plug-tab-content active" id="tab-queries">
                            <?php if (empty($full_data['database']['queries'])): ?>
                                <p>جزئیات کوئری ذخیره نشده (SAVEQUERIES خاموش بوده یا لاگ غیرفعال). </p>
                                <p>فقط تعداد: <?php echo $full_data['database']['query_count'] ?? 0; ?></p>
                            <?php else: ?>
                                <table class="widefat striped">
                                    <thead><tr><th>#</th><th>زمان</th><th>کوئری</th><th>caller</th></tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($full_data['database']['queries'],0,100) as $i => $q):
                                            $sql = is_array($q) ? ($q[0] ?? '') : $q;
                                            $time = is_array($q) ? floatval($q[1] ?? 0) : 0;
                                            $trace = is_array($q) ? ($q[2] ?? '') : '';
                                        ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td><?php echo round($time,4); ?>s <?php if($time>=$options['slow_query_threshold']) echo '<span style="color:red;">🐌</span>'; ?></td>
                                            <td><code style="white-space:pre-wrap; word-break:break-all; display:block; max-width:600px; max-height:80px; overflow:auto;"><?php echo esc_html($sql); ?></code></td>
                                            <td><small style="font-size:11px;"><?php echo esc_html(substr($trace,0,120)); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="plug-tab-content" id="tab-slow">
                            <?php if (empty($full_data['database']['slow_queries'])): ?>
                                <p>✅ کوئری کندی بالاتر از آستانه (<?php echo $options['slow_query_threshold']; ?>s) یافت نشد.</p>
                            <?php else: ?>
                                <?php foreach ($full_data['database']['slow_queries'] as $sq): ?>
                                    <div style="background:#fff8e5; border:1px solid #f0c36d; padding:10px; border-radius:6px; margin-bottom:10px;">
                                        <strong>⏱ <?php echo $sq['time']; ?>s</strong><br>
                                        <code style="display:block; background:#fff; padding:8px; margin-top:5px;"><?php echo esc_html($sq['sql']); ?></code>
                                        <small>📍 <?php echo esc_html($sq['caller']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="plug-tab-content" id="tab-duplicate">
                            <?php if (empty($full_data['database']['duplicate_queries'])): ?>
                                <p>✅ کوئری تکراری یافت نشد.</p>
                            <?php else: ?>
                                <table class="widefat striped">
                                    <thead><tr><th>تعداد</th><th>زمان کل</th><th>نمونه کوئری</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($full_data['database']['duplicate_queries'] as $dq): ?>
                                            <tr>
                                                <td><strong><?php echo $dq['count']; ?>x</strong></td>
                                                <td><?php echo round($dq['total_time'],4); ?>s</td>
                                                <td><code><?php echo esc_html(substr($dq['sql'],0,200)); ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="font-size:12px; color:#666;">نکته: کوئری‌های تکراری را با کش کردن (Transient/Object Cache) یا بازنویسی منطق کاهش دهید.</p>
                            <?php endif; ?>
                        </div>

                        <div class="plug-tab-content" id="tab-errors">
                            <?php if (empty($full_data['php_errors'])): ?>
                                <p>✅ خطای PHP ثبت نشده.</p>
                            <?php else: ?>
                                <?php foreach ($full_data['php_errors'] as $e): ?>
                                    <div style="background:#fcf0f1; border:1px solid #e5a5a5; padding:10px; border-radius:6px; margin-bottom:10px;">
                                        <strong style="color:#d63638;"><?php echo esc_html($e['type']); ?></strong>: <?php echo esc_html($e['message']); ?><br>
                                        <small><?php echo esc_html($e['file'] . ':' . $e['line']); ?> (<?php echo round($e['time'] ?? 0,3); ?>s)</small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="plug-tab-content" id="tab-http">
                            <?php if (empty($full_data['http_requests']['all'])): ?>
                                <p>درخواست HTTP ثبت نشده.</p>
                            <?php else: ?>
                                <table class="widefat striped">
                                    <thead><tr><th>زمان</th><th>URL</th><th>Method</th><th>Status</th><th>Caller</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($full_data['http_requests']['all'] as $h): ?>
                                            <tr style="<?php echo ($h['duration'] ?? 0) >= $options['slow_http_threshold'] ? 'background:#fff8e5;' : ''; ?>">
                                                <td><?php echo round($h['duration'] ?? 0,3); ?>s <?php if(($h['duration']??0)>=$options['slow_http_threshold']) echo '🐌'; ?></td>
                                                <td><small title="<?php echo esc_attr($h['url']); ?>"><?php echo esc_html(mb_substr($h['url'],0,80)); ?></small></td>
                                                <td><?php echo esc_html($h['method'] ?? ''); ?></td>
                                                <td><?php echo esc_html($h['status'] ?? ''); ?><?php if(!empty($h['is_error'])) echo ' ❌'; ?></td>
                                                <td><small><?php echo esc_html(substr($h['caller'] ?? '',0,80)); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="plug-tab-content" id="tab-assets">
                            <h4>📜 اسکریپت‌ها (<?php echo count($full_data['assets']['scripts']['queue'] ?? []); ?>)</h4>
                            <p style="word-break:break-all;"><code><?php echo esc_html(implode(', ', $full_data['assets']['scripts']['queue'] ?? [])); ?></code></p>
                            <h4>🎨 استایل‌ها (<?php echo count($full_data['assets']['styles']['queue'] ?? []); ?>)</h4>
                            <p style="word-break:break-all;"><code><?php echo esc_html(implode(', ', $full_data['assets']['styles']['queue'] ?? [])); ?></code></p>
                            <h4>🧩 افزونه‌های فعال (<?php echo count(array_filter($full_data['plugins'] ?? [], fn($p)=>$p['active'])); ?>)</h4>
                            <ul style="list-style:disc; margin-right:20px;">
                                <?php foreach ($full_data['plugins'] ?? [] as $pl): if(!$pl['active']) continue; echo '<li>'.esc_html($pl['name'].' v'.$pl['version']).'</li>'; endforeach; ?>
                            </ul>
                        </div>

                        <div class="plug-tab-content" id="tab-env">
                            <table class="widefat striped">
                                <?php foreach ($full_data['environment'] ?? [] as $k=>$v): if(is_array($v)) continue; ?>
                                    <tr><th><?php echo esc_html($k); ?></th><td><code><?php echo esc_html((string)$v); ?></code></td></tr>
                                <?php endforeach; ?>
                                <?php foreach ($full_data['constants'] ?? [] as $k=>$v): ?>
                                    <tr><th><?php echo esc_html($k); ?></th><td><code><?php echo esc_html(var_export($v,true)); ?></code></td></tr>
                                <?php endforeach; ?>
                            </table>
                            <h4>🖼 قالب</h4>
                            <p>فایل: <code><?php echo esc_html($full_data['theme']['template_file'] ?? ''); ?></code></p>
                            <p>Stylesheet: <?php echo esc_html($full_data['theme']['stylesheet'] ?? ''); ?> | Template: <?php echo esc_html($full_data['theme']['template'] ?? ''); ?></p>
                            <h4>🔗 درخواست</h4>
                            <pre style="background:#f6f8fa; padding:10px; overflow:auto; font-size:12px;"><?php echo esc_html(wp_json_encode($full_data['request'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="postbox">
                <div class="inside" style="padding:15px;">
                    <h3>ℹ️ اطلاعات گزارش</h3>
                    <p><strong>شناسه:</strong> <?php echo esc_html($row['id']); ?></p>
                    <p><strong>زمان:</strong> <?php echo esc_html($row['created_at']); ?></p>
                    <p><strong>URL:</strong><br><small style="word-break:break-all;"><?php echo esc_html($row['url']); ?></small></p>
                    <p><strong>کاربر:</strong> <?php echo get_userdata($row['user_id'] ?? 0)->display_name ?? 'ناشناس'; ?> (#<?php echo $row['user_id'] ?? 0; ?>)</p>
                    <hr>
                    <h4>📊 خلاصه عملکرد</h4>
                    <ul>
                        <li>زمان کل: <?php echo round($full_data['execution']['total_time'] ?? 0,4); ?>s</li>
                        <li>حافظه اوج: <?php echo $full_data['execution']['peak_memory_mb'] ?? 0; ?> MB</li>
                        <li>کوئری: <?php echo $full_data['database']['query_count'] ?? 0; ?> (<?php echo round($full_data['database']['total_query_time'] ?? 0,4); ?>s)</li>
                        <li>HTTP: <?php echo $full_data['http_requests']['count'] ?? 0; ?></li>
                        <li>هوک‌ها: <?php echo $full_data['hooks']['count'] ?? 0; ?></li>
                    </ul>
                </div>
            </div>

            <div class="postbox" style="margin-top:20px;">
                <div class="inside" style="padding:15px;">
                    <h3>💡 تفسیر سریع</h3>
                    <div style="font-size:13px; line-height:1.8;">
                        <?php
                        $issues = [];
                        if (($full_data['execution']['total_time'] ?? 0) > 2) $issues[] = '⏱ زمان اجرا بالاست (>2s) - کش، بهینه‌سازی کوئری، بررسی افزونه‌ها';
                        if (($full_data['execution']['peak_memory_mb'] ?? 0) > 80) $issues[] = '🧠 مصرف حافظه زیاد است - افزونه سنگین یا حلقه بی‌نهایت';
                        if (count($full_data['database']['slow_queries'] ?? []) > 0) $issues[] = '🐌 کوئری کند دارید - ایندکس و بهینه‌سازی لازم است';
                        if (count($full_data['database']['duplicate_queries'] ?? []) > 3) $issues[] = '🔁 کوئری تکراری زیاد - از transient استفاده کنید';
                        if (count($full_data['php_errors'] ?? []) > 0) $issues[] = '❌ خطای PHP - لاگ خطاها را بررسی کنید';
                        if (count($full_data['http_requests']['slow'] ?? []) > 0) $issues[] = '🌐 درخواست HTTP کند - API خارجی یا لایسنس‌چک';
                        if (empty($issues)) echo '✅ مشکل بحرانی در این گزارش دیده نشد.';
                        else foreach ($issues as $iss) echo '<div style="margin-bottom:8px; background:#fff8e5; padding:6px; border-radius:4px;">'.$iss.'</div>';
                        ?>
                    </div>
                </div>
            </div>

            <div class="postbox" style="margin-top:20px;">
                <div class="inside" style="padding:15px;">
                    <h3>🔧 عملیات</h3>
                    <?php if (!$is_live): ?>
                    <button class="button button-primary plug-analyze-btn" data-id="<?php echo $row['id']; ?>" style="width:100%; margin-bottom:8px;">🤖 تحلیل مجدد با AI</button>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=plug-monitor&action=delete&report_id='.$row['id']), 'plug_delete_'.$row['id']); ?>" class="button" style="width:100%;" onclick="return confirm('حذف شود؟')">🗑 حذف گزارش</a>
                    <?php else: ?>
                    <p>این گزارش زنده است و ذخیره نشده. برای ذخیره:</p>
                    <button class="button button-primary" onclick="if(window.plugManualCollect) plugManualCollect();">⚡ ذخیره گزارش زنده</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.plug-card { background:#f6f7f7; padding:12px; border-radius:8px; text-align:center; border:1px solid #dcdcde; }
.plug-big { font-size:20px; font-weight:bold; }
.plug-tab { background:transparent; border:none; padding:10px 14px; cursor:pointer; font-size:13px; }
.plug-tab.active { background:#fff; border:1px solid #ccd0d4; border-bottom:none; border-radius:4px 4px 0 0; font-weight:bold; }
.plug-tab-content { display:none; }
.plug-tab-content.active { display:block; }
.plug-analysis-render h3 { margin-top:16px; color:#1d2327; }
.plug-analysis-render ul { list-style:disc; margin-right:20px; }
</style>

<script>
jQuery(function($){
    $('.plug-tab').on('click', function(){
        var tab = $(this).data('tab');
        $('.plug-tab').removeClass('active');
        $(this).addClass('active');
        $('.plug-tab-content').removeClass('active');
        $('#tab-'+tab).addClass('active');
    });

    $('.plug-analyze-btn').on('click', function(){
        var btn = $(this);
        var id = btn.data('id');
        if(id==='live') return;
        var original = btn.text();
        btn.prop('disabled', true).text('در حال تحلیل...');
        $('#plug-analysis-result').show().html('<p>🤖 در حال ارسال به '+ $('#model').val() +' و تحلیل... لطفا 10-20 ثانیه صبر کنید.</p>');

        $.post(PlugMonitor.ajax_url, {
            action: 'plug_analyze_report',
            nonce: PlugMonitor.nonce,
            report_id: id,
            model: '<?php echo esc_js($options['model']); ?>'
        }, function(res){
            btn.prop('disabled', false).text(original);
            if(res.success){
                var html = '<div style="background:#d4edda; padding:15px; border-radius:8px;"><h3>✅ تحلیل انجام شد (مدل: '+res.data.model+')</h3><div style="background:#fff; padding:10px; border-radius:6px; margin-top:10px; line-height:1.9;">'+ res.data.analysis.replace(/\n/g,'<br>') +'</div></div>';
                $('#plug-analysis-result').html(html);
                $('#plug-analysis-placeholder').html(html);
                setTimeout(()=>location.reload(), 2000);
            } else {
                $('#plug-analysis-result').html('<div style="background:#f8d7da; padding:10px; border-radius:6px;">❌ '+res.data.message+'</div>');
            }
        }).fail(function(){
            btn.prop('disabled', false).text(original);
            $('#plug-analysis-result').html('<div style="background:#f8d7da; padding:10px;">خطای ارتباط</div>');
        });
    });

    $('#plug-analyze-live').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true).text('در حال تحلیل...');
        // برای live از manual_collect استفاده نمی‌کنیم، مستقیم AJAX می‌زنیم؟
        // اینجا فقط نمایش می‌دهیم که باید ذخیره شود
        $.post(PlugMonitor.ajax_url, {
            action: 'plug_manual_collect',
            nonce: PlugMonitor.nonce
        }, function(res){
            if(res.success){
                var id = res.data.report_id;
                // حالا تحلیل
                $.post(PlugMonitor.ajax_url, {
                    action: 'plug_analyze_report',
                    nonce: PlugMonitor.nonce,
                    report_id: id
                }, function(r2){
                    btn.prop('disabled', false).text('🤖 تحلیل گزارش زنده');
                    if(r2.success){
                        $('#plug-live-analysis-box').html('<div style="background:#d4edda; padding:10px;">✅ تحلیل شد:<br>'+r2.data.analysis.substring(0,2000)+'</div>');
                    } else {
                        $('#plug-live-analysis-box').html('❌ '+r2.data.message);
                    }
                });
            }
        });
    });
});
</script>
