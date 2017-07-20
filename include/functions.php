<?php

/**
 * functions
 *
 * @author 	Evans Edem Ladzagla
 * @file	functions.php
 * @category 	Core
 * @package 	cmc-hook/include
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function cmchk_include( $slug, $cmchk_args = array() ){
    if( empty( $slug )) return;
	global $wpdb; $slug = explode(":", $slug); $pid = $slug[1]; $slug = $slug[0];
	$slug =  preg_replace( "/.php$/i", '', $slug);
	$sql = $wpdb->prepare("SELECT * FROM `".CMCHK_TABLE_HOOK."` where slug = '%s' and project_id = %d ", $slug, $pid);
	$data = $wpdb->get_row( $sql, 'ARRAY_A' );

	$data = apply_filters('cmchk_include_hook', $data);
    if( $data === false) return;
    cmc_hook::run_php( $data['code'], $cmchk_args );
}

function cmchk_project_dir( $id = 0, $create = false){
	$dir = CMCHK_DIR_PROJECT. 'project_'.$id.'/';
	if( $create && !file_exists( $dir ) )  mkdir( $dir, 0777, true );
	return CMCHK_DIR_PROJECT. 'project_'.$id.'/';
}