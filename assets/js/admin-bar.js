function plugManualCollect(){
    if(typeof jQuery==='undefined'){ alert('jQuery not loaded'); return false; }
    var nonce = (window.PlugMonitorBar && PlugMonitorBar.nonce) ? PlugMonitorBar.nonce : (window.PlugMonitor ? PlugMonitor.nonce : '');
    var ajax_url = (window.PlugMonitorBar && PlugMonitorBar.ajax_url) ? PlugMonitorBar.ajax_url : (window.PlugMonitor ? PlugMonitor.ajax_url : '/wp-admin/admin-ajax.php');
    if(!nonce){ alert('امنیت: nonce یافت نشد، صفحه را رفرش کنید.'); return false; }
    if(!confirm('گزارش جدید از همین صفحه بگیریم و ذخیره کنیم؟')) return false;
    jQuery.post(ajax_url, {action:'plug_manual_collect', nonce:nonce}, function(res){
        if(res.success){
            alert('✅ گزارش #' + res.data.report_id + ' ثبت شد. به صفحه گزارش‌ها می‌روید.');
            window.location.href = (PlugMonitorBar.admin_url || '/wp-admin/admin.php?page=plug-monitor-report&report_id=') + res.data.report_id;
        } else {
            alert('❌ ' + (res.data.message || 'خطا'));
        }
    }).fail(function(){ alert('خطای AJAX'); });
    return false;
}
