<?php

/*
package: cmc_hook
file: class-cmc-hook-functions.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class cmchk{

	/**
     * Check whether current user is in permitted role to use cmc-hooks
	 * By Default administrator has permission
	 * Other roles has to be added at Settings inorder to allow thier users
     */
	public static function is_user_allowed(){
		if( current_user_can('administrator') ) return true;
		$allowed_roles = self::get_setting('allowed_roles');  $allowed_roles = explode('\n', $allowed_roles);
		foreach($allowed_roles  as $role){
			if( current_user_can( $role ) ) return true;
		}
		return false;
	}

	/**
     *  Replace functions with live ones when exporting
     */
	public static function replacelive( $out ){
		$out = str_replace('cmchk_include', 'cmchk_include_live', $out );
		$out = str_replace('cmchk_project_dir', 'cmchk_project_dir_live', $out ); 
		$out = str_replace('cmchk_plugins_loaded', 'plugins_loaded', $out );
		return $out;
	}
	
	/**
     * On Admin Menu load this function run
	 * 	@since 1.0.6
     */
	public static function menu_render( $slug, $section, $menus ){
		$menu = $menus[$slug];
		if( !empty($menu['sections']) ){
			cmchk::menu_section_render( $slug, $section, $menus );
		}else{			
			if( is_callable($menu['page']) )
				call_user_func( $menu['page'], $menu, $slug, $section );
		}
	}

	/**
     * On Admin Menu load this function run
	 * 	@since 1.0.6
     */
	public static function menu_section_render( $slug, $section, $menus ){
		$menu = $menus[$slug]; $section = empty( $section )? $menu['default']: $section;
		$sections = apply_filters("cmchk_admin_page_section-{$section}", $menu['sections'], $menus);		
		$page = $sections[$section];
		call_user_func( $page['page'], $menu, $slug, $section );
	}
	
    /**
     *  Download file
     */
    public static function output_file( $out, $fname, $type = 'application/octet-stream' ){
        $type = (empty($type))? 'application/octet-stream' : $type;	
        header('Content-Description: File Transfer');		
        header('Content-Type: '.$type);
        header('Content-Disposition: attachment; filename='.$fname);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $out;
        exit();				
    }
	
    /**
     *  Save slug of hook and project
     */
    public static function save_slug(){
        global $wpdb;
        if( $_REQUEST['table'] == 'hook' ){
			$table = CMCHK_TABLE_HOOK;
			cmchk::slug_update( $table ); 
        }else if( $_REQUEST['table'] == 'project' ){
			$table = CMCHK_TABLE_PROJECT;
			cmchk::slug_update( $table ); 
        }
    }
	
    /**
     *  Save slug based on a particular table
     * 
     * @param string $table whether hook or project
     */
    public static function slug_update( $table ){
        global $wpdb; $id = $_REQUEST['id']; $slug = $_REQUEST['slug']; $proj_id = !empty($_REQUEST['proj'])? $_REQUEST['proj'] : 0;	
        $slug = cmchk::get_slug( $table, $id, $slug, $proj_id ); 		
        $data['slug'] = $slug;
        $wpdb->update( $table, $data, array('id'=> $id) );
        wp_send_json(array('slug'=> $data['slug']));
    }
	
    /**
     *  Get Slug of hook or project
     * 
     * @param string $table whether to use hook or project table
     * @param string $id  id of the record to generate slug for
     * @param string $slug the string to propose as slug
     * @param int $pid Project id
     */
    public static function  get_slug( $table, $id, $slug, $pid = 0 ){
        global $wpdb; $slug = sanitize_text_field( $slug ); $slug = str_replace(' ', '_', $slug); 
        $table = ( $table == CMCHK_TABLE_PROJECT ) ? CMCHK_TABLE_PROJECT: CMCHK_TABLE_HOOK;
		$slug = substr( $slug, 0, 200 );		
        $sql = "SELECT `slug` FROM `$table` WHERE slug = '%s' and id != %d and project_id = %d LIMIT 1 ";
        $check = $wpdb->get_var( $wpdb->prepare( $sql, $slug, $id, $pid ) );
        if( $check ){		
			$suffix = 1; 
			do {
				$slug_suff = substr($slug . "_$suffix", 0, 200);
				$sql = "SELECT `slug` FROM `$table` WHERE slug = '%s' and id != %d and project_id = %d LIMIT 1 ";			
				$check = $wpdb->get_var( $wpdb->prepare( $sql, $slug_suff, $id, $pid ) );	
				$suffix++;			
			} while ( $check );
			$slug = $slug_suff;
        }
        $slug = apply_filters('cmchk_get_slug', $slug, $id, $table, $pid);
        return $slug;
    }

	/**
     *  Get value of one Hook Settings
     * 
     * @param string $name name of the settings
     * @param string $default default value if name doesnt exist
     */
    public static function get_setting( $name, $default = ""){
        global $cmchk_settings_default;
        $opt = get_option( CMCHK_SETTINGS, $cmchk_settings_default );
        return isset($opt[$name])? $opt[$name]: $default;
    }
        
	/**
     *  Set value of one Hook Settings
     * 
     * @param array $data name value of settings
     */
    public static function set_setting( $new_data ){
		global $cmchk_settings_default;
		$data = get_option( CMCHK_SETTINGS, $cmchk_settings_default );
		$data = array_merge( $data, $new_data );
		update_option( CMCHK_SETTINGS, $data );
	}
	
    /**
     *  Get Current Url
     */
    public static function current_url(){
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
	
	/**
     *  Run Php Code
	 *
	 *	@param string $file path to the file to require
	 * 	@param array $cmc_args	arguments to pass to the file
     */
	public static function run_php( $code, $cmchk_args = array() ){
		eval('?>'.$code);
	}
	
	/**
     *  Require a file
	 *
	 *	@param string $file path to the file to require
	 * 	@param array $cmc_args	arguments to pass to the file
     */
	public static function include_file( $file, $cmc_args = array() ){
		require( $file );
	}
	
	/**
	* Check whether a string starts with another string
	*/	
	function startsWith($needle, $haystack) {
		return preg_match('/^' . preg_quote($needle, '/') . '/', $haystack);
	}
	
	/**
	* Check whether a string ends with another string
	*/
	function endsWith($needle, $haystack) {
		return preg_match('/' . preg_quote($needle, '/') . '$/', $haystack);
	}
    
}