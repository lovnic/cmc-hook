=== CMC Hook ===
Contributors: lovnic
Tags: php, javascript, css, shortcode generator, custom filters, custom hooks, custom actions, plugin creator, safe php, live php developer, Wordpress IDE
Requires at least: 4.6.0
Tested up to: 4.7
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Register php functions to hooks(action and filter), run php codes safely, create and test plugins all from dashboard tools

== Description ==
a. Targeted at developers for quick test and development of plugins
a. Register php functions to hooks ( action and filter ) live from wordpress dashboard tools.
b. Run php, html, css and javascript codes safely. php codes are enclosed between php tags eg. "<?php echo 'hello'; ?>"
c. Hooks can be disabled from the url, so your system can recover from erroneous php codes
d. create and quickly live test plugins from your website before deployment
e. Easy to use with shortcodes
f. Very extendable with other plugins as it has lots of filters and actions.

== Screenshots ==
1. Hooks
2. Hook - Filter
3. Hook - Action
4. Hook - File
5. Projects
6. Project Hooks
7. Settings


== shortcode ==
[cmchksh id="" slug=""]

== Frequently Asked Questions ==
=Minimum Requirements=
wordpress 4.6.0
php 5.5

=How to install=
Upload the plugin to the wp-content\plugins folder of your wordpress installaiton and activate it

=How to use=
The plugin create a sub menu to tools admin menu called "cmc hook"
From the cmc hook menu you can create hooks which can be either a filter, action or file
The code editor of filter and action should not contain any class or function definition as they are run in annonymous functions
File hooks can have class and function definition.

=In case there are php errors which hangs my site=
 You can deactivate hooks from url and even prevent all hooks from loading.
 
=How To Deactivate hook=
To deactivate hook, pass "cmchk_neg=cmchkrtk&cmchk_id=2,3" to the url. The cmchk_id is the id of the list of hooks you want to deactivate
Add "cmchk_run_on=none" to the url parameters to prevents all hooks from loading and running.
"cmchk_neg=token" must be present in the url parameters when deactivating any hook or project. The value of which is a security token which defaults to "cmchkrtk" and can be changed at the settings of "cmc hook" menu

=What are Hook Projects=
From the cmc hook menu projects can be created which houses a list of hooks and can be exported as a wordpress plugin
Projects have one main "run file" that runs when the project is loaded. This file calls all other project hooks. To include other project file hook use "cmchk_include( 'slug' )" function. The id is the slug of the hook file to load
Projects action and filters are loaded automatically when the project loads  and must not be included in the run file.

=Can I Deactivate Projects=
Projects can be deactivated remotely by adding to the url parameters "cmchk_pid=4,5" where cmchk is the id of the projects to deactivate

=What is safe mode=
Hooks can also be run in safe mode. This allows hooks to only run when "cmchk_safe=id" is appended to the url parameters. The id is the id  of hook

=Can I choose not to use codemirror=
Codemirror is provided as the coded editor but can be disabled at the settings to use plain textarea

=Should i use php tags=
Php codes must be code tags eg. "<?php  echo 'hello'; ?>". The same apply to javascript and css.

=Can I use html javascript and css in the code editor=
 A misture of html, php, javascript and css can be used in the code editor
 
=Filters=
cmchk_load_hook
cmchk_load_project
cmchk_editor_data_save
cmchk_project_editor_data_save
cmchk_settings_data_save
cmchk_export_projects
cmchk_import_project
cmchk_export_plugin_info
cmchk_update_slug
cmchk_get_slug
cmchk_admin_page_menu
cmchk_admin_page_section
cmchk_create_plugin_hooks
cmchk_create_plugin_file_hooks
cmchk_include_hook

=Actions=
cmchk_hook_loaded
cmchk_editor_save
cmchk_project_editor_save
cmchk_settings_save
cmchk_admin_page_settings_controls
cmchk_admin_page_hook_editor_wp_funcs
cmchk_admin_page_editor_controls_top
cmchk_admin_page_editor_controls
cmchk_admin_page_project_editor_controls
cmchk_admin_page_project_editor_controls2