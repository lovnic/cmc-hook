<?php 
/**
 * CMC Hook Uninstall
 *
 * @version     0.0.6
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
 global $wpdb;

if( self::get_setting('del_table_uninstall', false) ){
	$sql = "DROP TABLE IF EXISTS ".CMCHK_TABLE_HOOK;
	$wpdb->query($sql);
	$sql = "DROP TABLE IF EXISTS ".CMCHK_TABLE_PROJECT;
	$wpdb->query($sql);
}

if( self::get_setting('del_opt_uninstall', false) ){
	delete_option('cmc_hook_settings');
}

?>
