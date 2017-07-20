<?php 
/*
Plugin Name: cmc-hook
Description: Register php functions to hooks(action and filter), run php codes safely, create and quickly test plugins all from dashboad tools
Version: 1.0.5
Author: Evans Edem Ladzagla
Author URI: https://profiles.wordpress.org/lovnic/
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: cmchk

@package cmc-hook
@category Core
@author Evans Edem Ladzagla
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Main cmc_hook Class.
 *
 * @class cmc_hook
 * @version	1.0.0
 */
final class cmc_hook{
    
    /**
     * The single instance of the class.
     *
     * @var cmc_hook
     */
    public static $_instance = null;
	
    /**
     * Hooks instance
     *
     * @var cmc_hook_List.
     */
    public static $hooks;
	
    /**
     * Projects instance
     * 
     * @var cmc_hook_project_List.
     */
    public static $projects;

    /**
     * Admin Page Url.
     *
     * @var string
     */
    public static $menu;

    /**
     * Main cmc_hook Instance.
     *
     * Ensures only one instance of cmc_hook is loaded or can be loaded.
     *
     * @static
     * @return cmc_hook - Main instance.
     */
    public static function instance(){
        if( self::$_instance == null ){
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * cmc_hook Constructor.
     */
    function __construct(){
        self::constants(); self::includes();
        if( !empty($_REQUEST['cmchk_neg']) )self::remote_deactivate_hook();         
		add_action( 'plugins_loaded', array( __CLASS__, 'load_hooks' ), 11 );
		add_action( 'plugins_loaded', array( __CLASS__, 'init'));
		add_action( 'plugins_loaded', array( __CLASS__, 'init2'), 12);
        add_shortcode('cmchksh', array(__CLASS__, 'shortcode'));
        register_activation_hook( __FILE__, array( __CLASS__, 'plugin_activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'plugin_deactivate' ) );        	
    }
	
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
    * Init cmc-hook when WordPress Initialises.
	* Runs before hooks are loaded
	* Providing hooks to change their content before they run
	* Allow other plugins to hook into it
    */
	public static function init(){
		if( is_admin() ){
			if( self::is_user_allowed() ){
				$action = !empty($_REQUEST['action'])?$_REQUEST['action']:$_REQUEST['action2'];
				if( $_REQUEST['cmchk_action'] == 'hook_editor' || $action == 'cmchk_hook_editor' )self::hook_table_editor();
				if( $_REQUEST['cmchk_action'] == 'trash_hook' )self::trash_hook();
				if( $_REQUEST['cmchk_action'] == 'trash_project' )self::trash_project();
				if( $_REQUEST['cmchk_action'] == 'hook_code_save' )self::hook_code_save();
				if( $action == 'create_folder' )self::create_folder();
				if( $_REQUEST['cmchk_action'] == 'project_editor' || $action == 'cmchk_project_editor' )self::hook_project_table_editor();
			}
		}
	} 
	
    /**
    * Init cmc-hook when WordPress Initialises.
	* Runs after hooks are loaded
	* Providing hooks to add actions and filters for them
    */
    public static function init2(){				
        if( is_admin() ){
            if( self::is_user_allowed() ){
				$action = !empty($_REQUEST['action'])?$_REQUEST['action']:$_REQUEST['action2'];
				if( defined('DOING_AJAX') && DOING_AJAX ){
                    add_action( 'wp_ajax_cmchk_slug', array(__CLASS__, 'save_slug') );					
                }else{	
					if( isset($_REQUEST['cmchk_action']) && !empty($_REQUEST['cmchk_action']) ){
						switch( $_REQUEST['cmchk_action'] ){
							case 'create_plugin': self::hook_project_create_plugin(); break;
							case 'export': self::export( $_REQUEST['id'] ); break;
							case 'import': self::import(); break;
							case 'jfiletree':self::project_explorer(); break;
						}
					}
					if( !empty($action) ){
						switch( $action ){
							case 'cmchk-hook-bulk-export': self::export_hook(); break;
							case 'cmchk-project-bulk-export': self::export($_POST['bulk-items']); break;
						}
					}
                }      
                add_filter( 'set-screen-option', function($status, $option, $value ){
					return $value;
				}, 10, 3 );
                add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );	
            }                     
        }		
    }
	
    /**
     * Load activated hooks and projects used for both admin and frontend.
     */
    public static function load_hooks(){
        $on = self::get_setting( 'run_hook_on', 'none' ); $run_hook = false;
        if( $on == 'both' ){
			$run_hook = true;
        }else if( $on == 'none' || ($on == 'backonly' && !is_admin()) || ($on == 'frontonly' && $on == !is_admin()) ){
			$run_hook == false;
        }			
        if( !$run_hook )return;
		
        global $wpdb;		
        $sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where active != 0 and project_id = 0";
        $hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
		$hooks = apply_filters('cmchk_load_hook', $hooks);
        if( is_array( $hooks ) ){ 
            foreach($hooks as $r){
                self::_load_hook($r);
            }            
        }
		
        $sql = "SELECT h.*, p.active pactive FROM `".CMCHK_TABLE_PROJECT. 
        "` p inner join `".CMCHK_TABLE_HOOK."` h on p.file_run = h.id where p.active != 0 and project_id = -1 ";
        $projs = $wpdb->get_results( $sql, 'ARRAY_A' );
		$projs  = apply_filters('cmchk_load_project', $projs );
        if( is_array( $projs ) ){ 
            foreach($projs as $r){
                if( $r['active'] < 1 && (!is_array($cmchk = explode(',',$_REQUEST['cmchk_safe_proj'])) || !in_array($r['id'], $cmchk)) )continue;
				$sql = "SELECT * FROM `".CMCHK_TABLE_HOOK."` where parent_id = $r[parent_id] and type IN('filter', 'action') and active = 1";
				$actfilters = $wpdb->get_results( $sql, 'ARRAY_A' );
				if( is_array($actfilters) ){
					foreach($actfilters as $a){
						self::_load_hook($a);
					}
				}
				self::_load_hook($r);
            }            
        }
		do_action('cmchk_plugins_loaded');
        do_action('cmchk_hook_loaded');
    }
	
    /**
     * Load Single hook
     * @param hook-record $r
     */
    public static function _load_hook( $r ){
		if( $r['active'] < 1 && (!is_array($cmchk = explode(',',$_REQUEST['cmchk_safe'])) || !in_array($r['id'], $cmchk)) )return;
        
        $fbody = $r["code"];
        if( in_array( $r['type'], array('filter', 'action')) ){
			$hook = "add_$r[type]";
            $hook($r['hookname'], function() use($fbody){
                self::run_php( $fbody );
            }, $r['priority'], $r['args']);        
        }else if( $r['type'] == 'file' ){
            self::run_php( $fbody );
        }
    }
	
    /**
     * Remotely Deactivate hook 
     * If ids are provided Then the hooks with those ids will be deactivated
     * If no id is provided, run hook on  is set to none so no hook runs
     * The id provided should be one or comma separated multiple e.g. 1,2,3
     */
    public static function remote_deactivate_hook(){
		$neg = $_REQUEST['cmchk_neg'];  
        if( (!self::get_setting('deactivate_remote') || $neg != self::get_setting('deactivate_remote_token')) )return;
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
			global $cmchk_settings_default;
			$data = get_option( 'cmc_hook_settings', $cmchk_settings_default );
            $data['run_hook_on'] = $_REQUEST['cmchk_run_on'];
            update_option('cmc_hook_settings', $data);			
        }
    }
	
    /**
     * Short code to run hook 
     * [cmchk id='1']
	 * [cmchk slug='hook1']
	 * Runs only for hooks without a project
     * 
     * @param array $attr 
     */
    public static function shortcode( $attr ){		
        global $wpdb;
        if( !empty($attr['id']) ){
            $model = $wpdb->get_row( $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE id = %d where active = 1 and project_id = 0 and shortcode = 1", esc_sql($attr['id'])), ARRAY_A );
        }else if( !empty($attr['slug']) ){
            $model = $wpdb->get_row( $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE slug = %s where active = 1 and project_id = 0 and shortcode = 1", esc_sql($attr['slug'])), ARRAY_A );
        }
        $model = apply_filters('cmchk_shortcode', $model, $attr );
		if( $model === false )return;
        if( empty($model) ){
			self::run_php( $model['code'] );
        }		
    }

    /**
     * Loads Admin Menu 
     * Page cmc-hook is added to Tools
     */
    public static function admin_menu(){
        $hook = add_management_page( __('CMC Hook', 'cmchk'), __('CMC Hook', 'cmchk'), 'manage_options', 'cmc-hook', function(){
			require("pages/admin.php");
        }); 
        self::$menu = menu_page_url('cmc-hook', false);
        add_action( "load-$hook", array(__CLASS__, "menu_load"));		
    }

    /**
     * On Admin Menu load this function run
     */
    public static function menu_load(){ 
        if( !empty($_REQUEST['cmchk_action']) ){
			switch( $_REQUEST['cmchk_action'] ){
				case 'hook_settings': self::hook_settings_save(); break;
			}
        }

        if( (empty($_REQUEST['tab']) && empty($_REQUEST['section'])) || ($_REQUEST['section'] == 'project') ){
			require("include/class-cmc-hook-table.php");
			$option = 'per_page';
			$args   = [
					'label'   => 'Hooks',
					'default' => 5,
					'option'  => 'hooks_per_page'
			];
			add_screen_option( $option, $args );	
			self::$hooks = new cmc_hook_List(); 
			self::$hooks->process_bulk_action();
        }

        if( $_REQUEST['tab'] == 'project' && empty( $_REQUEST['section']) ){
			require("include/class-cmc-hook-project-table.php");
			$option = 'per_page';
			$args   = [
					'label'   => 'Projects',
					'default' => 5,
					'option'  => 'projects_per_page'
			];
			add_screen_option( $option, $args );	
			self::$projects = new cmc_hook_project_List(); 
			self::$projects->process_bulk_action();
        }

        if( self::get_setting('enable_codemirror', true) && $_REQUEST['tab'] == 'explorer' )
			self::codemirror_script ();

		wp_enqueue_script( 'jquery' );
		//wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'jquery-ui-tabs' );
        wp_enqueue_script( 'main_js', CMCHK_URL_JS.'main.js', array('jquery') );
        wp_enqueue_script( 'into_js', CMCHK_URL_JS.'intro/intro.js', array('jquery') );
		wp_enqueue_script( 'tiptip_js', CMCHK_URL_JS.'tiptip/jquery.tipTip.js', array('jquery') );
		wp_enqueue_script( 'jqueryFileTree_js', CMCHK_URL_JS.'jqueryFileTree/jqueryFileTree.js', array('jquery') );
		
		wp_enqueue_style( 'jquery-ui_css', CMCHK_URL_CSS.'jquery-ui/jquery-ui.css' );
        wp_enqueue_style( 'intro_css', CMCHK_URL_CSS.'intro/introjs.css' );
		wp_enqueue_style( 'font_font-awesome_css', CMCHK_URL_CSS.'font-awesome/css/font-awesome.min.css' );
		wp_enqueue_style( 'tiptip_css', CMCHK_URL_JS.'tiptip/tiptip.css' );
		wp_enqueue_style( 'jqueryFileTree_css', CMCHK_URL_JS.'jqueryFileTree/jqueryFileTree.css' );
        wp_enqueue_media();
    }

    /**
     * Loads sytles and script for codemirror editor
     */
    public static function codemirror_script(){
        wp_enqueue_script( 'codemirror_js', CMCHK_URL_JS.'codemirror/codemirror.js', array('jquery') );            
        wp_enqueue_script( 'codemirror_xml_js', CMCHK_URL_JS.'codemirror/mode/xml/xml.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_javascript_js', CMCHK_URL_JS.'codemirror/mode/javascript/javascript.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_css_js', CMCHK_URL_JS.'codemirror/mode/css/css.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_htmlmixed_js', CMCHK_URL_JS.'codemirror/mode/htmlmixed/htmlmixed.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_clike_js', CMCHK_URL_JS.'codemirror/mode/clike/clike.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_php_js', CMCHK_URL_JS.'codemirror/mode/php/php.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_searchcursor_js', CMCHK_URL_JS.'codemirror/util/searchcursor.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_matchbrackets_js', CMCHK_URL_JS.'codemirror/addon/edit/matchbrackets.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_matchbtags_js', CMCHK_URL_JS.'codemirror/addon/edit/matchtags.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_foldcold_js', CMCHK_URL_JS.'codemirror/addon/fold/foldcode.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_foldgutter_js', CMCHK_URL_JS.'codemirror/addon/fold/foldgutter.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_bracefold_js', CMCHK_URL_JS.'codemirror/addon/fold/brace-fold.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_xmlfold_js', CMCHK_URL_JS.'codemirror/addon/fold/xml-fold.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_indentfold_js', CMCHK_URL_JS.'codemirror/addon/fold/indent-fold.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_fold_markdownfold_js', CMCHK_URL_JS.'codemirror/addon/fold/markdown-fold.js', array('codemirror_js') );
		wp_enqueue_script( 'codemirror_selection_active-line_js', CMCHK_URL_JS.'codemirror/addon/selection/active-line.js', array('codemirror_js') );
        wp_enqueue_script( 'codemirror_search_annotatescrollbar_js', CMCHK_URL_JS.'codemirror/addon/search/annotatescrollbar.js', array('codemirror_js') );
		wp_enqueue_script( 'codemirror_search_matchesonscrollbar_js', CMCHK_URL_JS.'codemirror/addon/search/matchesonscrollbar.js', array('codemirror_js') );
		wp_enqueue_script( 'codemirror_search_searchcursor_js', CMCHK_URL_JS.'codemirror/addon/search/searchcursor.js', array('codemirror_js') );
		wp_enqueue_script( 'codemirror_search_match-highlighter_js', CMCHK_URL_JS.'codemirror/addon/search/match-highlighter.js', array('codemirror_js') );
		
        wp_enqueue_script( 'codemirror_ui_js', CMCHK_URL_JS.'codemirror/ui/codemirror-ui.js', array('codemirror_js') );

        wp_enqueue_style( 'codemirror_css', CMCHK_URL_CSS.'codemirror/codemirror.css');
		$theme = self::get_setting('codemirror_theme', '');
		if( !empty($theme) ){
			wp_enqueue_style( 'codemirror_theme_css', CMCHK_URL_CSS.'codemirror/theme/'.$theme.'.css');
		}
	    wp_enqueue_style( 'codemirror_fold_css', CMCHK_URL_JS.'codemirror/addon/fold/foldgutter.css');
        wp_enqueue_style( 'codemirror_ui_css', CMCHK_URL_CSS.'codemirror/ui/codemirror-ui.css');
		
	}

	/*
	*	Generate project explorer list
	*/
	public static function project_explorer(){
		global $wpdb; $_POST['dir'] = urldecode($_POST['dir']); $active_proj = '';
		$matches = explode("/", rtrim( $_POST['dir'], '/\\') ); $proj_id = $_REQUEST['proj']; $hook_id = $_REQUEST['id'];
		$dir_id = reset($matches); $base_hook_id = end($matches);

		if( $dir_id == -2 ){
			$base_proj = array('0'=>'Hooks', '-1'=>'Projects'); $base_proj_current = ($proj_id > 0)? -1 : 0;
			$ul = "<ul class='jqueryFileTree' style='display: none;' >";
			foreach( $base_proj as $k => $v){
				$current = ( $k === $base_proj_current )? 'cmchk-current-project':'';
				$collapsed = ( $k === $base_proj_current )? 'expanded':'collapsed';
				$ul .= "\n\t<li class='directory $collapsed $current '><a href='#' rel='$k/' >$v";
				if( $collapsed == 'expanded' ){
					$ul .= ( $proj_id > 0)? self::proj_exp_projects($proj_id, $hook_id): self::proj_exp_hooks( $proj_id, $hook_id );
				}		
				$ul .= "</a></li>";
			}			
			$ul .= "\n</ul>";	
			echo $ul;
		}else if(  $dir_id == -1 ){
			echo self::proj_exp_projects( $proj_id, $hook_id );
		}else if( $dir_id > -1 ){	
			echo self::proj_exp_hooks( $dir_id, $hook_id );
		}		
		exit();
	}
	
	/*
	*	Project Explorer Project list
	*
	* 	@param int $proj_id selected project id
	*	@param int $hook_id	selected hook id
	*/
	private static function proj_exp_projects( $proj_id, $hook_id ){
		global $wpdb; $sql = "SELECT * FROM `".CMCHK_TABLE_PROJECT."` where status != 'trash' and project_id = -1 ";				
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );				
		if( $result !== false ){
			$ul = "<ul class='jqueryFileTree' style='display: none;'>";
			$li = array();
			foreach( $result as $r ){ 
				$current = ( $r['id'] === $proj_id )? 'cmchk-current-project':'';
				$collapsed = ( $r['id'] === $proj_id )? 'expanded':'collapsed';
				$lia = "<li class='directory $collapsed $current '><a href='#' rel='$r[id]/' >".htmlentities($r['title']); 
				if( $r['id'] == $proj_id ){
					$lia .= self::proj_exp_hooks( $proj_id, $hook_id);
				}
				$lia .= "</a></li>";	
				( $r['id'] === $proj_id )? ( array_unshift( $li, $lia) ) : ($li[] = $lia);
			}
			$ul .= implode("\n\t", $li);
			$ul .= "\n</ul>";	
			return $ul;
		}
	}
	
	/*
	*	Project Explorer hook list in a project
	*
	* 	@param int $proj_id project id
	*	@param int $hook_id	selected hook id
	*/
	private static function proj_exp_hooks( $proj_id, $hook_id ){
		global $wpdb; $sql = "SELECT * FROM `".CMCHK_TABLE_HOOK."` where project_id = $proj_id and status != 'trash' ";
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );				
		if( $result !== false ){ 
			$ul = "<ul class='jqueryFileTree' style='display: none;'>";		
			foreach( $result as $r ){
				$current = ( $r['id'] === $hook_id )? 'cmchk-current-hook':'';
				$addr = "?page=cmc-hook&tab=explorer&id=$r[id]";
				$ul .= "\n\t<li class='file ext_xml $current'><a href='$addr' rel='$r[project_id]/$r[id]/' >" . htmlentities($r['title']) . "</a></li>";		
			}
			$ul .= "\n</ul>";
			return $ul;
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
		global $wpdb; $data = array(); $response = array(); 
		$data['code'] = wp_unslash( $_REQUEST['code'] );
		$id = (int)sanitize_text_field( wp_unslash( $_REQUEST['id'] ) );
		
		$result = $wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id) );
		
		if( $result === false){
			$response['success'] = false; $response['message'] = $wpdb->last_error;
		}else{
			$response['success'] = true; //$response['message'] = "Saved";
		}
		wp_send_json($response);
		exit();
	}
	
    /**
     *  Creates and Edit hooks with or without ajax
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

        $response = self::_hook_editor_table( $_POST );
		
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
					self::include_file( CMCHK_DIR."pages/sections/hook_attributes.php", array('hook_id'=>$response['id']) );				
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
			$response = self::_trash_hook( $hook_id );
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
	
	/**
     *  Privately trash hook
    */
	private static function _trash_hook( $id ){
		global $wpdb; $response = array(); $data = array();  $hook_id = intval($id);
		$data['status'] = 'trash'; $data['active'] = 0;
				
		$wpdb->update( CMCHK_TABLE_HOOK, $data, array( 'id' => $hook_id ) );
		$response['success'] = true; //$response['message'] = "Successfull";
		return $response;
	}
		
	/*
	*	Used Internally to save sanitized data record to hook table;
	*
	* 	@param array $model record values
	*/
	private function _hook_editor_table( $model = array() ){
        global $wpdb; $data = array(); $response = array(); 
        if( empty($model['title']) ){
            $response['success'] = false; $response['message'] = 'Title cannot be empty';
			return $response;
        }

        $data['id'] = isset( $model['id'] )? $model['id'] : 0;
        $data['title'] = sanitize_text_field( wp_unslash( $model['title'] ) );			
        if( $data['id'] == 0){
            $data['project_id'] = !empty($model['project_id'])? $model['project_id'] : 0;
            $data['slug'] = self::get_slug( CMCHK_TABLE_HOOK, $data['id'], $data['title'], $data['project_id'] ); //sanitize_text_field( wp_unslash( str_replace(' ', '_', $_POST['title'])) );
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
       
        $response = self::_hook_project_table_editor( $_POST );
		if( $response['success'] ){
			global $wpdb; $proj = $response['record'];
			if( $response['is_new'] ){
				$hook_resp = self::_hook_editor_table( array('title'=>$proj['title'], 'type'=>'file', 'project_id'=>$response['id'], 'active'=> 1) );
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
					self::include_file( CMCHK_DIR."pages/sections/project_attributes.php", array('proj_id'=>$response['id']) );
					//require("pages/sections/project_attributes.php");
                $response['replace'] = ob_get_clean();
            //}
            wp_send_json( $response);
        }else{
			wp_redirect( self::current_url() );
			exit();
		}
    }

	/*
	*	Used Internally to save sanitized data record to hook table;
	*
	* 	@param array $model record values
	*/
	private function _hook_project_table_editor( $model = array() ){
		global $wpdb; $data = array(); $response = array(); 
        if( empty($model['title']) ){
			$response['success'] = false; $response['message'] = 'Title cannot be empty';
			return $response;
        }
        $data['id'] = isset( $model['id'] )? $model['id'] : 0;
        $data['title'] = sanitize_text_field( wp_unslash( $model['title'] ) );		
        if( $data['id'] == 0){
			$data['project_id'] = !empty($model['cmchk_proj'])? $model['cmchk_proj'] : -1;	
			$data['slug'] = self::get_slug( CMCHK_TABLE_PROJECT, $data['id'], $data['title'], $data['project_id'] ) ;
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
     *  Trash a project
    */
	public static function trash_project(){
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
		if ( ! wp_verify_nonce( $nonce, 'cmchk-project-attr-trash-proj' ) ) {
		  die( 'Go get a life script kiddies' );
		}
		global $wpdb; $proj_id = intval( $_REQUEST['id'] );
		$response = self::_trash_project( $proj_id );
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
     *  Privately trash project
    */
	private static function _trash_project( $id ){
		global $wpdb; $response = array(); $data = array();  $proj_id = intval($id);
		$data['status'] = 'trash'; $data['active'] = 0;

		$wpdb->update( CMCHK_TABLE_PROJECT, $data, array( 'id' => $proj_id ) );
		$response['success'] = true; //$response['message'] = "Successfull";
		return $response;
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
    public static function hook_settings_save(){   		
        if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmc-hook-settings-nonce' ) ) {
			die( 'Cheating...' );
        }
        $response = self::_hook_settings_save( $_POST );     

        do_action('cmchk_settings_save', $data);
    }
	
	 /**
     *  Internally Save hook settings
     */
	private static function _hook_settings_save( $model = array() ){
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
		global $wpdb; $ids = array();
		if( $proj_id == 'all'){
			$ids = $wpdb->get_col("SELECT id FROM ".CMCHK_TABLE_PROJECT." where project_id = -1 "); array_unshift($ids, 0);
		}else if( is_numeric($proj_id) ){
			$ids = array($proj_id);
		}else if( is_array($proj_id) ){
			$ids = $proj_id;
		}
		$projs = self::_export( $ids );
		if( $projs === false ) return;
        
		self::output_file( json_encode($projs), 'wp_cmchk.json', 'application/json');
    }
	
	/**
     *  Internally Export Projects
     */
	private static function _export($ids){
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
     *  Export hooks
    **/
	public static function export_hook(){
		global $wpdb; $ids = esc_sql( $_POST['bulk-items'] ); $id = implode(', ', $ids); $proj = array('id'=> 0);
		$projs = array(); 
		$sql = "SELECT * FROM `".CMCHK_TABLE_HOOK."` where id IN($id)";
		$proj['hooks'] = $wpdb->get_results( $sql, ARRAY_A ); $projs['0'] = $proj;	
		$projs = apply_filters('cmchk_export_projects', $projs);
		$projs['meta'] = array(
			'ver'=>CMCHK_VERSION,
			'site_url'=> get_bloginfo('url'),
			'datetime'=> date('Y-m-d H:i:s'),
		);
		if( $projs === false ) return;
			
		self::output_file( json_encode($projs), 'wp_cmchk.json', 'application/json');
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
		$projs = file_get_contents($_FILES['cmchk_file_import']['tmp_name']);		
		$projs = json_decode($projs, true);
		$meta = $projs['meta']; unset($projs['meta']);
		$response = self::_import( $projs, $meta );
		
		wp_redirect( self::current_url() );
		exit();
	}
	
	/**
     *   Internally Import hooks from a project
    **/	
	protected static function _import( $projs, $meta){
		global $wpdb; $response = array();
		foreach($projs as $proj){ 
			$hooks = $proj['hooks']; $run_hook = $proj['file_run']; 
			if( $proj && $proj['id'] > 0){				
				unset( $proj['id'] ); unset($proj['hooks']); unset($proj['file_run']);
				$proj['active'] = 0; $proj['slug'] = self::get_slug( CMCHK_TABLE_PROJECT, 0, $proj['slug'], 0 );;
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
					$h['slug'] = self::get_slug( CMCHK_TABLE_HOOK, 0, $h['slug'], 0 );
				}
				$wpdb->insert( CMCHK_TABLE_HOOK, $h );
				if( $run_hook == $id ) $run_hook = $wpdb->insert_id;
			}
			if( $proj_id > 0)
				$wpdb->update( CMCHK_TABLE_PROJECT, array('file_run'=>$run_hook), array('id'=> $proj_id) );
		}
		
		$response['result'] = true; $response['message'] = "Import successfull";
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
        
        global $wpdb; $data = array(); $hook_id = intval($_REQUEST['id']);
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
				$h['code'] = self::replacelive( $h['code'] );
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
				$out = $h['code']; $out = self::replacelive( $out );
				if( $proj['file_run'] == $h['id'] ){
					if( self::startsWith("<?php", ltrim( $h['code'] )) ){
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
        self::output_file($out, $fname, 'application/zip' );
		exit;
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
			self::slug_update( $table ); 
        }else if( $_REQUEST['table'] == 'project' ){
			$table = CMCHK_TABLE_PROJECT;
			self::slug_update( $table ); 
        }
    }
	
    /**
     *  Save slug based on a particular table
     * 
     * @param string $table whether hook or project
     */
    public static function slug_update( $table ){
        global $wpdb; $id = $_REQUEST['id']; $slug = $_REQUEST['slug']; $proj_id = !empty($_REQUEST['proj'])? $_REQUEST['proj'] : 0;	
        $slug = self::get_slug( $table, $id, $slug, $proj_id ); 		
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
     *  Get value of one Hook Settings
     *	@since 1.0.5 
	 *
     * @param string $name name of the settings
     * @param string $default default value if name doesnt exist
     */
	public static function legacy(){
		global $wpdb;
		$setting = get_option( CMCHK_SETTINGS );		
		if( is_array($setting) && empty($setting['version']) ){ 
			$table = $wpdb->get_var("SHOW TABLES LIKE '".CMCHK_TABLE_PROJECT."'"); ;
			if( $table == CMCHK_TABLE_PROJECT ){ // less than 1.0.5
				$data = array('project_id' => '-1');
				$result = $wpdb->update( CMCHK_TABLE_PROJECT, $data, array('project_id'=> '0') );
				
				$result = $wpdb->get_row("SELECT * FROM ".CMCHK_TABLE_PROJECT);
				if(!isset($result->folder_id)){
					$wpdb->query("ALTER TABLE ".CMCHK_TABLE_PROJECT." ADD `folder_id` INT(10) NOT NULL DEFAULT -1");
				}
				
				$result = $wpdb->get_row("SELECT * FROM ".CMCHK_TABLE_HOOK);
				if(!isset($result->folder_id)){
					$wpdb->query("ALTER TABLE ".CMCHK_TABLE_HOOK." ADD `folder_id` INT(10) NOT NULL DEFAULT -1");
				}			
				$setting['version'] = CMCHK_VERSION;
				update_option( CMCHK_SETTINGS, $setting );
			}else{
				return;
			}
		}
		
	}
	
    /**
     *  Activation function runs on plugin activation
     */
    public static function plugin_activate(){
        self::legacy();
		global $wpdb;		
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $sql = 'CREATE TABLE IF NOT EXISTS `'.CMCHK_TABLE_HOOK.'` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `project_id` INT(10) NOT NULL DEFAULT 0,
            `title` varchar(200) NOT NULL,
            `slug` varchar(200) NOT NULL,
            `type` varchar(30) NOT NULL,
            `code` longtext NOT NULL,
            `description` varchar(1000),
            `hookname` varchar(100) NOT NULL,
            `args` int(11) NOT NULL DEFAULT 1,
            `priority` int(10) NOT NULL DEFAULT 10,
            `active` tinyint(1) NULL DEFAULT 0,
            `enable_shortcode` tinyint(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL,
			`folder_id` INT(10) NOT NULL DEFAULT -1,
            `datetimecreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `datetimeupdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
             PRIMARY KEY (`id`)
        )';
		
        dbDelta( $sql );   
		
        $sql = 'CREATE TABLE IF NOT EXISTS `'.CMCHK_TABLE_PROJECT.'` (			
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `project_id` INT(10) NOT NULL DEFAULT -1,
            `title` varchar(200) NOT NULL,
            `slug` varchar(200) NOT NULL,
            `description` varchar(1000),
            `priority` int(10) NOT NULL DEFAULT 10,
            `active` tinyint(1) NULL DEFAULT 0,
            `enable_shortcode` tinyint(1) NOT NULL DEFAULT 0,
            `file_run` INT(10) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL,
			`folder_id` INT(10) NOT NULL DEFAULT -1,
            `datetimecreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `datetimeupdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
             PRIMARY KEY (`id`)
        )';

        dbDelta( $sql );    
		
		if( !get_option( CMCHK_SETTINGS ) ){
			global $cmchk_settings_default;
			update_option(CMCHK_SETTINGS, $cmchk_settings_default);
		}

    }
    
    /**
     *  Deactivation function runs on plugin deactivation
     */
    public static function plugin_deactivate(){
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

	/**
     * Include required core files used in admin and on the frontend.
     */
    public static function includes(){
        require_once("include/default_values.php");
		require_once("include/functions.php");
    }
	
    /**
     * Define cmc_hook Constants.
     */
    public static function constants(){
        global $wpdb;
        define('CMCHK_VERSION', '1.0.5');
        define('CMCHK_FOLDER', basename( dirname( __FILE__ ) ) );
        define('CMCHK_DIR', plugin_dir_path( __FILE__ ) );
		define('CMCHK_DIR_INCLUDE', CMCHK_DIR . 'include/' );
        define('CMCHK_DIR_ZIP', CMCHK_DIR . 'zip/' );
		define('CMCHK_DIR_FILE', CMCHK_DIR . 'files/' );
        define('CMCHK_DIR_URL',  plugin_dir_url( __FILE__ ) );
		define('CMCHK_URL_JS', plugin_dir_url( __FILE__ ) . 'assets/js/');
		define('CMCHK_URL_CSS', plugin_dir_url( __FILE__ ).'assets/css/');
        define('CMCHK_TABLE_HOOK',  $wpdb->prefix.'cmc_hook' );
        define('CMCHK_TABLE_PROJECT',  $wpdb->prefix.'cmc_hook_project' );
		define('CMCHK_SETTINGS',  'cmc_hook_settings' );
		define('CMCHK_DIR_PROJECT', CMCHK_DIR.'projects/');
    }
    
	/**
    * Cloning is forbidden.
    */
    public function __clone() {
		doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'cmchk' ), '2.1' );
    }

    /**
    * Unserializing instances of this class is forbidden.\
    */
    public function __wakeup() {
		doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'cmchk' ), '2.1' );
    }
}
/**
 * Main instance of cmc_hook.
 *
 * Returns the main instance of cmchk to prevent the need to use globals.
 *
 * @return cmc_hook
 */
function cmchk() {
   return cmc_hook::instance();
}
cmchk();
?>