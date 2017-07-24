<?php
/*
package: cmc_hook
file: admin/hooks_editor.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmchk::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $wpdb; $hook_id = $cmc_args['hook_id'];

if( $hook_id > 0 ){
	$sql = $wpdb->prepare( "SELECT * FROM ".CMCHK_TABLE_HOOK." WHERE id = %d", $hook_id);
    $model = $wpdb->get_row( $sql, ARRAY_A );
}
if( empty( $model ) ){
	echo "Hook Does Not Exist";
	return;
}

if( $model ){
	$sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_PROJECT." WHERE id = %d", $model['project_id']);
    $proj = $wpdb->get_row( $sql, ARRAY_A );
}
$types = array('filter'=>'Filter', 'action'=>'Action', 'file'=>'Php file');
$html_json = array('proj_id'=> $projid, 'hook_id'=>(int)$model['id'] );
?>
<form id="cmchk-hookeditor-hook-attributes" data-cmchk='<?php echo json_encode($html_json); ?>' >
	<p>     
		<label>Title</label><br/>
		<input type="text" name="title" class="widefat" value="<?php echo $model['title']; ?>" />
		<?php wp_nonce_field( 'cmc-hook-nonce','_wpnonce', true, true ); ?>
		<input name="XDEBUG_SESSION_START" type="hidden" />
		<input name="id" type="hidden" value="<?php echo $model['id']; ?>" /> 
		<input name="cmchk_id" type="hidden" value="<?php echo $model['id']; ?>" /> 
		<input id="cmc-hook-table" name="table" type="hidden" value="hook" />
		<?php if( $proj ){ ?>
		<input id="cmchk_project_id"  name="project_id" type="hidden" value="<?php echo $proj['id']; ?>" />
		<?php } ?>
	</p>
	<p>
		<label><?php echo __("Hook ID") ?></label><br>
		<input type="text" class="widefat" readonly="true" value="<?php echo $model['id']; ?>" />
	</p>
	<p>
		<label><?php echo __('Type', 'cmchk') ?></label><br/>
		<select id="cmc-hk-type" name="type" class="widefat" onchange="var r = /filter|action/.test(this.value); jQuery('#cmc-hk-hook-box').toggle( r );" >
			<?php 
				foreach( $types as $k => $v){
				   echo sprintf('<option value="%1$s" %3$s >%2$s</option>', $k, $v, selected($model['type'], $k, false));
				}                
			?>
		</select>                            
	</p>  
	<?php if( !empty( $hook_id ) ){ ?>
	<p>
		<label><?php echo __('Slug', 'cmchk') ?></label><br/>
		<input id="cmchk-hook-slug" type="text" name="slug" class="widefat" readonly="readonly" value="<?php echo $model['slug']; ?>" style="width:80%;" />
		<button id="cmchk-hook-slug-btn" type="button" class="button" style="width:15%; padding:0px;" data-cmchkid="<?php echo $hook_id; ?>">Edit</button>
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
			<input type="radio" name="active" <?php checked( $model['active'], -1); ?> value ="-1" />
		</label> 
		<?php if( !$proj ){ ?>							
		<label>
			<?php echo __('Shortcode', 'cmchk') ?>
			<input type="checkbox" name="enable_shortcode" <?php checked( $model['enable_shortcode'], 1); ?> />
		</label> 
		<?php } ?>							
	</p>

	<p>
		<?php global $wp; //$wp->request ?>
		<a class="page-title-action" target="_blank" href="<?php echo add_query_arg( array(), admin_url('tools.php?page=cmc-hook') ); ?>" >
			<?php echo __('Run', 'cmchk') ?>
		</a>
		<a class="page-title-action" target="_blank" href="<?php echo add_query_arg(array('cmchk_safe'=>$model['id']), admin_url('tools.php?page=cmc-hook') ); ?>" >
			<?php echo __('Run In Safe Mode', 'cmchk') ?>
		</a>
	</p>

	<div id="cmc-hk-hook-box" class="" style="display:<?php echo  preg_match('/filter|action/', $model['type'])?'block':'none' ?>;">
		<h2 class="" style="background: #ccc; padding: 5px;">
			<span><?php echo __('Hook Settings', 'cmchk') ?></span>
		</h2>
		<div class="">
			<p>
				<label><?php echo __('Hook Name', 'cmchk') ?></label><br/>
				<input type="text" name="hookname" class="widefat" value="<?php echo $model['hookname']; ?>"/>
			</p>
			<p >
				<label><?php echo __('Priority', 'cmchk') ?></label>
				<input type="number" name="priority" class="widefat" value="<?php echo $model['priority']; ?>" />
			</p>
			<p>
				<label><?php echo __('Arguments', 'cmchk') ?></label>
				<input type="number" name="args" class="widefat" value="<?php echo $model['args']; ?>" /> 
			</p>
		</div>
	</div>
	
	<div> 
		<?php do_action('cmchk_hookeditor_hook_attributes'); ?>
	</div>
	
	<div style="margin:10px 0; float:left;width:100%;">
		<?php $nonce = wp_create_nonce( 'cmchk-hook-attr-trash-hook' ); ?>
		<a  href="#" id="cmchk-hookattr-trash-btn"
			data-cmchk-url="<?php echo "?page=cmc-hook&id=$model[id]&cmchk_action=trash_hook&_wpnonce=$nonce&XDEBUG_SESSION_START"; ?>" 
			style="color:#a00;" style="float:left;">Move To Trash</a>
		<button type="submit" name="" class="button button-primary" style="float:right;" >Save</button>
	</div>
	<div style="clear:both;"></div>
	<script>
		(function($){	
			
			$('#cmchk-hook-slug-btn').click(function(){
				var $btn = $(this), $slug = $('#cmchk-hook-slug'), $proj = $('#cmchk_project_id');
				if( $btn.text() == 'Edit' ){
					$slug.attr('readonly', false);               
					$btn.text('ok');
				}else if( $btn.text() == 'ok'){
					$slug.attr('readonly', true);
					$btn.prop('disabled', true);
					var data = {action:'cmchk_slug', id: $btn.data('cmchkid'), slug:$slug.val(), table:'hook', XDEBUG_SESSION_START:'xdebug'};
					data.cmchk_proj = $proj.val();
					ajax = $.post(ajaxurl, data, function( data ){
						$slug.val(data.slug);
						$btn.text("Edit")
					}).always(function(){$btn.prop('disabled', false);})
					  .fail(function(){alert("Network Error");});               
				}
			});

			$('#cmchk-hookeditor-hook-attributes :submit').click(function(e){
				e.preventDefault();
				var $btn = $(this), $form = $btn.closest('form'); 
				var url = ajaxurl+'?action=cmchk_hook_editor&XDEBUG_SESSION_START=';
				$form.find(':submit').prop('disabled', true);
				var items = []; items = $form.serializeArray(); //items.push({name:'code', value:cmchk.editor.getValue()});
				$.post(url, items, function(result){
					if(result.message)alert(result.message);
					if(result.replace && typeof(result.replace) == 'string'){
						$('#cmchk-hookeditor-hook-attributes').replaceWith(result.replace);
					}
				}).always(function(){
					$form.find(':submit').prop('disabled', false);
				}).fail(function(){ alert("Network Error"); });
				return false;
			});

			$('#cmchk-hookattr-trash-btn').click(function(e){
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
</form>
