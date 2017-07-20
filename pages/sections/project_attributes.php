<?php

/*
*   package: cmc_hook
*   parent: pages
*   file: admin/projects_editor.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmc_hook::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $wpdb; $proj_id = $cmc_args['proj_id']; //  $_REQUEST['cmchk_id'];
if( $proj_id > 0 ){
	$sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_PROJECT." WHERE id = %d", $proj_id);
    $model = $wpdb->get_row( $sql, ARRAY_A );
}
if( empty( $model ) ){
	echo "Project Does Not Exist";
	return;
}
$sql = $wpdb->prepare("SELECT `id`, `slug` FROM ".CMCHK_TABLE_HOOK." where type = 'file' and project_id = %d", $model['id']);
$hooks = $wpdb->get_results( $sql, 'ARRAY_A' );

?>
<div id="cmchk_project_editor_form_container">
	<form id="cmchk_project_editor_form" action="" method="post" class="cmchk_project_editor_form">
		<p>
			<label>Title</label><br>
			<input type="text" name="title" class="widefat" value="<?php echo $model['title']; ?>" data-step="1" data-intro="Enter The Title"/>
			<?php wp_nonce_field( 'cmc-hook-project-nonce','_wpnonce', true, true ); ?>
			<input name="XDEBUG_SESSION_START" type="hidden" />
			<input id="cmchk_project_id" name="id" type="hidden" value="<?php echo $model['id']; ?>" />
			<input name="cmchk_id" type="hidden" value="<?php echo $model['id']; ?>" />
			<input id="cmc-hook-table" name="cmc-hook-table" type="hidden" value="project" />
		</p>
			<?php if( !empty( $proj_id ) ){ ?>
		 <p>
			<label><?php echo __('Project ID', 'cmchk') ?></label><br/>
			<input type="text" class="widefat" readonly="true" value="<?php echo $model['id']; ?>" />
		</p>
		<p>
			<label><?php echo __('Slug', 'cmchk') ?></label><br/>
			<input id="cmchk-project-slug" type="text" name="slug" class="widefat" readonly="true" value="<?php echo $model['slug']; ?>" style="width:80%;" />
			<button id="cmchk-project-slug-btn" type="button" class="button" style="width:15%; padding:0px;" data-cmchkid='<?php echo $proj_id; ?>'>Edit</button>
		</p>
		<?php } ?>
		<p>
			<label><?php echo __('Description', 'cmchk') ?></label><br/>
			<textarea name="description" row="3" class="widefat" style="vertical-align: top;" ><?php echo $model['description']; ?></textarea>
		</p>
		<p>
			<label>
				<?php echo __('Active', 'cmchk') ?>
				<input type="radio" name="active" <?php checked( $model['active'], 1); ?> value="1" />
			</label>
			<label>
				<?php echo __('Inactive', 'cmchk') ?>
				<input type="radio" name="active" <?php checked( $model['active'], 0); ?> value="0" />
			</label>
			<label>
				<?php echo __('Safe Mode', 'cmchk') ?>
				<input type="radio" name="active" <?php checked( $model['active'], -1); ?> value="-1" />
			</label>   
		</p>                        
		<p>
			<label>Run File</label> <br/>
			<select name="file_run" class="widefat">
				<option value="0">None</option>
				<?php
					foreach( $hooks as $h){
						printf('<option value="%s" %s >%s</option>', $h['id'], selected( $model['file_run'], $h['id'], false), $h['slug'] );
					}
				?>
			</select>
		</p>
		<p>
			<?php global $wp; //$wp->request ?>
			<a class="page-title-action" target="_blank" href="<?php echo add_query_arg(array(),cmc_hook::current_url()); ?>" data-step="8" data-intro="Run">
				<?php echo __('Run', 'cmchk') ?>
			</a> 
			<a class="page-title-action" target="_blank" href="<?php echo add_query_arg(array('cmchk_safe_proj'=>$model['id']),cmc_hook::current_url()); ?>" data-step="8" data-intro="Run record in safe mode">
				<?php echo __('Run In Safe Mode', 'cmchk') ?>
			</a> 
		</p>
		<?php  
			do_action('cmchk_admin_page_project_editor_controls');
		?> 
		<div style="margin:10px 0; float:left;width:100%;">
			<?php $nonce = wp_create_nonce( 'cmchk-project-attr-trash-proj' ); ?>
			<a  href="#" id="cmchk-projectattr-trash-btn"
				data-cmchk-url="<?php echo "?page=cmc-hook&id=$model[id]&cmchk_action=trash_project&_wpnonce=$nonce&XDEBUG_SESSION_START"; ?>" 
				style="color:#a00;" style="float:left;">Move To Trash</a>
			<button type="submit" name="" class="button button-primary" style="float:right;" >Save</button>
		</div>
		<div style="clear:both;"></div>
		<div id="" class="">
			<h2 class="" style="background:#ccc;" >
				<span> <?php echo __('Export', 'cmchk') ?> </span>  
			</h2>
			<div class="" > 
				<p>
					<?php $nonce = wp_create_nonce( 'cmchk-project-export-nonce' ); ?>
					<a href="?page=cmc-hook&tab=project&cmchk_action=export&XDEBUG_SESSION_START&_wpnonce=<?php echo $nonce; ?>&id=<?php echo $model['id']; ?>" target="_blank" class="button button-secondary">Export</a>
					<a href="javascript:void(0);" class="button button-secondary" style="float:right;" onclick="jQuery('#cmchk_project_export_form_container').slideToggle('fast');">
						WP Plugin
						<span class="dashicons dashicons-arrow-down" style="float:right;" ></span>
					</a>
				</p>       
			</div>		
		</div>
	</form>
	<div id="cmchk_project_export_form_container" style="display: none;">
		<p style="background: #ccc; padding: 5px;">
		<span> <b><?php echo __('Create Plugin', 'cmchk') ?> </b></span>                         
		</p>                        
		<?php 
		if( !empty( $proj_id ) ){
			cmc_hook::include_file( CMCHK_DIR."pages/sections/plugin_form.php", array('proj_id'=>$proj_id) );
		}
		?>
	</div> 
</div>

<script>
    (function($){
        $('#cmchk-project-slug-btn').click(function(){
            var $btn = $(this), $slug = $('#cmchk-project-slug');
            if( $btn.text() == 'Edit' ){
                $slug.attr('readonly', false);               
                $btn.text('ok');
            }else if($btn.text() == 'ok'){
                $slug.attr('readonly', true);
                $btn.prop('disabled', true);
                ajax = $.post(ajaxurl, {action:'cmchk_slug', id: $btn.data('cmchkid'), slug:$slug.val(), table:'project', XDEBUG_SESSION_START:'xdebug'}, function( data ){
                    $btn.text('Edit'); $slug.val(data.slug);
                }).always(function(){$btn.prop('disabled', false);})
                .fail(function(){alert("Network Error"); });               
            }
        });  
    
		$('#cmchk_project_editor_form :submit').click(function(e){
            e.preventDefault();
            var $btn = $(this), $form = $btn.closest('.cmchk_project_editor_form'); 
            var url = ajaxurl+'?action=cmchk_project_editor&XDEBUG_SESSION_START=';
            $btn.prop('disabled', true);
            $.post(url, $form.serialize(), function(data){
                if(data.message)alert(data.message);
                if(data.replace && typeof(data.replace) == 'string'){
					$('#cmchk_project_editor_form_container').replaceWith(data.replace);
                }
            }).always(function(){
                $btn.prop('disabled', false);
            }).fail(function(){ alert("Network Error"); });
            return false;
        });
	
		$('#cmchk-projectattr-trash-btn').click(function(e){
			e.preventDefault();
			var $this = $(this), url = $this.data('cmchk-url'), data = {};
			$.post( url, data, function(result){
				if( result.message )alert(result.message);
				if( result.redirect )window.location = result.redirect;
				if(result.replace && typeof(result.replace) == 'string'){
					$('#cmchk-hookeditor-hook-attributes').replaceWith(result.replace);
				}
			}).fail(function(){ alert("Network Error"); })
			.always(function(){});
			return false
		});
	})(jQuery);      
</script>
