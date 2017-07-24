<?php

/*
package: cmc_hook
file: class-cmc-hook-explorer.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class cmchk_explorer{
	
	/*
	*	Generate project explorer list
	*/
	public static function project_explorer( $dir, $proj_id, $hook_id ){
		global $wpdb; $dir = urldecode($dir); $active_proj = '';
		$matches = explode("/", rtrim( $dir, '/\\') );
		$dir_id = reset($matches); $base_hook_id = end($matches);

		if( $dir_id == -2 ){
			$base_proj = array('0'=>'Hooks', '-1'=>'Projects'); $base_proj_current = ($proj_id > 0)? -1 : 0;
			$ul = "<ul class='jqueryFileTree' style='display: none;' >";
			foreach( $base_proj as $k => $v){
				$current = ( $k === $base_proj_current )? 'cmchk-current-project':'';
				$collapsed = ( $k === $base_proj_current )? 'expanded':'collapsed';
				$ul .= "\n\t<li class='directory $collapsed $current '><a href='#' rel='$k/' >$v";
				if( $collapsed == 'expanded' ){
					$ul .= ( $proj_id > 0)? cmchk_explorer::projects($proj_id, $hook_id): cmchk_explorer::hooks( $proj_id, $hook_id );
				}		
				$ul .= "</a></li>";
			}			
			$ul .= "\n</ul>";	
			echo $ul;
		}else if(  $dir_id == -1 ){
			echo cmchk_explorer::projects( $proj_id, $hook_id );
		}else if( $dir_id > -1 ){	
			echo cmchk_explorer::hooks( $dir_id, $hook_id );
		}		
		exit();
	}
	
	/*
	*	Project Explorer Project list
	*
	* 	@param int $proj_id selected project id
	*	@param int $hook_id	selected hook id
	*/
	private static function projects( $proj_id, $hook_id ){
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
					$lia .= cmchk_explorer::hooks( $proj_id, $hook_id);
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
	private static function hooks( $proj_id, $hook_id ){
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


}