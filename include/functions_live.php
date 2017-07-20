<?php
/**
 This file contain all custom function equivalence
 of cmc-hook in the project - CMC Hook Project 
**/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if( !function_exists('cmchk_include_live') ){
function cmchk_include_live( $path, $cmchk_args = array() ){
    if( empty( $path )) return;
	$path = explode(":", $path);  $path = $path[0];
    include( $path );
}
}

if( !function_exists('cmchk_project_dir_live') ){
function cmchk_project_dir_live( $id = 0, $create = false){
	$dir = plugin_dir_path( __FILE__ );
	return $dir;
}
}