<?php 
/*
package: cmc_hook
file: admin/page.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmc_hook::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $cmchk_settings_default;
$model = get_option('cmc_hook_settings', $cmchk_settings_default);
$codemirrortheme = array('3024-day','3024-night', 'abcdef', 'ambiance', 'base16-dark', 'base16-light', 'bespin',
'blackboard', 'cobalt', 'colorforth', 'dracula', 'duotone-dark', 'duotone-light', 'eclipse', 'elegant', 'erlang-dark',
'hopscotch', 'icecoder', 'isotope', 'lesser-dark', 'liquibyte', 'material', 'mbo', 'mdn-like', 'midnight', 'monokai',
'neat', 'neo', 'night', 'panda-syntax', 'paraiso-dark', 'paraiso-light', 'pastel-on-dark', 'railscasts', 'rubyblue',
'seti', 'solarized dark', 'solarized light', 'the-matrix', 'tomorrow-night-bright', 'tomorrow-night-eighties', 'ttcn',
'twilight', 'vibrant-ink', 'xq-dark', 'xq-light', 'yeti', 'zenburn' );
?>
<style>
    .cmchk_section_settings_table{
        
    }
	
	#cmchk_section_settings_form .cmchk-help-tip{
		float:right;
	}
</style>

<div class="cmchk_section_settings_inner">
    <h3> <?php echo __('All Settings', 'cmchk'); ?></h3>
    <form id="cmchk_section_settings_form" method="post">
        <?php wp_nonce_field( 'cmc-hook-settings-nonce','_wpnonce', true, true ); ?>
        <input name="XDEBUG_SESSION_START" type="hidden" />
        <table id="cmchk_section_settings_table_1" class="cmchk_section_settings_table form-table">
            <tr>
                <th>
					<?php echo __('Version', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __( "Current Version of Plugin Installed",'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
				</th>
                <td>
                     <label><?php echo CMCHK_VERSION ; ?></label>
                </td>                
            </tr>
			<tr>
                <th>
					<?php echo __('Run Hooks On', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __( "Select the condition for hooks and projects to load",'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
				</th>
                <td>
                     <label><?php echo __('Front End Only', 'cmchk'); ?>  <input name="run_hook_on" type="radio" value="frontonly" <?php checked( $model['run_hook_on'], 'frontonly'); ?> /></label>
                    <label> <?php echo __('Back End Only', 'cmchk'); ?> <input name="run_hook_on" type="radio" value="backonly" <?php checked( $model['run_hook_on'], 'backonly'); ?> /></label>
                    <label> <?php echo __('Both', 'cmchk'); ?> <input name="run_hook_on" type="radio" value="both" <?php checked( $model['run_hook_on'], 'both'); ?> /></label>
                    <label> <?php echo __('None', 'cmchk'); ?> <input name="run_hook_on" type="radio" value="none" <?php checked( $model['run_hook_on'], 'none'); ?> /></label>
                </td>
                
            </tr>
            <tr>
                <th>
                    <?php echo __('Deactivate Remotely', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __("Allow deactivation of hook load through url parameters e.g. (cmchk_neg=token\n&cmchk_id=3,4\n&cmchk_pid=2,5\n&cmchk_run_on=none)\n use the token for security", 'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
                    <label>  <input name="deactivate_remote" type="checkbox" <?php checked( $model['deactivate_remote'], 1); ?> /></label>
                    <label><?php echo __('Deactivate Remote token', 'cmchk'); ?></label><input name="deactivate_remote_token" type="text" value="<?php echo $model['deactivate_remote_token'];  ?>" />
                </td>
            </tr>
            <tr>
                <th>
                    <?php echo __('Enable Code Mirror', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __("Enable codemirror for hook code editor", 'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
                    <input name="enable_codemirror" type="checkbox" <?php checked( $model['enable_codemirror'], 1); ?> />
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('CodeMirror Theme', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __("Select Code Mirror Theme", 'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
					<select name="codemirror_theme">
						<option value="" >Default</option>
						<?php 
							foreach($codemirrortheme as $t){
								echo sprintf("<option value='%s' %s >%s</option>", $t, $model['codemirror_theme'] == $t?'selected="selected"':'', $t );
							}
						?>
					</select>
                </td>
            </tr>
            <tr>
                <th>
                    <?php echo __('Delete on Uninstall', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __( "On Deactivation of Plugin Select items to delete" , 'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
					<label>
						<?php echo __('Database Tables', 'cmchk'); ?>
						<input name="del_table_uninstall" type="checkbox" <?php checked( $model['del_table_uninstall'], 1); ?> />
					</label>					
					<label>
						 <?php echo __('Settings', 'cmchk'); ?>
						 <input name="del_opt_uninstall" type="checkbox" <?php checked( $model['del_opt_uninstall'], 1); ?> />
					</label>
                </td>
            </tr>
            <tr>
                <th>
                    <?php echo __('Roles', 'cmchk'); ?>
					<span class="cmchk-help-tip" data-tip="<?php echo __("Enter role per line to allow usage of the system", 'cmchk'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
                    <textarea name="allowed_users" class="widefat" style="min-height:150px;" title="One Role Per Line"><?php echo $model['allowed_users']; ?></textarea>
                </td>
            </tr>
        </table>
        <?php
            do_action('cmchk_admin_page_settings_controls', $model);
        ?>
        <button type="submit" name="cmchk_action" value="hook_settings" class="button button-primary" ><?php echo __('Submit', 'cmchk'); ?></button>
    </form>   
    
</div>