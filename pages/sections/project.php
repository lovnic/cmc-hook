<?php
/*
*   package: cmc_hook
*   parent: pages
*   file: admin/projects.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmchk::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $wpdb; $proj_id = (int)$_REQUEST['id'];
if( $proj_id > 0 ){
	$sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_PROJECT." WHERE id = %d", $proj_id);
    $model = $wpdb->get_row( $sql, ARRAY_A );
}
else if( empty( $model ) ){
    echo "project Not found";
	return;
}
?>

<h3 style="margin-bottom: 5px;"> 
    <?php echo __('Project Hooks', 'cmchk'); ?>
<!--
    <div style="float:right;margin-top:-5px;">
        <button href="javascript:void(0);" class="button button-secondary" onclick="javascript:introJs().start();" >Help</button>
        <button type="button" name="cmchk_action" value="project_editor" class="button button-primary cmchk_project_editor_form_submit" > <?php echo __('Submit', 'cmchk') ?></button>
    </div>
	-->   
</h3>
<div style="width:400px;">
	
</div>
<div id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">
        <div id="post-body-content" style="position: relative">
            <?php if( $proj_id > 0 ){ ?>
                <div>
                    <?php 
                    cmc_hook::$hooks->prepare_items();
                    cmc_hook::$hooks->views();
                    ?>
                    <form method="post" action="?page=cmc-hook&tab=project&section=project&id=<?php echo $model['id']; ?>" >
                        <input type="hidden" name="XDEBUG_SESSION_START" />
                        <?php                            
                            cmc_hook::$hooks->display();
                        ?>
                    </form>
                </div>
            <?php } ?>
        </div>
        <div id="postbox-container-1" class="postbox-container">
            <div id="" class="postbox">
                <h2 class="hndle ui-sortable-handle">
                    <span> <?php echo __('Attributes', 'cmchk') ?> </span>   
                </h2>
                <div class="inside"> 
					<?php
						cmchk::include_file( CMCHK_DIR."pages/sections/project_attributes.php", array('proj_id'=>$proj_id) );
					?>                
                </div>
            </div> 
        </div>
        <div id="postbody-content-2" class="postbox-container">
            <?php do_action('cmchk_admin_page_project_editor_controls_2'); ?>              
        </div>
    </div>
    <br class="clear"/>
</div>
<!--
<div style="float:right;margin-top:-5px;">
    <button href="javascript:void(0);" class="button button-secondary" onclick="javascript:introJs().start();" >Help</button>
    <button type="button" name="cmchk_action" value="project_editor" class="button button-primary cmchk_project_editor_form_submit" > <?php echo __('Submit', 'cmchk') ?></button>
</div>
-->
<script>
    (function($){
        
        $('#cmchk-form-project-hook-submit').click(function(){
            var $btn = $(this), $div = $('#cmchk-form-project-hook'), $nonce = $div.find('[name=_wpnonce]');
            data = {title:$div.find(':text').val(), _wpnonce: $nonce.val(), XDEBUG_SESSION_START:'xdebug'};
            data.cmchk_proj = $('#cmchk_project_id').val();$btn.prop('disabled', true);            
            $.post($div.data('url'), data, function(data){
                if(data.message)alert(data.message);
                if(data.url)document.location = data.url;
            }).always(function(){$btn.prop('disabled', false);})
            .fail(function(){alert("Network Error:");});
        });                
        
    })(jQuery);
</script>