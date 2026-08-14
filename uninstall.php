<?php
// Only run when WP uninstalls the plugin
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'error_lait_settings' );
delete_option( 'error_lait_schema_version' );
delete_option( 'lat_settings' );
