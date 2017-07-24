<?php

/*
package: cmc_hook
file: class-cmc-hook-hook.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class cmchk_hook{
	
	/*
	*	Used Internally to save sanitized data record to hook table;
	*
	* 	@param array $model record values
	*/
	public function editor( $model = array() ){
        global $wpdb; $data = array(); $response = array(); 
        if( empty($model['title']) ){
            $response['success'] = false; $response['message'] = 'Title cannot be empty';
			return $response;
        }

        $data['id'] = isset( $model['id'] )? $model['id'] : 0;
        $data['title'] = sanitize_text_field( wp_unslash( $model['title'] ) );			
        if( $data['id'] == 0){
            $data['project_id'] = !empty($model['project_id'])? $model['project_id'] : 0;
            $data['slug'] = cmchk::get_slug( CMCHK_TABLE_HOOK, $data['id'], $data['title'], $data['project_id'] ); //sanitize_text_field( wp_unslash( str_replace(' ', '_', $_POST['title'])) );
        }					
       // $data['code'] = !isset($model['code'])? "" : wp_unslash( $model['code'] );
        $data['type'] = !isset($model['type'])? "file": sanitize_text_field( wp_unslash( $model['type'] ) );
        $data['hookname'] = !isset($model['hookname'])? "": sanitize_text_field( wp_unslash( $model['hookname'] ) );
        $data['args'] = !isset($model['args'])? 0 : sanitize_text_field( wp_unslash( $model['args'] ) );
        $data['priority'] = !isset($model['priority'])? 10 : sanitize_text_field( wp_unslash( $model['priority'] ) );
        $data['description'] = !isset($model['description'])? "": wp_unslash( $model['description'] );
        $data['datetimeupdated'] = date('Y-m-d H:i:s');
		$data['active'] = empty( $model['active'] ) ? 0 : ($model['active'] < 1 ? -1: 1);
		$data['status'] = 'publish';
        $data['enable_shortcode'] = ( $data['id'] == 0)? 0 : (isset( $model['enable_shortcode'] ) ? 1 : 0);

        $data = apply_filters( 'cmchk_editor_data_save', $data);
        if( $data === false ) return false;
		
        if( $data['id'] ) {
            $result = $wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $data['id']) );
        }else { 
            $wpdb->insert( CMCHK_TABLE_HOOK, $data );
            $result = $wpdb->insert_id ? $wpdb->insert_id : false;
        }
		$response['record'] = $data;
		$response['project_id'] = $data['project_id'];

		if( $result === false ){ 
			$response['success'] = false; $response['message'] = __( $wpdb->last_error, 'cmchk' );  
		}else if( $data['id'] > 0 ){
			$response['success'] = true; $response['id'] = $data['id']; $response['message'] = __( 'Update Successfull', 'cmchk' );        
		}else{
			$response['success'] = true; $response['id'] = $result; $response['message'] = __( 'Add Successfully', 'cmchk' ); 
			$response['is_new'] = true;
		}	
		
		return $response;		
	}

	/*
	*	Saves the code of a hook
	*/
	public static function code_save( $code, $id ){
		global $wpdb; $data = array(); $response = array(); 
		$data['code'] = wp_unslash( $code );
		$id = (int)sanitize_text_field( wp_unslash( $id ) );
		
		$result = $wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id) );
		
		if( $result === false){
			$response['success'] = false; $response['message'] = $wpdb->last_error;
		}else{
			$response['success'] = true; //$response['message'] = "Saved";
		}
		return $response;
	}
	
	
	/**
     *  Privately trash hook
    */
	public static function trash( $id ){
		global $wpdb; $response = array(); $data = array();  $hook_id = intval($id);
		$data['status'] = 'trash'; $data['active'] = 0;
				
		$wpdb->update( CMCHK_TABLE_HOOK, $data, array( 'id' => $hook_id ) );
		$response['success'] = true; //$response['message'] = "Successfull";
		return $response;
	}
		
	/**
     *  Privately trash hook
    */
	public static function hook( $id ){
		global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_HOOK." where id = %d", $id);
        $hook = $wpdb->get_row( $sql, 'ARRAY_A' );
		return $hook;
	}
			
	/**
     *  Privately trash hook
    */
	public static function get_hooks(){
		global $wpdb;		
        $sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where active != 0 and project_id = 0";
        $hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
		
		return $hooks;
	}		
		
	/**
     * Load Single hook
     * @param array  $hook hook-record
     */
    public static function load( $hook, $safe = true ){
		if( !is_array($hook) ){
			$hook = self::hook( $hook );
		}
		if( $safe ){
			if( $hook['active'] < 1 && (!is_array($hk_ids = explode(',',$_REQUEST['cmchk_safe'])) || !in_array($hook['id'], $hk_ids)) )return;
		}

		$fbody = $hook["code"];
        if( in_array( $hook['type'], array('filter', 'action')) ){
			$func = "add_$hook[type]";
            $func($hook['hookname'], function() use($fbody){
                cmchk::run_php( $fbody );
            }, $hook['priority'], $hook['args']);        
        }else if( $hook['type'] == 'file' ){
            cmchk::run_php( $fbody );
        }
    }
	
	/**
     * Load All hook
     * @param array  $hook hook-record
     */
    public static function load_all(){
		$hooks = cmchk_hook::get_hooks();
		$hooks = (array)apply_filters('cmchk_load_hook', $hooks);
		foreach($hooks as $r){
			cmchk_hook::load($r);
		} 
	}

	/**
     *  Export hooks
    **/
	public static function export( $ids ){
		global $wpdb; $projs = array();  $proj = array('id'=> 0); $id = implode(', ', $ids); 
		$sql = "SELECT * FROM `".CMCHK_TABLE_HOOK."` where id IN($id)";
		$proj['hooks'] = $wpdb->get_results( $sql, ARRAY_A ); $projs['0'] = $proj;	
		$projs = apply_filters('cmchk_export_projects', $projs);
		
		if( $projs === false ) return;
		$projs['meta'] = array(
			'ver'=>CMCHK_VERSION,
			'site_url'=> get_bloginfo('url'),
			'datetime'=> date('Y-m-d H:i:s'),
		);

		cmchk::output_file( json_encode($projs), 'wp_cmchk.json', 'application/json');
	}
	
	/**
     *  Deactivate hooks
    **/
	public static function deactivate( $id ){
		global $wpdb; $data['active'] = 0;
		$wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id, 'project_id'=>0) );
	}	
	
}