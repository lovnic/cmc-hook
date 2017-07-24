<?php

/*
package: cmc_hook
file: class-cmc-hook-actions.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class cmchk_project{
	
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
			$data['project_id'] = !empty($model['cmchk_proj'])? $model['cmchk_proj'] : -1;	
			$data['slug'] = cmchk::get_slug( CMCHK_TABLE_PROJECT, $data['id'], $data['title'], $data['project_id'] ) ;
        }		
        $data['description'] = isset($model['description'])? wp_unslash( $model['description'] ): "";
        $data['datetimeupdated'] = date('Y-m-d H:i:s');
		$data['active'] = empty( $model['active'] ) ? 0 : ($model['active'] < 1 ? -1: 1);
		$data['status'] = 'publish';
        $data['file_run'] = !empty($model['file_run'])? $model['file_run'] : 0;	

        $data = apply_filters( 'cmchk_project_editor_data_save', $data);
        if( $data === false ) return;

        if( $data['id'] ) {
            $result = $wpdb->update( CMCHK_TABLE_PROJECT, $data, array('id'=> $data['id']) );
        }else { 
            $wpdb->insert( CMCHK_TABLE_PROJECT, $data );
            $result =  $result = $wpdb->insert_id ? $wpdb->insert_id : false;
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
	
	/**
     *  Get the main file of every project
    */
	public static function get_all_main_files(){
		global $wpdb;
		$sql = "SELECT h.*, p.active pactive FROM `".CMCHK_TABLE_PROJECT. 
        "` p inner join `".CMCHK_TABLE_HOOK."` h on p.file_run = h.id where p.active != 0 and h.project_id > 0 ";
        $projs = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $projs;
	}
	
	/**
     *  Get the main file of every project
    */
	public static function get_filters_and_action( $id ){
		global $wpdb;
		$sql = $wpdb->prepare("SELECT * FROM `".CMCHK_TABLE_HOOK."` where project_id = %d and type IN('filter', 'action') and active = 1", $id );
		$hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $hooks;
	}
	
	/**
     *  Trash project
    */
	public static function trash( $id ){
		global $wpdb; $response = array(); $data = array();  $proj_id = intval($id);
		$data['status'] = 'trash'; $data['active'] = 0;

		$wpdb->update( CMCHK_TABLE_PROJECT, $data, array( 'id' => $proj_id ) );
		$response['success'] = true; //$response['message'] = "Successfull";
		return $response;
	}
	
	/**
     *  Load project
    */	
	public static function load( $file, $safe = true ){
		if( $safe ){
			if( $file['active'] < 1 && (!is_array($cmchk = explode(',',$_REQUEST['cmchk_safe_proj'])) || !in_array($file['project_id'], $cmchk)) )return;
		}		
		$filters = (array)cmchk_project::get_filters_and_action( $file['project_id'] );
		foreach($filters as $filter){
			cmchk_hook::load($filter);
		}
		cmchk_hook::load( $file );
	}
	
	/**
     *  Load All project
    */	
	public static function load_all(){
		$projs = cmchk_project::get_all_main_files();
		$projs  = (array)apply_filters('cmchk_load_project', $projs );
		foreach( $projs as $proj ){
			cmchk_project::load( $proj );
		}   
	}
    
	/**
     *   Import hooks from a project
    **/	
	public static function import_from_file( $file ){
		$projs = file_get_contents( $file );		
		$projs = json_decode($projs, true);
		$meta = $projs['meta']; unset($projs['meta']);
		$response = cmchk_project::import( $projs, $meta );
		
		return $response;
	}
	
	/**
     *   Import hooks from a project
    **/	
	public static function import( $projs, $meta){
		global $wpdb; $response = array();
		foreach($projs as $proj){ 
			$hooks = $proj['hooks']; $run_hook = $proj['file_run']; 
			if( $proj && $proj['id'] > 0){				
				unset( $proj['id'] ); unset($proj['hooks']); unset($proj['file_run']);
				$proj['active'] = 0; $proj['slug'] = cmchk::get_slug( CMCHK_TABLE_PROJECT, 0, $proj['slug'], 0 );;
				$proj = apply_filters('cmchk_import_project', $proj);
				if( $proj === false ) continue;
				$wpdb->insert( CMCHK_TABLE_PROJECT, $proj );
				$proj_id = $wpdb->insert_id;
			}else{
				$proj_id = 0; $proj = apply_filters('cmchk_import_project', $proj);
			}
			foreach($hooks as $h){
				$id = $h['id']; unset($h['id']); $h['project_id'] = $proj_id;
				if( $proj_id == 0 ){ 
					$h['active'] = 0; 
					$h['slug'] = cmchk::get_slug( CMCHK_TABLE_HOOK, 0, $h['slug'], 0 );
				}
				$wpdb->insert( CMCHK_TABLE_HOOK, $h );
				if( $run_hook == $id ) $run_hook = $wpdb->insert_id;
			}
			if( $proj_id > 0)
				$wpdb->update( CMCHK_TABLE_PROJECT, array('file_run'=>$run_hook), array('id'=> $proj_id) );
		}
		
		$response['result'] = true; $response['message'] = "Import successfull";
		return $response;
	}
    
	/**
     *  Export Projects
     */
	public static function export( $proj_id ){
		global $wpdb; $ids = array();
		if( $proj_id == 'all'){
			$ids = $wpdb->get_col("SELECT id FROM ".CMCHK_TABLE_PROJECT." where project_id = -1 "); array_unshift($ids, 0);
		}else if( is_numeric($proj_id) ){
			$ids = array($proj_id);
		}else if( is_array($proj_id) ){
			$ids = $proj_id;
		}
		$projs = cmchk_project::export_with_id( $ids );
		if( $projs === false ) return;
        
		cmchk::output_file( json_encode($projs), 'wp_cmchk.json', 'application/json');
	}
	
	/**
     *  Export Projects
     */
	public static function export_with_id($ids){
		global $wpdb; $ids = (array)$ids; $projs = array();		 
		foreach($ids as $id){
			if( !empty( $id ) ){
				$sql = $wpdb->prepare( "select * from `".CMCHK_TABLE_PROJECT."` where id = %d", intval($id) );
				$proj = $wpdb->get_row($sql, ARRAY_A);
			}
			$proj['id'] = empty( $proj['id'] )? 0 : $proj['id'];
			$sql = $wpdb->prepare( "select * from `".CMCHK_TABLE_HOOK."` where project_id = %d", $proj['id'] );
			$proj['hooks'] = $wpdb->get_results($sql, ARRAY_A);  $projs[] = $proj;	
		}
		$projs['meta'] = array(
			'ver'=>CMCHK_VERSION,
			'site_url'=> get_bloginfo('url'),
			'datetime'=> date('Y-m-d H:i:s'),
		);
		$projs = apply_filters('cmchk_export_projects', $projs);
		
		return $projs;
	}
	
	 /**
     *  Creates Plugin from project
     */
    public static function create_plugin( $hook_id ){      
        global $wpdb; $data = array();
		$sql = $wpdb->prepare( "SELECT p.*, h.slug as h_slug FROM `".CMCHK_TABLE_PROJECT."` ".
		" p left join `".CMCHK_TABLE_HOOK."` h on p.file_run = h.id where p.id = %d", $hook_id );
        //$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cmc_hook_project where id = %d", $_REQUEST['cmchk_id']);
        $proj = $wpdb->get_row( $sql, 'ARRAY_A' );
        if( !$proj )return;

        $out .= "<?php \n";
        $out .= "/**\n This file contain all the filters and actions created\n by cmc-hook in the project - $proj[title] \n**/\n\n";
        $sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where type IN('filter', 'action') and active = 1 and project_id = $proj[id]";
        $hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
		$hooks = apply_filters('cmchk_create_plugin_hooks', $hooks);
        if( is_array($hooks)){
			foreach( $hooks as $h){
				$h['code'] = cmchk::replacelive( $h['code'] );
				$out .= "add_$h[type]('$h[hookname]', function(){ ?>\n$h[code]\n<?php }, $h[args], $h[priority]); \n\n\n";
			}
        }
        $out .= "\n?>";
        is_dir( CMCHK_DIR_ZIP ) || mkdir( CMCHK_DIR_ZIP ); 
        $temp_file = CMCHK_DIR_ZIP.$proj['slug'].'.zip';
        if( file_exists($temp_file) ) unlink($temp_file); $zip = new ZipArchive(); $zip->open( $temp_file, ZipArchive::CREATE );	
        set_time_limit ( 200 );
        $zip->addFromString($proj['slug'].'/'.'hooks.php', $out);			
        $out = "";

        $pluginvars = array('pluginname'=>'Plugin Name', 'pluginurl'=>'Plugin URI', 'descirption'=>'Description', 'version'=>'Version', 'author'=>'Author',
        'authorurl'=>'Author URI', 'license'=>'License', 'licenseurl'=>'License URI', 'textdomain'=>'Text Domain');
        $pluginvars = apply_filters( 'cmchk_export_plugin_info', $pluginvars );
		$plugininfo = "";
		
        foreach( $pluginvars as $k => $v ){
			if( !empty($_POST[$k]) ) $plugininfo .= "\n$v: ".str_replace("\n", ' ', $_POST[$k]);
        }
		if( empty($_POST['addfields']) ){
			$plugininfor .= "\n$_POST[addfields]";
		}
        $plugininfo = "<?php\n /**".$plugininfo."\n**/ \n\ninclude('cmchk_function.php');\ninclude('hooks.php');\n";
		/** $plugininfo = $plugininfo . "add_action('plugins_loaded', function(){\n\tinclude('$proj[h_slug].php');\n});\n ?>\n\n"; **/
		//$zip->addFromString( $proj['slug'].'/cmchk.php', $plugininfo );
		$a = $zip->addFile( CMCHK_DIR_INCLUDE."functions_live.php", $proj['slug'].'/cmchk_function.php');
        $sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where type = 'file' and project_id = $proj[id]";
        $hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
		$hooks = apply_filters('cmchk_create_plugin_file_hooks', $hooks);
        if( is_array($hooks) ){
			foreach($hooks as $h){
				$out = $h['code']; $out = cmchk::replacelive( $out );
				if( $proj['file_run'] == $h['id'] ){
					if( cmchk::startsWith("<?php", ltrim( $h['code'] )) ){
						$out = preg_replace('/^' . preg_quote('<?php', '/') . '/', '', $out);
						$out = $plugininfo ."\n". $out;
					}else{
						$out = $plugininfo. "?>\n". $out;
					}
					
				}
				$zip->addFromString($proj['slug'].'/'.$h['slug'].'.php', $out);		
			}
        }
        $zip->close();
        $out = file_get_contents( $temp_file ); $fname = 'wp_'.$proj['slug'].'.zip';
		unlink( $temp_file );
        cmchk::output_file($out, $fname, 'application/zip' );
		exit;
    }
    
	/**
     *  Deactivate Projects
    **/
	public static function deactivate( $id ){
		global $wpdb; $data['active'] = 0;
		$wpdb->update( CMCHK_TABLE_PROJECT, $data, array('id'=> $id, 'project_id'=>0) );
	}	
	
}