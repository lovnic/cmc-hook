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

class cmc_hook_List extends WP_List_Table {
    
	public function __construct() {
        parent::__construct( [
            'singular' => __( 'Hook', 'cmchk' ), //singular name of the listed records
            'plural'   => __( 'Hooks', 'cmchk' ), //plural name of the listed records
            'ajax'     => false, //should this table support ajax?
			//'screen'	=> 'cmchk_hook'

        ] );

    }
    
    public static function get_hooks( $per_page = 5, $page_number = 1 ) {
        global $wpdb; $proj_id = intval( $_REQUEST['id'] );

        $sql = "SELECT * FROM ".CMCHK_TABLE_HOOK;
        if( $_REQUEST['tab'] == 'project' ){
            $sql .= " where project_id = ".$proj_id;
        }else{
            $sql .= " where project_id = 0 ";
        }
		
		if( $_REQUEST['status'] == 'trash' ){
			$sql .= " and status = 'trash' ";
		}else{
			$sql .= " and status != 'trash' ";
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
        
        $wpdb->delete(
          CMCHK_TABLE_HOOK,
          [ 'ID' => $id ],
          [ '%d' ]
        );
    }
    
    public static function record_count() {
        global $wpdb; $hook_id = intval($_REQUEST['id']);

        $sql = "SELECT COUNT(*) FROM ".CMCHK_TABLE_HOOK;
        if( $_REQUEST['tab'] == 'project' ){
            $sql .= " where project_id = ".$hook_id;
        }else{
            $sql .= " where project_id = 0";
        }
		
        if( $_REQUEST['cmchk_status'] == 'trash' ){
			$sql .= " and status = 'trash' ";
		}else{
			$sql .= " and status != 'trash' ";
		}  

        return $wpdb->get_var( $sql );
    }

    public function cmchk_get_counts(){
        global $wpdb; $count = array(); $proj_id = intval( $_REQUEST['id'] );

        $sql = "SELECT COUNT(*) FROM ".CMCHK_TABLE_HOOK;
        if( $_REQUEST['tab'] == 'project' ){
            $sql .= " where project_id = ".$proj_id;
        }else{
            $sql .= " where project_id = 0";
        }
		
        $sqlall = $sql . " and status != 'trash' ";
        $count['all'] = $wpdb->get_var($sqlall);

        $sqltrash =  $sql . " and status = 'trash' ";
        $count['trash'] = $wpdb->get_var($sqltrash);
        return $count;
    }
	
    public function no_items() {
        _e( 'No Hook avaliable.', 'cmchk' );
    }
    
    function column_title( $item ) {
        $nonce = wp_create_nonce( 'sp_delete_hook' );

        $title = '<strong>' . $item['title'] . '</strong>';
        
        $cmchkpage = ''; $cmchksec = '';
        if( $_REQUEST['tab'] == 'project' ) {            
            $cmchkpage = '&tab=project';
            $cmchksec = '&section=project';
            //$cmchkproj = '&cmchk_proj='.$_REQUEST['id'];
        }
 
        $actions = [];  
        if( $_REQUEST['status'] == 'trash' ){
			$actions['restore'] = sprintf( '<a href="?page=cmc-hook%1$s&id=%2$s&action=restore&_wpnonce=%4$s&XDEBUG_SESSION_START">%3$s</a>', $cmchkpage.$cmchksec.$cmchkproj, absint( $item['id'] ), __('Restore', 'cmchk'), $nonce );
			$actions['delete'] = sprintf( '<a href="?page=cmc-hook%1$s&id=%2$s&action=delete&_wpnonce=%4$s&XDEBUG_SESSION_START">%3$s</a>', $cmchkpage.$cmchksec.$cmchkproj, absint( $item['id'] ), __('Delete Permanently', 'cmchk'), $nonce );
        }else{
			$actions['view'] = sprintf( '<a href="?page=cmc-hook&tab=explorer&id=%1$s">%2$s</a>', absint( $item['id'] ), __('View', 'cmchk') );
			$actions['trash'] = sprintf( '<a href="?page=cmc-hook%1$s&id=%2$s&action=trash&_wpnonce=%4$s&XDEBUG_SESSION_START">%3$s</a>', $cmchkpage.$cmchksec.$cmchkproj, absint( $item['id'] ), __('Trash', 'cmchk'), $nonce );
        }
        
        $actions = apply_filters('cmchk_hook_table_actions', $actions);
        return $title . $this->row_actions( $actions );
    }
    
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'code':
                return htmlspecialchars($item[ $column_name ]);
            case 'active':
				return $item[ $column_name ] == -1? 'Safe Mode':($item[ $column_name ]? 'Yes':'No');
            case 'enable_shortcode':
            case 'safe_mode':
                return $item[ $column_name ]? 'Yes':'No';
            default:
                return $item[ $column_name ]; // print_r( $item, true ); //Show the whole array for troubleshooting purposes
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
            //'id'      => __( 'ID', 'cmchk' ),
            'title'    => __( 'Title', 'cmchk' ),
            'slug'  => __('Slug', 'cmchk'),
            'hookname'    => __( 'Hook Name', 'cmchk' ),
            'type' => __( 'Type', 'cmchk' ),
            //'code'    => __( 'Code', 'cmchk' ),
            //'description'    => __( 'Description', 'cmchk' ),          
            //'args'    => __( 'Args', 'cmchk' ),
            //'priority' => __('Priority', 'cmchk'),
            'active'    => __( 'Active', 'cmchk' ),
            'enable_shortcode'=> __('Shortcode', 'cmchk'),
            //'datetimecreated'=> __('Created', 'cmchk'),
            'datetimeupdated'=> __('Updated', 'cmchk'),            
        ];

