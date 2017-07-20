<?php
/*
package: cmc_hook
file: admin/hooks_editor.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmc_hook::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $wpdb; $hook_id = intval( $_REQUEST['id'] );
if( $hook_id > 0 ){
	$sql = $wpdb->prepare( "SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE id = %d", $hook_id);
    $model = $wpdb->get_row( $sql, ARRAY_A );
}

//if( empty( $model ) ){
	//echo "Hook Not found"; return;
//}

$sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_PROJECT." WHERE id = %d", $model['project_id']);
$proj = $wpdb->get_row( $sql, ARRAY_A );

$projid = $proj? $proj['id']: 0;
?>
<div id="cmchk_hook_editor_form"  >
	<style>
		#cmchk_hook_editor_code_editor .CodeMirror {
			/* border: 1px solid #eee; */
			height: 100%;
		}
		#cmchk_hook_editor_code_editor .CodeMirror-focused .cm-matchhighlight {
			background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFklEQVQI12NgYGBgkKzc8x9CMDAwAAAmhwSbidEoSQAAAABJRU5ErkJggg==);
			background-position: bottom;
			background-repeat: repeat-x;
		}
		#cmchk_hook_editor_code_editor .cm-matchhighlight {background-color: lightgreen}
		#cmchk_hook_editor_code_editor .CodeMirror-selection-highlight-scrollbar {background-color: green}
		.cmchk-current-project > a, .cmchk-current-hook > a{ color:red !important; }		
	</style>
    <div style="margin: 10px 5px;">
        <h4 style="float:left; margin:5px 0px;">
        <span style="margin-left:5px;">
        </span>
        <?php if( $proj ){ ?>
            <label>Project : </label>
            <a href="<?php echo '?page=cmc-hook&tab=project&section=project&id='.$proj['id']; ?>">
                <?php echo $proj['title']; ?>
            </a> 
        <?php } ?>
			<span id="cmchk-hook-code-spin" class="fa fa-spin fa-spinner" style="margin-left:10px; display:none;"></span>
        </h4> 
    </div>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content" style="position: relative">
				<?php
					if( !empty( $model ) ){
						cmc_hook::include_file( CMCHK_DIR."pages/sections/hook_code.php", array('hook_id'=>$hook_id) );
					}									
				?>
            </div>
            <div id="postbox-container-1" class="postbox-container">
                <div id="" class="postbox">
                    <h2 class="hndle ui-sortable-handle">
                        <span> <?php echo __('Attributes', 'cmchk') ?> </span>
                    </h2>
                    <div class="inside">					
						<div id="cmchk-hookeditor-attributes-tabs">
							<ul>
								<li><a href="#tabs-1">Explorer</a></li>
								<li><a href="#tabs-2">Hook</a></li>
								<li><a href="#tabs-3">Project</a></li>								
							</ul>
							<div id="tabs-1" style="padding:5px;">
								<div id="cmchk_hook_editor_project_explorer" class="" style="overflow-x: auto;">
									<h4 style="margin:5px;font-weight: bold;"><?php echo __('Project Explorer', 'cmchk'); ?></h4>
									<div id="cmchk_hook_editor_project_explorer_jft" class="" data-cmchk_url="<?php echo "?page=cmc-hook&cmchk_action=jfiletree&proj=$projid&id=$model[id]&XDEBUG_SESSION_START" ?>"></div>                   
								</div>
							</div>
							<div id="tabs-2" style="padding:5px;" >
								<?php
									if( !empty( $model ) ){
										cmc_hook::include_file( CMCHK_DIR."pages/sections/hook_attributes.php", array('hook_id'=>$hook_id) );
									}									
								?>
							</div>
							<div id="tabs-3" style="padding:5px;" >
								<?php
									if( $projid > 0){
										cmc_hook::include_file( CMCHK_DIR."pages/sections/project_attributes.php", array('proj_id'=>$projid) );// require_once("project_attributes.php"); 
									}
								?>
							</div>
							
						</div>                       
					</div>					
                </div>                
            </div>
            <div id="postbody-content-2" class="postbox-container">
                <?php 
                    do_action('cmchk_admin_page_editor_controls');
                ?>    
            </div>
        </div>
        <br class="clear"/>
    </div>
</div>
<script>
var cmchk = cmchk || {};
(function($, w, cmchk){
    $(function(){
		$('#cmchk-hookeditor-attributes-tabs').tabs();
		
		$filetree = $('#cmchk_hook_editor_project_explorer_jft');
		$filetree.fileTree({
			root: '-2',
			script:  $filetree.data('cmchk_url'),//'connectors/jqueryFileTree.php?XDEBUG_SESSION_START',
			expandSpeed: 1000,
			collapseSpeed: 1000,
			multiFolder: true
		}, function(file){
			var file = file.replace(/\/$/, ''), data = file.split('/')
			url = '?page=cmc-hook&tab=explorer&id='+data[1];
			window.location = url; 			
			//alert(url);
		});	
		
    });
})(jQuery, window, cmchk);
</script>
