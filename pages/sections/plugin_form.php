<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

 if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if(	!cmc_hook::is_user_allowed()){
	exit('You do not have permission to view this page');
}

global $wpdb; $proj_id = $cmc_args['proj_id'];
?>
<form action="" method="post" target="_blank" >
    <p>
        <?php wp_nonce_field( 'cmchk-create-plugin-nonce','_wpnonce', true, true ); ?>
        <input type="hidden" name="id" value="<?php echo $proj_id; ?>" />
        <input type="hidden" name="XDEBUG_SESSION_START" />
        <label>Plugin Name</label><br/>
        <input name="pluginname" class="widefat" />
    </p>
    <p>
        <label>Plugin URI</label><br/>
        <input name="pluginurl" class="widefat" />
    </p>
    <p>
        <label>Description</label><br/>
        <textarea name="descirption" class="widefat" ></textarea>
    </p>
    <p>
        <label>Version</label><br/>
        <input name="version" class="widefat" />
    </p>
    <p>
        <label>Author</label><br/>
        <input name="author" class="widefat" />
    </p>
    <p>
        <label>Author URI</label><br/>
        <input name="authorurl" class="widefat" />
    </p>
    <p>
        <label>License</label><br/>
        <input name="license" class="widefat" />
    </p>
    <p>
        <label>License URI</label><br/>
        <input name="licenseurl" class="widefat" />
    </p>
    <p>
        <label>Text Domain</label><br/>
        <input name="textdomain" class="widefat" />
    </p>
    <p>
        <label>Additional Fields</label><br/>
        <textarea name="addfields" class="widefat" ></textarea>
    </p>
    <p>
        <button type="submit" class="button button-primary" name="cmchk_action" value="create_plugin">Create </button>
    </p>
</form>