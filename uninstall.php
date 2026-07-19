<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;
$table = $wpdb->prefix . 'plug_monitor_reports';
$wpdb->query("DROP TABLE IF EXISTS $table");

delete_option('plug_monitor_options');
delete_transient('plug_monitor_openrouter_models');
delete_option('_transient_timeout_plug_monitor_openrouter_models');

// پاک کردن کرون
wp_clear_scheduled_hook('plug_monitor_cleanup');
