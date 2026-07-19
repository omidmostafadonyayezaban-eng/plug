/* Plug Monitor Admin JS */
jQuery(function($){
    // Manual collect for all pages
    window.plugManualCollect = function(){
        if(typeof PlugMonitor === 'undefined') return alert('PlugMonitor object not found');
        $('#plug-live-result').html('در حال جمع‌آوری گزارش...');
        $.post(PlugMonitor.ajax_url, {action:'plug_manual_collect', nonce:PlugMonitor.nonce}, function(res){
            if(res.success){
                $('#plug-live-result').html('<div style="background:#d4edda; padding:10px; border-radius:6px;">✅ گزارش #'+res.data.report_id+' ثبت شد. <a href="'+window.location.href.replace(/&report_id=.*/, "")+'&report_id='+res.data.report_id+'">مشاهده</a> | رفرش خودکار...</div>');
                setTimeout(function(){ location.href = location.origin + location.pathname + '?page=plug-monitor-report&report_id=' + res.data.report_id; }, 1200);
            } else {
                $('#plug-live-result').html('<div style="background:#f8d7da; padding:10px; border-radius:6px;">❌ '+(res.data.message||'خطا')+'</div>');
            }
        }).fail(function(){
            $('#plug-live-result').html('<div style="background:#f8d7da; padding:10px;">خطای AJAX</div>');
        });
        return false;
    };

    $('#plug-manual-collect').on('click', function(e){
        e.preventDefault();
        window.plugManualCollect();
    });

    // Test connection on dashboard
    $('#plug-test-connection').on('click', function(){
        var btn=$(this); btn.prop('disabled',true).text('در حال تست...');
        $.post(PlugMonitor.ajax_url, {action:'plug_test_connection', nonce:PlugMonitor.nonce, model: ''}, function(res){
            btn.prop('disabled',false).text('🔌 تست اتصال');
            if(res.success){
                $('#plug-test-result').html('<div style="background:#d4edda; padding:8px; border-radius:6px;">✅ '+res.data.response+'</div>');
            } else {
                $('#plug-test-result').html('<div style="background:#f8d7da; padding:8px; border-radius:6px;">❌ '+res.data.message+'</div>');
            }
        });
    });

    // Quick analyze current live
    $('#plug-manual-analyze').on('click', function(){
        if(typeof PlugMonitor === 'undefined') return;
        var btn=$(this); btn.prop('disabled',true).text('🤖 در حال تحلیل...');
        // اول گزارش بگیر بعد تحلیل
        $.post(PlugMonitor.ajax_url, {action:'plug_manual_collect', nonce:PlugMonitor.nonce}, function(res){
            if(!res.success){
                btn.prop('disabled',false).text('🤖 تحلیل با AI');
                $('#plug-live-result').html('❌ '+res.data.message);
                return;
            }
            var id=res.data.report_id;
            $.post(PlugMonitor.ajax_url, {action:'plug_analyze_report', nonce:PlugMonitor.nonce, report_id:id}, function(r2){
                btn.prop('disabled',false).text('🤖 تحلیل با AI');
                if(r2.success){
                    $('#plug-live-result').html('<div style="background:#d4edda; padding:10px; border-radius:6px;"><strong>✅ تحلیل گزارش #'+id+' انجام شد</strong><br><div style="background:#fff; padding:10px; margin-top:8px; border-radius:6px; max-height:300px; overflow:auto; line-height:1.8;">'+r2.data.analysis.substring(0,2000)+'...</div><a href="?page=plug-monitor-report&report_id='+id+'" class="button button-primary" style="margin-top:8px;">مشاهده کامل</a></div>');
                } else {
                    $('#plug-live-result').html('<div style="background:#f8d7da; padding:10px;">❌ '+r2.data.message+'</div>');
                }
            });
        });
    });

    // Analyze buttons in table
    $('.plug-analyze-btn').on('click', function(){
        var id=$(this).data('id');
        if(!id || id==='live') return;
        var btn=$(this); var orig=btn.text(); btn.prop('disabled',true).text('⏳ تحلیل...');
        $.post(PlugMonitor.ajax_url, {action:'plug_analyze_report', nonce:PlugMonitor.nonce, report_id:id}, function(res){
            btn.prop('disabled',false).text(orig);
            if(res.success){
                alert('✅ تحلیل انجام شد! صفحه رفرش می‌شود.');
                location.href = '?page=plug-monitor-report&report_id='+id;
            } else {
                alert('❌ '+res.data.message);
            }
        }).fail(function(){ btn.prop('disabled',false).text(orig); alert('خطای AJAX'); });
    });
});
