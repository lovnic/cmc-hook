<?php 
/*
Plugin Name: cmc-hook
Description: Register php functions to hooks(action and filter), run php codes safely, create and quickly test plugins all from dashboad tools
Version: 1.0.6
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
        if( !empty($_REQUEST['cmchk_neg']) )cmchk_actions::remote_deactivate_hook();
		add_action( 'plugins_loaded', array( __CLASS__, 'init'));		
        add_shortcode('cmchksh', array(__CLASS__, 'shortcode'));
        register_activation_hook( __FILE__, array( __CLASS__, 'plugin_activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'plugin_deactivate' ) );        	
    }

	/**
    * Init cmc-hook when WordPress Initialises.
	* Runs before hooks are loaded
	* Providing hooks to change their content before they run
	* Allow other plugins to hook into it
    */
	public static function init(){
		do_action('cmchk_before_init');
		if( is_admin() ){
			if( cmchk::is_user_allowed() ){				
				$action = !empty($_REQUEST['action'])? $_REQUEST['action'] : $_REQUEST['action2'];
				if( $_REQUEST['cmchk_action'] == 'hook_editor' || $action == 'cmchk_hook_editor' )cmchk_actions::hook_table_editor();
				if( $_REQUEST['cmchk_action'] == 'trash_hook' )cmchk_actions::trash_hook();				
				if( $_REQUEST['cmchk_action'] == 'hook_code_save' )cmchk_actions::hook_code_save();				
				if( $_REQUEST['cmchk_action'] == 'project_editor' || $action == 'cmchk_project_editor' )cmchk_actions::hook_project_table_editor();	
				if( $_REQUEST['cmchk_action'] == 'trash_project' )cmchk_actions::trash_project();	
				if( $action == 'create_folder' ) cmchk_actions::create_folder();				
			}
		}
		
		cmchk_actions::load_hooks();		
		self::init2();
		
		do_action('cmchk_init');
	} 
	
    /**
    * Init cmc-hook when WordPress Initialises.
	* Runs after hooks are loaded
	* Providing hooks to add actions and filters for them
    */
    public static function init2(){				
        if( is_admin() ){
            if( cmchk::is_user_allowed() ){
				$action = !empty($_REQUEST['action'])?$_REQUEST['action']:$_REQUEST['action2'];
				if( defined('DOING_AJAX') && DOING_AJAX ){
                    add_action( 'wp_ajax_cmchk_slug', array(__CLASS__, 'save_slug') );					
                }else{	
					if( !empty($_REQUEST['cmchk_action']) ){
						switch( $_REQUEST['cmchk_action'] ){
							case 'create_plugin': cmchk_actions::hook_project_create_plugin(); break;
							case 'export': cmchk_actions::export( $_REQUEST['id'] ); break;
							case 'import': cmchk_actions::import(); break;
							case 'jfiletree':cmchk_actions::project_explorer(); break;
							case 'hook_settings': cmchk_actions::settings_save(); break;
						}
					}
					if( !empty($action) ){
						switch( $action ){
							case 'cmchk-hook-bulk-export': cmchk_actions::export_hook(); break;
							case 'cmchk-project-bulk-export': cmchk_actions::export($_POST['bulk-items']); break;
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
        add_action( "load-$hook", array(__CLASS__, "menu_load"));		
    }

    /**
     * On Admin Menu load this function run
     */
    public static function menu_load(){ 

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
			require( CMCHK_DIR_INCLUDE."class-cmc-hook-project-table.php");
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

        if( cmchk::get_setting('enable_codemirror', true) && $_REQUEST['tab'] == 'explorer' )
			self::codemirror_script ();

		wp_enqueue_script( 'jquery' );
		//wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'jquery-ui-tabs' );
        wp_enqueue_script( 'main_js', CMCHK_URL_JS.'main.js', array('jquery') );
        wp_enqueue_script( 'into_js', CMCHK_URL_JS.'intro/intro.js', array('jquery') );
		wp_enqueue_script( 'tiptip_js', CMCHK_URL_JS.'TipTip/jquery.tipTip.js', array('jquery') );
		wp_enqueue_script( 'jqueryFileTree_js', CMCHK_URL_JS.'jqueryFileTree/jqueryFileTree.js', array('jquery') );
		
		wp_enqueue_style( 'jquery-ui_css', CMCHK_URL_CSS.'jquery-ui/jquery-ui.css' );
        wp_enqueue_style( 'intro_css', CMCHK_URL_CSS.'intro/introjs.css' );
		wp_enqueue_style( 'font_font-awesome_css', CMCHK_URL_CSS.'font-awesome/css/font-awesome.min.css' );
		wp_enqueue_style( 'tiptip_css', CMCHK_URL_JS.'TipTip/tipTip.css' );
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
		$theme = cmchk::get_setting('codemirror_theme', '');
		if( !empty($theme) ){
			wp_enqueue_style( 'codemirror_theme_css', CMCHK_URL_CSS.'codemirror/theme/'.$theme.'.css');
		}
	    wp_enqueue_style( 'codemirror_fold_css', CMCHK_URL_JS.'codemirror/addon/fold/foldgutter.css');
        wp_enqueue_style( 'codemirror_ui_css', CMCHK_URL_CSS.'codemirror/ui/codemirror-ui.css');
		
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
       
    }

	/**
     * Include required core files used in admin and on the frontend.
     */
    public static function includes(){
        require_once( CMCHK_DIR_INCLUDE."default_values.php");
		require_once( CMCHK_DIR_INCLUDE."class-cmchk-functions.php");
		require_once( CMCHK_DIR_INCLUDE."functions.php");
		require_once( CMCHK_DIR_INCLUDE."class-cmchk-actions.php");
		require_once( CMCHK_DIR_INCLUDE."class-cmchk-hook.php");
		require_once( CMCHK_DIR_INCLUDE."class-cmchk-project.php");
		require_once( CMCHK_DIR_INCLUDE."class-cmchk-explorer.php");
    }
	
    /**
     * Define cmc_hook Constants.
     */
    public static function constants(){
        global $wpdb;
        define('CMCHK_VERSION', '1.0.6');
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
    * Unserializing instances of this class is forbidden.
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
function cmchkc() {
   return cmc_hook::instance();
}
cmchkc();
?>