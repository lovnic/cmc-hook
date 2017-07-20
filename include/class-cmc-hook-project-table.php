<?php

/*
package: cmc_hook
file: admin/hooks_table.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class cmc_hook_project_List extends WP_List_Table {
   
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Project', 'cmchk' ), //singular name of the listed records
            'plural'   => __( 'Projects', 'cmchk' ), //plural name of the listed records
            'ajax'     => false, //should this table support ajax?
			//'screen'	=>'cmchk_project'

        ] );

    }
    
    public static function get_projects( $per_page = 5, $page_number = 1 ) {

        global $wpdb;
        $sql = "SELECT p.*, h.title hk_title FROM `".CMCHK_TABLE_PROJECT."` ".
		"p left join `".CMCHK_TABLE_HOOK."` h on p.file_run = h.id where ";
        //$sql = "SELECT * FROM {$wpdb->prefix}cmc_hook_project";

        if( $_REQUEST['status'] == 'trash' ){
            $sql .= " p.status = 'trash' ";
        }else{
            $sql .= " p.status != 'trash' ";
        }   
                
        if ( ! empty( $_REQUEST['orderby'] ) ) {
          $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
          $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }

        $sql .= " LIMIT $per_page";

        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }
    
    public static function delete_hook( $id ) {        
        global $wpdb; 
        if( $id > 0){
            $wpdb->delete(
                CMCHK_TABLE_HOOK,
                [ 'project_id' => esc_sql($id) ],
                [ '%d' ]
            );   
            $wpdb->delete(
                CMCHK_TABLE_PROJECT,
                [ 'id' => esc_sql($id) ],
                [ '%d' ]
            );
        }
       
       
    }
    
    public static function record_count(){
        global $wpdb;

        $sql ="SELECT COUNT(*) FROM ".CMCHK_TABLE_PROJECT." where ";
        
        if( $_REQUEST['status'] == 'trash' ){
            $sql .= " status = 'trash' ";
        }else{
            $sql .= " status != 'trash' ";
        }   

        return $wpdb->get_var( $sql );
    }
    
    public function cmchk_get_counts(){
        global $wpdb; $count = array();

        $sql = "SELECT COUNT(*) FROM ".CMCHK_TABLE_PROJECT." where ";

        $sqlall = $sql . " status != 'trash' ";
        $count['all'] = $wpdb->get_var($sqlall);

        $sqltrash =  $sql . " status = 'trash' ";
        $count['trash'] = $wpdb->get_var($sqltrash);
        return $count;
    }

    public function no_items() {
        _e( 'No Project avaliable.', 'cmchk' );
    }
    
    function column_title( $item ) {
        global $wpdb; $nonce = wp_create_nonce( 'sp_delete_hook' );

        $sql = "SELECT count(*) FROM `".CMCHK_TABLE_HOOK."` where project_id = $item[id] ";
		$count = $wpdb->get_var($sql);
		
        $title = '<strong>' . $item['title'] . '</strong>';
        $title .= " ($count hooks)";
        $actions = [];
		
        if( $_REQUEST['status'] == 'trash' ){
            $actions['restore'] = sprintf( '<a href="?page=cmc-hook&tab=project&status=trash&id=%1$s&action=restore&_wpnonce=%3$s&XDEBUG_SESSION_START">%2$s</a>', absint( $item['id'] ), __('Restore', 'cmchk'), $nonce );
            $actions['delete'] = sprintf( '<a href="?page=cmc-hook&tab=project&action=delete&status=trash&id=%1$s&_wpnonce=%3$s&XDEBUG_SESSION_START">%2$s</a>', absint( $item['id'] ), __('Delete', 'cmchk'), $nonce );
        }else{
            $actions['view'] = sprintf( '<a href="?page=cmc-hook&tab=project&section=project&id=%1$s">%2$s</a>', absint( $item['id'] ), __('View', 'cmchk'), $nonce );
            $actions['trash'] = sprintf( '<a href="?page=cmc-hook&tab=project&id=%1$s&action=trash&_wpnonce=%3$s&XDEBUG_SESSION_START">%2$s</a>', absint( $item['id'] ), __('Trash', 'cmchk'), $nonce );
        }
		
        $actions = apply_filters('cmchk_project_table_actions', $actions);
        return $title . $this->row_actions( $actions );
    }
    
    public function column_default( $item, $column_name ) {
		$col_value = $item[ $column_name ];
        switch ( $column_name ) {
            case 'active':
				return $col_value == -1? 'Safe Mode':($col_value? 'Yes':'No');
            case 'enable_shortcode':
            case 'safe_mode':
                return $col_value? 'Yes':'No';
			case 'hk_title':				
				return "<a href='?page=cmc-hook&cmchk_page=project&cmchk_section=hook_editor&cmchk_id=$item[file_run]'>$col_value</a>";
            default:
                return $col_value; // print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }
    
    function column_cb( $item ) {
        return sprintf(
          '<input type="checkbox" name="bulk-items[]" value="%s" />', $item['id']
        );
    }
    
    function get_columns() {
        $columns = [
            'cb'      => '<input type="checkbox" />',
           // 'id'      => __( 'ID', 'cmchk' ),
            'title'    => __( 'Title', 'cmchk' ),
            'slug'  => __('Slug', 'cmchk'),
            //'description'    => __( 'Description', 'cmchk' ),   
            'active'    => __( 'Active', 'cmchk' ),           
            'hk_title'=>__('Run File', 'cmchk'),
            //'datetimecreated'=>__('Created', 'cmchk'),
            'datetimeupdated'=> __('Updated', 'cmchk'),
        ];

        return $columns;
    }
    
    public function get_sortable_columns() {
        $sortable_columns = array(
          'title' => array( 'title', true ),
		  'slug' => array('slug', true),
          'id' => array( 'id', false ),
        );

        return $sortable_columns;
    }
    
    public function get_bulk_actions() {
        $actions = [];
        if( $_REQUEST['status'] == 'trash' ){
            $actions['bulk-restore'] = 'Restore';
            $actions['bulk-delete'] = 'Delete Permanently';
        }else{
			$actions['bulk-activate'] = 'Activate'; 
			$actions['bulk-deactivate'] = 'Deactivate'; 
			$actions['bulk-safe_mode'] = 'Safe Mode'; 
            $actions['bulk-trash'] = 'Trash';
            $actions['bulk-clone'] = 'Clone';
            $actions['cmchk-project-bulk-export'] = 'Export';
        }    
        return $actions;
    }
    
    protected function get_views() {
        $count = $this->cmchk_get_counts();
        $trash = $_REQUEST['status'] == 'trash'? 'current':'';
        $all = empty($_REQUEST['status'])? 'current':'';
        $views = array(
			'all'=>"<a href='?page=cmc-hook&tab=project' class='$all'>All <span class='count'>($count[all])</span></a> ",
		);
		
		$views['trash']="<a href='?page=cmc-hook&tab=project&status=trash' class='$trash'>Trash <span class='count'>($count[trash])</span></a>";
				
        return $views;
    }
	
    public function prepare_items() {
        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        //$this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'projects_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( [
          'total_items' => $total_items, //WE have to calculate the total number of items
          'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = self::get_projects( $per_page, $current_page );
           // $this->search_box( 'Search', 'cmchk_projects_search' );
    }
    
	public function cmc_admin_view(){
		$count = $this->cmchk_get_counts(); $proj_id = intval( $_REQUEST['id'] );
		echo "<div id='cmchk_section_projects' class='cmchk_section'>";
		echo '<h3>'. __('All Projects', 'cmchk').'</h3>';                
		$this->prepare_items();
		$this->views();
		echo "<form method='post' action='?page=cmc-hook&tab=project&id=$proj_id'>"; 
		echo "<input type='hidden' name='XDEBUG_SESSION_START' />";                
		$this->display();
		echo "</form>";
		echo "<div>";
	}
	
    public function process_bulk_action() {
        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'sp_delete_hook' ) ) {
                  die( 'Go get a life script kiddies' );
            }
            else {
				$proj_id = absint( $_REQUEST['id'] );
				self::delete_hook( $proj_id );
				wp_redirect( '?page=cmc-hook&tab=project&status=trash' );
				exit;
            }
        }
		
		$action = $this->current_action();
        if ( in_array($action, array('trash', 'restore')) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
            if ( ! wp_verify_nonce( $nonce, 'sp_delete_hook' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                global $wpdb; $data = array(); $proj_id = $_REQUEST['id'];
				$data['status'] = ($action == 'trash')? 'trash':'publish';
				if( $action == 'trash' ) $data['active'] = 0;
                $wpdb->update( CMCHK_TABLE_PROJECT, $data, array( 'id'=> $proj_id ) );
				$restoreurl = $action == 'restore'? '&status=trash':'';
                wp_redirect( '?page=cmc-hook&tab=project'.$restoreurl );				
                exit;
            }
        }

        // If the delete bulk action is triggered
        $action = !empty($_POST['action'])? $_POST['action']: $_POST['action2'];
        if ( in_array( $action,  array('bulk-trash', 'bulk-restore', 'bulk-delete')) ){			
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );  
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
                die("Security Breach");
            }
            global $wpdb; $ids = esc_sql( $_POST['bulk-items'] );

            foreach ( $ids as $id ) {
                if( $action == 'bulk-delete'){
					self::delete_hook( absint( $id ) );
                }else{
					$data = array();
					$data['status'] = ($action == 'bulk-trash')? 'trash':'publish';
					if( $action == 'bulk-trash' ) $data['active'] = 0;
					$wpdb->update( CMCHK_TABLE_PROJECT, $data, array('id'=> $id) );
                }
            }
            $restoreurl = in_array($action, array('bulk-delete', 'bulk-restore')) ? '&status=trash':'';
            wp_redirect( '?page=cmc-hook&tab=project'.$restoreurl );				
            exit;
        }
		
		if( in_array($action, array('bulk-activate', 'bulk-deactivate', 'bulk-safe_mode')) ){
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );  
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
                die("Security Breach");
            }
			
			global $wpdb;  $ids = esc_sql( $_POST['bulk-items'] );
            foreach ( $ids as $id ) {
				$data = array();
				$data['active'] = ($action == 'bulk-activate')? 1: (($action == 'bulk-deactivate')? 0 : -1);
				$wpdb->update( CMCHK_TABLE_PROJECT, $data, array('id'=> $id) );               
            }
            wp_redirect( '?page=cmc-hook&tab=project' );				
            exit;
		}
		
		if( $action == 'bulk-clone'){
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
				die("Security Breach");
			}
			global $wpdb; $ids = esc_sql( $_POST['bulk-items'] ); $id = implode(', ', $ids);
			$sql = "SELECT * FROM ".CMCHK_TABLE_PROJECT." where id IN($id) ";
			$projs = $wpdb->get_results( $sql, 'ARRAY_A' );
			if( $projs ){
				foreach( $projs as $p){
					$p_id = $p['id']; $filerun = $p['file_run']; unset($p['id']); unset($p['datetimecreated']);
					$p['active'] = 0; $h['datetimeupdated'] = date('Y-m-d H:i:s');
					$p['slug'] = cmc_hook::get_slug( CMCHK_TABLE_PROJECT, 0, $p['slug'], 0 );
					$wpdb->insert( CMCHK_TABLE_PROJECT, $p ); $proj_id = $wpdb->insert_id;
					if(!$proj_id)continue;
					$sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where project_id = $p_id";
					$hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
					if( !$hooks )continue;
					foreach($hooks as $h){
						$h_id = $h['id']; unset($h['id']); $h['active'] = 0; $h['project_id'] = $proj_id;
						$wpdb->insert( CMCHK_TABLE_HOOK, $h ); $hook_id = $wpdb->insert_id;	
						if( $filerun == $h_id ){
							$wpdb->update( CMCHK_TABLE_PROJECT, array('file_run'=>$hook_id), array('id'=> $proj_id) );
						}
					}					
				}
			}			
			wp_redirect( '?page=cmc-hook&tab=project' );				
			exit;
		}
    }
    
}