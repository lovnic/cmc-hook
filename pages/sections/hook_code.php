<?php
/*
package: cmc_hook
file: admin/hooks_code.php 
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
	echo "Hook Not Found";
	return;
}


$sql = $wpdb->prepare("SELECT * FROM ".CMCHK_TABLE_PROJECT." WHERE id = %d", $model['project_id']);
$proj = $wpdb->get_row( $sql, ARRAY_A );

$proj_id = $proj? $proj['id']: 0;
$sql = "SELECT `id`, `slug` FROM ".CMCHK_TABLE_HOOK." where project_id = $proj_id and type = 'file' and id != $model[id] and status != 'trash' ";
$hk_files = $wpdb->get_results( $sql, 'ARRAY_A' );
$cmc_wp_funcs = array(
    'admin_menu'=>'Admin Menu', 'menu'=>'Menu', 'post_type'=>'Post Type', 'taxonomy'=>'Taxonomy',
    'script'=>'Script', 'style'=>'Style', 'meta_box'=>'Meta Box',    
);
$cmc_wp_funcs =  apply_filters( 'cmchk_admin_page_hook_editor_wp_funcs', $cmc_wp_funcs );

?>

<div id="cmchk-hook-code-container" style="">
	<div>
		 <?php  do_action('cmchk_admin_page_editor_controls_top');  ?>  
	</div>
	<div id="cmchk_hook_editor_code_editor" style="height:450px;">
		<textarea id="cmchk-hook-code" name="code" class="widefat" 
			data-cmchk-hook-id="<?php echo $model['id']; ?>"
			data-cmchk-theme="<?php echo self::get_setting('codemirror_theme', ''); ?>" 
			data-cmchk-img-url="<?php echo CMCHK_URL_CSS.'codemirror/ui/images/silk'; ?>"
			data-cmchk-nonce="<?php echo wp_create_nonce( 'cmchk-hook-code-save' ); ?>"
			style="height:100%;" 
		><?php echo esc_html($model['code']); ?></textarea>
		<div style="clear:both;"></div>
	</div>
	<p style="margin-top:35px;">
		<label>
			<?php echo __('Php File', 'cmchk') ?>
			<select id="cmc-hk-phpfile"  >
				<?php
					foreach( $hk_files as $k => $v){
					   echo sprintf('<option value="%1$s" >%2$s</option>', $v['id'], $v['slug'] );
					}
				?>
			</select>
			<a id="cmc-hk-phpfile-insert" class="button button-secondary" href="javascript:void(0);">
				<span class="dashicons dashicons-download" style="margin-top:4px;" ></span>
			</a>
		</label>
		<label>
			<?php echo __('WP functions', 'cmchk') ?>
			<select id="cmc-hk-wpfunc">
				<?php
					foreach( $cmc_wp_funcs  as $k => $v){
					   echo sprintf('<option value="%1$s" >%2$s</option>', $k, $v );
					}
				?>
			</select>
			<a id="cmc-hk-wpfunc-insert" class="button button-secondary" href="javascript:void(0);">
				<span class="dashicons dashicons-download" style="margin-top:4px;"></span>
			</a>
		</label>
		<a id="cmc-hk-media-insert" class="button button-primary" href="javascript:void(0);">
			<i class="dashicons dashicons-admin-media" style="margin-top:4px;"></i>
		</a>
	</p>	
	<div>
		<?php  do_action('cmchk_admin_page_editor_controls_bottom');  ?>  
	</div>
</div>

<script>
var cmchk = cmchk || {};
(function($, w, cmchk){
    $(function(){
        if( !CodeMirror )return;        
            var textarea = document.getElementById("cmchk-hook-code"), $textarea = $(textarea);		
            var codeMirrorOpt = {
            lineWrapping: false,
            lineNumbers: true,
			styleActiveLine: true,
            matchBrackets: true,
            mode: "application/x-httpd-php",
            matchTags: {bothTags: true},
            indentUnit: 4,
            indentWithTabs: true,
            foldGutter: true,
            gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
			highlightSelectionMatches: {showToken: /\w/, annotateScrollbar: false},
        };
		
		var theme = $textarea.data('cmchk-theme');
		if(theme) codeMirrorOpt.theme = theme;
		
        uiOptions = {
			path : 'ui/',
			searchMode : 'popup', // 'inline', //
			imagePath: $textarea.data('cmchk-img-url'), //'images/silk',
			buttons : ['save', 'search', 'undo','redo','jump', 'reindentSelection', 'reindent','about'],			
			saveCallback : function(){ 
				var $this = this, url = ajaxurl+'?cmchk_action=hook_code_save&XDEBUG_SESSION_START=xdebug';
				var data = {code:editor.getValue(), _wpnonce: $textarea.data('cmchk-nonce')};
				data.id = $textarea.data('cmchk-hook-id'); //$btn.prop('disabled', true); 				
				var $spin = $('#cmchk-hook-code-spin').show();
				//alert(url); return; 
				
				$.post(url, data, function( data ){
					if(data.message)alert(data.message);
					if(data.replace && typeof(data.replace) == 'string'){
						$('#cmchk_hook_editor_code_editor').replaceWith(data.replace);
					}
				})
				.fail(function(){ alert("Network Error"); })
				.always(function(){ $spin.hide(); });
				
				}
        };
			
        //var editor = CodeMirror.fromTextArea(textarea, codeMirrorOpt);
		var editor = new CodeMirrorUI( textarea, uiOptions, codeMirrorOpt );
        cmchk.editor = editor.mirror;

        $('.CodeMirror').attr({'data-step':"1", 
            'data-intro':"Enter Php Codes;"+
                        "\n Use php tags when using php codes "+
                        "\n Javascript and css can be used in thier tags "+
                        "\n Don't Define functions and classes when using action and filter types"
                        }).css({'border':'1px solid #ccc;'});
		
	});
})(jQuery, window, cmchk);
</script>

<script>
(function( $, cmchk ){
    $('#cmc-hk-phpfile-insert').click(function(){
        var file = $('#cmc-hk-phpfile'), opt = file.find('option:selected');
        var doc = cmchk.editor.getDoc(), cursor = doc.getCursor(), proj = $('#cmchk_project_id'), pid = proj.val();
        doc.replaceRange(" cmchk_include( '"+opt.text()+".php"+( pid? ':'+pid: '')+"' ); ", cursor);
        return false;
    });
    
    $('#cmc-hk-wpfunc-insert').click(function(){
        var btn = $(this), $file = $('#cmc-hk-wpfunc'), doc = cmchk.editor.getDoc();
        var cursor = doc.getCursor(), str = '';
        
        switch( $file.val() ){
            case "admin_menu":  
                 str = "add_menu_page( __('Custom Menu Title'), 'custom menu', 'manage_options', 'myplugin/myplugin-admin.php');"; break;
            case "menu":
                str = "register_nav_menus( array(\n"+
                      "\t'menu_1' => 'This is menu 1',\n"+
                      "\t'menu_2' => 'This is menu 2',\n"+
                      ") );"; break;
            case "post_type": 
                str = "register_post_type( 'book', array(\n"+
                      "\t'public' => true,\n"+
                      "'\tlabel'  => 'Books'\n"+
                      "));"
                break;            
            case "taxonomy": 
                str = "register_taxonomy( 'tax_name', 'obj_type', array('plublic'=>true) );";
                break;
            case "script": 
                str = "wp_register_script( 'handle', 'src', 'deps', 'ver', 'in_footer' );";
                break;            
            case "style": 
                str = "wp_register_style( 'handle', 'src', 'deps', 'ver', 'media' );";
                break;
            case "meta_box": 
                str = "add_meta_box( 'rm-meta-box-id', esc_html__( 'RM MetaBox Title', 'text-domain' ), 'rm_meta_box_callback', 'post', 'advanced', 'high' );"
                break;
        }
        doc.replaceRange(' '+ str +' \n', cursor);
        return false;
    });

    $('#cmc-hk-media-insert').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var id = button.prev();
        wp.media.editor.send.attachment = function(props, attachment) {
            var doc = cmchk.editor.getDoc();
            var cursor = doc.getCursor();
            doc.replaceRange(' '+attachment.url+' ', cursor);
            //id.val(attachment.id);
        };
        wp.media.editor.open(button);
        return false;
    });

})(jQuery, cmchk);
</script>
