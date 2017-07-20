<?php

/* 
 * package: cmc-hook
 * file: default.php
 */
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$GLOBALS['cmchk_settings_default'] = array(
	'version'=>CMCHK_VERSION,
    'run_hook_on' => 'both',
    'enable_codemirror'=>1,
	'codemirror_theme'=>'',
    'del_table_uninstall' => 0,
    'del_opt_uninstall' => 0,
    'allowed_roles'=>'',
    'deactivate_remote'=>1,
    'deactivate_remote_token'=>'cmchkrtk',	
);