        return $columns;
    }
    
    public function get_sortable_columns() {
        $sortable_columns = array(
          'title' => array( 'title', true ),
		  'slug'=> array('slug', true),
          'id' => array( 'id', false ),
          'hookname' => array( 'hookname', false ),
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
			if( empty($_REQUEST['tab']) && empty($_REQUEST['section']) ){
				$actions['cmchk-hook-bulk-export'] = 'Export';
			}
        } 
        return $actions;
    }
    
    protected function get_views() {
        $count = $this->cmchk_get_counts(); $hook_id = intval($_REQUEST['id']);
        $trash = $_REQUEST['status'] == 'trash'? 'current':'';
        $all = empty($_REQUEST['status'])? 'current':'';
		$url = $_REQUEST['tab'] == 'project'?"&tab=project&section=project&id=$hook_id":"";
		
        $views = array(
			'all'=>"<a href='?page=cmc-hook$url' class='$all'>All <span class='count'>($count[all])</span></a>"
		);
			
		$views['trash'] = "<a href='?page=cmc-hook{$url}&status=trash' class='$trash'>Trash <span class='count'>($count[trash])</span></a>";

		return $views;
    }
	
    public function prepare_items() {
        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        //$this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'hooks_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( [
          'total_items' => $total_items, //WE have to calculate the total number of items
          'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = self::get_hooks( $per_page, $current_page );
        //$this->search_box( 'Search', 'cmchk_hook_search' );
    }
    
	public function cmc_admin_view(){
		$count = $this->cmchk_get_counts(); $proj_id = intval( $_REQUEST['id'] );
		echo "<div id='cmchk_section_hooks' class='cmchk_section'>";
		echo '<h3>'. __('All Hooks', 'cmchk').'</h3>';
		$this->prepare_items();
		$this->views();
		echo "<form method='post' action='?page=cmc-hook&id=$proj_id'>";
		echo "<input type='hidden' name='XDEBUG_SESSION_START' />";                
		$this->display();
		echo "</form>";
		echo "<div>";
	}
	
    public function process_bulk_action() {
        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() ) {
            // In our file that handles the request, verify the nonce.
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );  $hook_id = absint( $_REQUEST['id'] );

            if ( ! wp_verify_nonce( $nonce, 'sp_delete_hook' ) ) {
              die( 'Go get a life script kiddies' );
            }
            else {
				global $wpdb;
				$sql = $wpdb->prepare( "SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE id = %d", $hook_id);
				$model = $wpdb->get_row( $sql, ARRAY_A );
				
				self::delete_hook( $hook_id );
                if( $model['project_id'] > 0){
					wp_redirect("?page=cmc-hook&tab=project&section=project&id=".$model['project_id'].'&status=trash');
                }else{
                    wp_redirect('?page=cmc-hook&status=trash');
                }
                exit;
            }
        }
        
        if( in_array($this->current_action(), array('trash', 'restore')) ){
            // In our file that handles the request, verify the nonce.
            $action = $this->current_action(); $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 

            if ( ! wp_verify_nonce( $nonce, 'sp_delete_hook' ) ) {
              die( 'Go get a life script kiddies' );
            }
            else {
                global $wpdb; $data = array(); $hook_id = intval($_REQUEST['id']);
				$data['status'] = ($action == 'trash')? 'trash':'publish';
				if( $action == 'trash' ) $data['active'] = 0;
				
				$sql = $wpdb->prepare( "SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE id = %d", $hook_id);
				$model = $wpdb->get_row( $sql, ARRAY_A );
				
                $wpdb->update( CMCHK_TABLE_HOOK, $data, array( 'id' => $hook_id ) );
                $restoreurl = $action == 'restore'? '&status=trash':'';
                if( $model['project_id'] > 0){
                    wp_redirect("?page=cmc-hook&tab=project&section=project&id=".$model['project_id'].$restoreurl);
                }else{
                    wp_redirect('?page=cmc-hook'.$restoreurl);
                }
                exit;
            }
        }
	
        $action = !empty($_POST['action'])? $_POST['action']: $_POST['action2'];
		
		// If the delete bulk action is triggered
        if ( in_array( $action,  array('bulk-trash', 'bulk-restore', 'bulk-delete')) ){	
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
				die("Security Breach");
			}
			
			global $wpdb;  $ids = esc_sql( $_POST['bulk-items'] ); $proj_id = intval($_REQUEST['id']);
			
			foreach ( $ids as $id ) {
				if( $action == 'bulk-delete'){
					self::delete_hook( absint( $id ) );
				}else{
					$data = array();
					$data['status'] = ($action == 'bulk-trash')? 'trash':'publish';
					if( $action == 'bulk-trash' ) $data['active'] = 0;
					$wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id) );
				}				
			}
			$restoreurl = in_array($action, array('bulk-delete', 'bulk-restore')) ? '&status=trash':'';
			if( $proj_id > 0){
				wp_redirect("?page=cmc-hook&tab=project&section=project&id=$proj_id".$restoreurl);
			}else{
				wp_redirect('?page=cmc-hook'.$restoreurl);
			}			
			exit;
		}
		
		if( in_array($action, array('bulk-activate', 'bulk-deactivate', 'bulk-safe_mode')) ){
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); 
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
                die("Security Breach");
            }
			
			global $wpdb;  $ids = esc_sql( $_POST['bulk-items'] ); $proj_id = intval($_REQUEST['id']);
            foreach ( $ids as $id ) {
				$data = array();
				$data['active'] = ($action == 'bulk-activate')? 1: (($action == 'bulk-deactivate')? 0 : -1);
				$wpdb->update( CMCHK_TABLE_HOOK, $data, array('id'=> $id) );               
            }
			
           if( $proj_id > 0){
				wp_redirect("?page=cmc-hook&tab=project&section=project&id=$proj_id");
			}else{
				wp_redirect('?page=cmc-hook');
			}
			exit;
		}
		
		if( $action == 'bulk-clone'){
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );  
			if( !wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ){
				die("Security Breach");
			}
			global $wpdb; $ids = esc_sql( $_POST['bulk-items'] ); $id = implode(', ', $ids); $proj_id = intval($_REQUEST['id']);
			$sql = "SELECT * FROM ".CMCHK_TABLE_HOOK." where id IN($id) ";
			$hooks = $wpdb->get_results( $sql, 'ARRAY_A' );
			if( $hooks ){
				foreach( $hooks as $h){
					$h['slug'] = cmc_hook::get_slug( CMCHK_TABLE_HOOK, 0, $h['slug'], $h['project_id'] );
					unset($h['id']); unset($h['datetimecreated']); 					
					$h['active'] = 0;  $h['datetimeupdated'] = date('Y-m-d H:i:s');
					$wpdb->insert( CMCHK_TABLE_HOOK, $h );
				}
			}	
			
			if( $proj_id > 0){
				wp_redirect("?page=cmc-hook&tab=project&section=project&id=$proj_id");
			}else{
				wp_redirect('?page=cmc-hook');
			}
			exit;
				
		}
   
	}
    
}