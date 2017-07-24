<?php

/*
package: cmc_hook
file: class-cmc-hook-actions.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class cmchk_actions{
	
	public static $debug = true;

	/**
     * Load activated hooks and projects used for both admin and frontend.
    */
    public static function load_hooks(){
        $on = cmchk::get_setting( 'run_hook_on', 'none' ); $run_hook = false;
        if( $on == 'both' ){
			$run_hook = true;
        }else if( $on == 'none' || ($on == 'backonly' && !is_admin()) || ($on == 'frontonly' && $on == !is_admin()) ){
			$run_hook == false;
        }			
        if( !$run_hook )return;
			
		cmchk_hook::load_all();		
        cmchk_project::load_all();
		
        do_action('cmchk_hooks_loaded');
    }

	/**
     *  Creates or Edit hooks with or without ajax
     */
    public static function hook_table_editor(){
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmc-hook-nonce' ) ) {
            if( defined('DOING_AJAX') && DOING_AJAX ){
				wp_send_json(array('success'=>false, 'message'=>'Invalid nonce'));
            }
            die( 'Cheating...' );
        }
		
        $response = cmchk_hook::editor( $_POST );		
		if( $response['success'] ){
			if( defined('DOING_AJAX') && DOING_AJAX ){
				unset( $response['message'] );
				if( $_REQUEST['tab'] == 'explorer' ){
					$response['url'] = "?page=cmc-hook&tab=explorer&id=".$response['id'];
				}else if( $response['project_id'] > 0){
					$response['url'] = "?page=cmc-hook&tab=project&section=project&id=".$response['project_id'];
                }else{
					$response['url'] = "?page=cmc-hook";
                }				
			}else{
				$message = $response['message'];
				add_action( 'admin_notices', function( $message ){
					$class = 'notice notice-success is-dismissible';
					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
                }); 
			}			
			do_action('cmchk_editor_save', $response['record'] );			
		}else{
			if( !defined('DOING_AJAX') || !DOING_AJAX ){
				$error = $response['message'];
                add_action( 'admin_notices', function() use ( $error ){
					$class = 'notice notice-error is-dismissible'; $message = __( 'An Error Occured', 'cmchk' );
					printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), esc_html( $message ), $error ); 
                });
			}
		}
		
        if( defined('DOING_AJAX') && DOING_AJAX ){
            //if( $response['is_new'] ){
				ob_start();
					cmchk::include_file( CMCHK_DIR."pages/sections/hook_attributes.php", array('hook_id'=>$response['id']) );				
				$response['replace'] = ob_get_clean();
            //}
            wp_send_json( $response );
        }else{
			wp_redirect( self::current_url() );
			exit();
		}
    }
	
	/**
     *  Trash a hook
    */
	public static function trash_hook(){
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
		if ( ! wp_verify_nonce( $nonce, 'cmchk-hook-attr-trash-hook' ) ) {
		  die( 'Go get a life script kiddies' );
		}
		else {
			global $wpdb; $hook_id = intval( $_REQUEST['id'] );
			$sql = $wpdb->prepare( "SELECT project_id FROM ".CMCHK_TABLE_HOOK." WHERE id = %d", $hook_id);
			$proj_id = $wpdb->get_var( $sql );
			
			require_once( CMCHK_DIR_INCLUDE.'class-cmchk-hook.php');			
			$response = cmchk_hook::trash( $hook_id );
			if( $response['success'] ){
				$sql = $wpdb->prepare("SELECT id FROM ".CMCHK_TABLE_HOOK." WHERE project_id = %d and status != 'trash'", $proj_id);
				$hook_id_new = $wpdb->get_var( $sql );
				$response['hook_id'] = $hook_id_new;
				$response['redirect'] = "?page=cmc-hook&tab=explorer&id=$hook_id_new";
			}
			wp_send_json( $response);
			exit;
		}
	}
	
	/*
	*	Saves the code of a hook
	*/
	public static function hook_code_save(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmchk-hook-code-save' ) ) {
            if( defined('DOING_AJAX') && DOING_AJAX ){
				wp_send_json(array('success'=>false, 'message'=>'Invalid nonce'));
            }
            die( 'Cheating...' );
        }
		require_once( CMCHK_DIR_INCLUDE.'class-cmchk-hook.php');			
		$response = cmchk_hook::code_save( $_REQUEST['code'], $_REQUEST['id'] );
		wp_send_json($response);
		exit();
	}
	
	/**
    *  Creates and Edit projects with or without ajax
    */
    public static function hook_project_table_editor(){
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmc-hook-project-nonce' ) ) {
            if( defined('DOING_AJAX') && DOING_AJAX ){
				wp_send_json(array('success'=>false, 'message'=>'Invalid nonce'));
            }
            die( 'Cheating...' );
        }
       
		require_once( CMCHK_DIR_INCLUDE.'class-cmchk-project.php');	
        $response = cmchk_project::editor( $_POST );
		if( $response['success'] ){
			global $wpdb; $proj = $response['record'];
			if( $response['is_new'] ){
				require_once( CMCHK_DIR_INCLUDE.'class-cmchk-hook.php');	
				$hook_resp = cmchk_hook::editor( array('title'=>$proj['title'], 'type'=>'file', 'project_id'=>$response['id'], 'active'=> 1) );
				if( $hook_resp['success'] ){
					$wpdb->update( CMCHK_TABLE_PROJECT, array('file_run'=>$hook_resp['id']), array('id'=> $response['id']) );
				}
			}						
			if( defined('DOING_AJAX') && DOING_AJAX ){
				unset( $response['message'] );
				if( $_REQUEST['tab'] == 'explorer' ){
					$response['url'] = '?page=cmc-hook&tab=explorer&id='.$hook_resp['id'];
				}else{
					$response['url'] = '?page=cmc-hook&tab=project&section=project&id='.$response['id'];
				}				
			}else{
				$message = $response['message'];
				add_action( 'admin_notices', function($message){
					$class = 'notice notice-success is-dismissible'; 
					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
                }); 
			}		
			do_action('cmchk_project_editor_save', $data);
		}else{
			if( !defined('DOING_AJAX') || !DOING_AJAX ){
				$error = $response['message'];
				add_action( 'admin_notices', function() use ($error){
					$class = 'notice notice-error is-dismissible'; $message = __( 'An Error Occured', 'cmchk' );
					printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), esc_html( $message ), $error ); 
                });
			}
		}
		
        if( defined('DOING_AJAX') && DOING_AJAX ){
            //if( $response['id'] > 0 ){
                ob_start();
					cmchk::include_file( CMCHK_DIR."pages/sections/project_attributes.php", array('proj_id'=>$response['id']) );
					//require("pages/sections/project_attributes.php");
                $response['replace'] = ob_get_clean();
            //}
            wp_send_json( $response);
        }else{
			wp_redirect( self::current_url() );
			exit();
		}
    }

	/**
	*  Trash a project
    */
	public static function trash_project(){
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
		if ( ! wp_verify_nonce( $nonce, 'cmchk-project-attr-trash-proj' ) ) {
		  die( 'Go get a life script kiddies' );
		}
		global $wpdb; $proj_id = intval( $_REQUEST['id'] );
		$response = cmchk_project::trash( $proj_id );
		if( $response['success'] ){
			if( $_REQUEST['tab'] == 'explorer' ){
				$response['redirect'] = "?page=cmc-hook&tab=explorer";
			}else{
				$response['redirect'] = "?page=cmc-hook&tab=project";
			}
			
		}
		wp_send_json( $response);
		exit;
		
	}
		
    /**
     * Remotely Deactivate hook 
     * If ids are provided Then the hooks with those ids will be deactivated
     * If no id is provided, run hook on  is set to none so no hook runs
     * The id provided should be one or comma separated multiple e.g. 1,2,3
     */
    public static function remote_deactivate_hook(){
		$neg = $_REQUEST['cmchk_neg'];  
        if( empty($neg) || !cmchk::get_setting('deactivate_remote') || $neg != cmchk::get_setting('deactivate_remote_token') )return;
        global $wpdb;
		if( !empty( $_REQUEST['cmchk_id'] )  ){
            $ids = explode(',', $_REQUEST['cmchk_id'] );
            foreach($ids as $id){
				$data['active'] = 0;
				$wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id, 'project_id'=>0) );
            }	
        }
		if( !empty( $_REQUEST['cmchk_pid'] )  ){
            $ids = explode(',', $_REQUEST['cmchk_pid'] );            
            foreach($ids as $id){
				$data['active'] = 0;
				$wpdb->update( CMCHK_TABLE_PROJECT, $data, array('id'=> $id, 'project_id'=>0) );
            }	
        }
		
		if( !empty($_REQUEST['cmchk_run_on']) && in_array( $_REQUEST['cmchk_run_on'], array( 'backonly', 'none')) ){
			//global $cmchk_settings_default;
			//$data = get_option( CMCHK_SETTINGS, $cmchk_settings_default );
            //$data['run_hook_on'] = $_REQUEST['cmchk_run_on'];
            //update_option( CMCHK_SETTINGS, $data);
			$data = array();
			$data['run_hook_on'] = $_REQUEST['cmchk_run_on'];
			cmchk::set_setting( $data );
        }
    }
	
	
	public static function create_folder(){
		
	}	
		
     /**
     *  handles bulk Hook actions
     */
    public static function hook_bulk_action(){
		
    }

    /**
     *  Save hook settings
     */
    public static function settings_save(){   		
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmc-hook-settings-nonce' ) ) {
			die( 'Cheating...' );
        }
        $response = self::_settings_save( $_POST );     

        do_action('cmchk_settings_save', $data);
    }
	
	/**
     *  Internally Save hook settings
     */
	private static function _settings_save( $model = array() ){
		$data = array(); $response = array();
        $data['run_hook_on'] = sanitize_text_field( wp_unslash( $model['run_hook_on'] ) );
        $data['deactivate_remote_token'] = sanitize_text_field( wp_unslash( $model['deactivate_remote_token'] ) );
		$data['codemirror_theme'] = sanitize_text_field( wp_unslash( $model['codemirror_theme'] ) );
		$data['allowed_users'] = wp_unslash( $model['allowed_users'] );
        $data['deactivate_remote'] = isset( $model['deactivate_remote'] ) ? 1 : 0; 
        $data['del_table_uninstall'] = isset( $model['del_table_uninstall'] ) ? 1 : 0; ;
        $data['del_opt_uninstall'] = isset( $model['del_opt_uninstall'] ) ? 1 : 0; ;         
        $data['enable_codemirror'] = isset( $model['enable_codemirror'] ) ? 1 : 0; 
		
		$data = apply_filters( 'cmchk_settings_data_save', $data);
        if( $data === false )return false;

        update_option('cmc_hook_settings', $data);
		$response['success'] = true; $response['message'] = "Saved Successfully";
		return $response;
	}
	
	/**
     *  Export Projects
     */
    public static function export( $proj_id ){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmchk-project-export-nonce' ) ) {
			die( 'Cheating...' );
        }
		
		cmchk_project::export( $proj_id );
    }
	
	/**
     *  Export hooks
    **/
	public static function export_hook(){
		global $wpdb; $ids = esc_sql( $_POST['bulk-items'] ); 
		cmchk_hook::export( $ids );
	}
	
	/**
     *   Import hooks from a project
    **/	
	public static function import(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmc-hook-import-nonce' ) ) {
			die( 'Cheating...' );
        }
		if( empty($_FILES['cmchk_file_import']) )return;
		
		$file = $_FILES['cmchk_file_import']['tmp_name'];
		cmchk_project::import_from_file( $file );
		
		wp_redirect( cmchk::current_url() );
		exit();
	}
	
    /**
     *  Creates Plugin from project
     */
    public static function hook_project_create_plugin(){
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmchk-create-plugin-nonce' ) ) {
			die( 'Cheating...' );
        } 
        
		$id = intval($_REQUEST['id']);
		cmchk_project::create_plugin( $id );
    }
	
	/*
	*	Generate project explorer list
	*/
	public static function project_explorer(){
		$dir = urldecode($_POST['dir']);
		$proj_id = $_REQUEST['proj']; $hook_id = $_REQUEST['id'];
		cmchk_explorer::project_explorer( $dir, $proj_id, $hook_id );
	}
	

}