<?php
if (!defined('ABSPATH')) exit;

$options = Plug_Monitor_Defaults::get_options();
$collector = Plug_Monitor_Collector::instance();
$live = $GLOBALS['plug_monitor_collected'] ?? $collector->get_last_collected();

// صفحه‌بندی
$paged = max(1, intval($_GET['paged'] ?? 1));
$per_page = 20;
$offset = ($paged - 1) * $per_page;
$total = Plug_Monitor_Reporter::count_reports();
$reports = Plug_Monitor_Reporter::get_reports($per_page, $offset);
$total_pages = ceil($total / $per_page);
?>
<div class="wrap plug-monitor-wrap" dir="rtl">
    <h1>📊 مانیتور هوشمند وردپرس <small style="font-size:12px; color:#666;">v<?php echo PLUG_MONITOR_VERSION; ?></small></h1>

    <div class="plug-top-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-top:20px;">
        <!-- Live Stats -->
        <div class="postbox">
            <div class="inside" style="padding:15px;">
                <h2 style="margin-top:0;">🚀 وضعیت لحظه‌ای همین صفحه</h2>
                <?php if ($live): ?>
                    <?php
                        $exec = round($live['execution']['total_time'] ?? 0, 4);
                        $mem = $live['execution']['peak_memory_mb'] ?? 0;
                        $qcount = $live['database']['query_count'] ?? 0;
                        $qtime = round($live['database']['total_query_time'] ?? 0, 4);
                        $errors = count($live['php_errors'] ?? []);
                        $http = $live['http_requests']['count'] ?? 0;
                        $slow = count($live['database']['slow_queries'] ?? []);
                        $dup = count($live['database']['duplicate_queries'] ?? []);
                    ?>
                    <div style="display:grid; grid-template-columns: repeat(3,1fr); gap:15px;">
                        <div style="background:#f0f6fc; padding:12px; border-radius:8px; border-right:4px solid #2271b1;">
                            <strong>⏱ زمان اجرا</strong><br>
                            <span style="font-size:22px; font-weight:bold;"><?php echo $exec; ?>s</span>
                            <?php if ($exec > 2) echo '<span style="color:#d63638;"> (کند!)</span>'; ?>
                        </div>
                        <div style="background:#f6f7f7; padding:12px; border-radius:8px; border-right:4px solid #00a32a;">
                            <strong>🧠 حافظه اوج</strong><br>
                            <span style="font-size:22px; font-weight:bold;"><?php echo $mem; ?> MB</span>
                        </div>
                        <div style="background:#fcf0f1; padding:12px; border-radius:8px; border-right:4px solid #d63638;">
                            <strong>🗄 کوئری‌ها</strong><br>
                            <span style="font-size:22px; font-weight:bold;"><?php echo $qcount; ?></span>
                            <small>(<?php echo $qtime; ?>s)</small>
                            <?php if ($slow>0) echo "<br><span style='color:#d63638;'>$slow کند</span>"; ?>
                        </div>
                    </div>
                    <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                        <span class="plug-badge-info">❌ خطاها: <?php echo $errors; ?></span>
                        <span class="plug-badge-info">🌐 HTTP: <?php echo $http; ?></span>
                        <span class="plug-badge-info">🔁 تکراری: <?php echo $dup; ?></span>
                        <span class="plug-badge-info">🧩 افزونه‌ها: <?php echo count($live['plugins'] ?? []); ?></span>
                    </div>

                    <?php if (!empty($live['database']['slow_queries'])): ?>
                    <details open style="margin-top:15px; background:#fff8e5; padding:10px; border-radius:6px;">
                        <summary style="cursor:pointer; font-weight:bold;">🐌 کندترین کوئری‌ها (<?php echo count($live['database']['slow_queries']); ?>)</summary>
                        <ol style="margin-top:10px;">
                            <?php foreach (array_slice($live['database']['slow_queries'],0,5) as $sq): ?>
                                <li style="margin-bottom:8px;">
                                    <code style="display:block; background:#fff; padding:6px; border-radius:4px; overflow:auto; max-height:60px;"><?php echo esc_html(substr($sq['sql'],0,300)); ?></code>
                                    <small>⏱ <?php echo $sq['time']; ?>s | 📍 <?php echo esc_html($sq['caller']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </details>
                    <?php endif; ?>

                    <?php if (!empty($live['php_errors'])): ?>
                    <details style="margin-top:10px; background:#fcf0f1; padding:10px; border-radius:6px;">
                        <summary style="cursor:pointer; font-weight:bold; color:#d63638;">❌ خطاهای PHP (<?php echo count($live['php_errors']); ?>)</summary>
                        <ul>
                            <?php foreach (array_slice($live['php_errors'],0,5) as $e): ?>
                                <li><strong><?php echo esc_html($e['type']); ?>:</strong> <?php echo esc_html(substr($e['message'],0,200)); ?><br><small><?php echo esc_html($e['file'] . ':' . $e['line']); ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endif; ?>

                <?php else: ?>
                    <p>داده زنده هنوز جمع‌آوری نشده یا SAVEQUERIES خاموش است. صفحه را رفرش کنید.</p>
                    <p><code>define('SAVEQUERIES', true);</code> را به wp-config.php اضافه کنید.</p>
                <?php endif; ?>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button id="plug-manual-collect" class="button button-primary">⚡ گرفتن گزارش جدید</button>
                    <button id="plug-manual-analyze" class="button">🤖 تحلیل با AI</button>
                    <a href="<?php echo admin_url('admin.php?page=plug-monitor-settings'); ?>" class="button">⚙️ تنظیمات OpenRouter</a>
                </div>
                <div id="plug-live-result" style="margin-top:15px;"></div>
            </div>
        </div>

        <!-- Side -->
        <div>
            <div class="postbox">
                <div class="inside" style="padding:15px;">
                    <h3>🤖 وضعیت OpenRouter</h3>
                    <?php if (empty($options['api_key'])): ?>
                        <p style="color:#d63638;">⚠️ کلید API تنظیم نشده است.</p>
                        <a href="<?php echo admin_url('admin.php?page=plug-monitor-settings'); ?>" class="button button-primary">تنظیم کلید</a>
                    <?php else: ?>
                        <p>✅ کلید تنظیم شده: <code><?php echo esc_html(substr($options['api_key'],0,12).'...'); ?></code></p>
                        <p>🧠 مدل انتخابی: <code><?php echo esc_html($options['model']); ?></code></p>
                        <button id="plug-test-connection" class="button">🔌 تست اتصال</button>
                        <div id="plug-test-result" style="margin-top:10px;"></div>
                    <?php endif; ?>
                    <hr>
                    <p><strong>نکات:</strong></p>
                    <ul style="list-style:disc; margin-right:20px; font-size:13px;">
                        <li>گزارش‌ها حداکثر <?php echo $options['max_reports']; ?> عدد و تا <?php echo $options['keep_reports_days']; ?> روز نگه‌داری می‌شوند.</li>
                        <li>برای دقت بیشتر، <code>SAVEQUERIES</code> را فعال کنید.</li>
                        <li>تحلیل AI به زبان فارسی و با پیشنهاد کدی انجام می‌شود.</li>
                    </ul>
                </div>
            </div>

            <div class="postbox" style="margin-top:20px;">
                <div class="inside" style="padding:15px;">
                    <h3>📈 آمار کلی</h3>
                    <p>تعداد کل گزارش‌ها: <strong><?php echo $total; ?></strong></p>
                    <p>میانگین زمان اجرا (۲۰ گزارش آخر): 
                        <?php
                            global $wpdb;
                            $avg = $wpdb->get_var("SELECT AVG(exec_time) FROM " . Plug_AI_Monitor::get_table() . " ORDER BY created_at DESC LIMIT 20");
                            echo $avg ? round(floatval($avg),3).'s' : '-';
                        ?>
                    </p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=plug-monitor&action=truncate'), 'plug_truncate'); ?>" onclick="return confirm('همه گزارش‌ها حذف شوند؟')" class="button button-link-delete">🗑 حذف همه</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="postbox" style="margin-top:30px;">
        <div class="inside" style="padding:0;">
            <h2 style="padding:15px 15px 0;">📋 گزارش‌های ذخیره‌شده</h2>
            <?php if (empty($reports)): ?>
                <p style="padding:15px;">هنوز گزارشی ثبت نشده. با دکمه «گزارش جدید» یک گزارش بگیرید.</p>
            <?php else: ?>
            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>زمان</th>
                        <th>URL</th>
                        <th>اجرا</th>
                        <th>کوئری</th>
                        <th>حافظه</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $row): ?>
                    <tr>
                        <td>#<?php echo $row['id']; ?></td>
                        <td><small><?php echo esc_html($row['created_at']); ?></small></td>
                        <td><small title="<?php echo esc_attr($row['url']); ?>"><?php echo esc_html(mb_substr($row['url'],0,60)); ?></small></td>
                        <td><?php echo round(floatval($row['exec_time']),3); ?>s</td>
                        <td><?php echo $row['query_count']; ?> <small>(<?php echo round(floatval($row['query_time']),3); ?>s)</small></td>
                        <td><?php echo round($row['memory_peak']/1024/1024,1); ?> MB</td>
                        <td>
                            <?php if ($row['status']==='analyzed'): ?>
                                <span class="plug-status analyzed">✅ تحلیل شده</span>
                            <?php else: ?>
                                <span class="plug-status new">🆕 جدید</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=plug-monitor-report&report_id='.$row['id']); ?>" class="button button-small">👁 مشاهده</a>
                            <button class="button button-small plug-analyze-btn" data-id="<?php echo $row['id']; ?>">🤖 تحلیل</button>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=plug-monitor&action=delete&report_id='.$row['id']), 'plug_delete_'.$row['id']); ?>" onclick="return confirm('حذف شود؟')" class="button button-small">🗑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="tablenav" style="padding:10px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => admin_url('admin.php?page=plug-monitor&paged=%#%'),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '‹ قبلی',
                        'next_text' => 'بعدی ›',
                    ]);
                    ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.plug-badge-info { background:#f0f0f1; padding:4px 8px; border-radius:12px; font-size:12px; }
.plug-status { padding:3px 8px; border-radius:10px; font-size:11px; }
.plug-status.analyzed { background:#d5e9f0; color:#0a4b78; }
.plug-status.new { background:#fef8ee; color:#94660e; }
.rtl .widefat th { text-align:right; }
</style>

<script>
function plugManualCollect(){
    jQuery('#plug-live-result').html('در حال جمع‌آوری...');
    jQuery.post(PlugMonitor.ajax_url, {action:'plug_manual_collect', nonce:PlugMonitor.nonce}, function(res){
        if(res.success){
            jQuery('#plug-live-result').html('<div style="background:#d4edda; padding:10px; border-radius:6px;">✅ گزارش #' + res.data.report_id + ' ثبت شد. زمان: ' + res.data.summary.time + 's | کوئری: ' + res.data.summary.queries + '</div>');
            setTimeout(()=>location.reload(), 1500);
        } else {
            jQuery('#plug-live-result').html('<div style="background:#f8d7da; padding:10px;">❌ '+ (res.data?.message||'خطا') +'</div>');
        }
    });
}
</script>
