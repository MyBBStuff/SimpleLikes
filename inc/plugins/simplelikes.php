<?php
/**
 *  Core Plugin File
 *
 *  A simple post like system.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.0
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

define('SIMPLELIKES_PLUGIN_PATH', MYBB_ROOT.'inc/plugins/SimpleLikes/');

if (!defined('PLUGINLIBRARY')) {
	define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');
}

function simplelikes_info()
{
	return array(
		'name'          =>  'Like System',
		'description'   =>  'A simple post like system.',
		'author'        =>  'euantor',
		'authorsite'    =>  'http://www.euantor.com',
		'version'       =>  '1.0',
		'guid'          =>  '',
		'compatibility' =>  '16*',
	);
}

function simplelikes_install()
{
	global $db, $cache;

	$plugin_info     = simplelikes_info();
	$euantor_plugins = $cache->read('euantor_plugins');
	$euantor_plugins['simplelikes'] = array(
		'title'     =>  'SimpleLikes',
		'version'   =>  $plugin_info['version'],
	);
	$cache->update('euantor_plugins', $euantor_plugins);

	if (!$db->table_exists('post_likes')) {
		$collation = $db->build_create_table_collation();
		$db->write_query("
			CREATE TABLE ".TABLE_PREFIX."post_likes(
				id INT(10) NOT NULL AUTO_INCREMENT,
				post_id INT(10) unsigned NOT NULL,
				user_id INT(10) unsigned NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB{$collation};"
		);
	}
}

function simplelikes_is_installed()
{
	global $db;

	return $db->table_exists('post_likes');
}

function simplelikes_uninstall()
{
	global $db, $lang, $PL;

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message('This plugin required PluginLibrary, please ensure it is installed correctly.', 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	if ($db->table_exists('post_likes')) {
		$db->drop_table('post_likes');
	}

	$PL->settings_delete('postlikes', true);
	$PL->templates_delete('postlikes');
}

function simplelikes_activate()
{
	global $mybb, $db, $PL, $cache;

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message('This plugin requires PluginLibrary, please ensure it is installed correctly.', 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	if ($PL->version < 9) {
		flash_message('This plugin requires PluginLibrary 9 or newer', 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$plugin_info = simplelikes_info();
	$this_version = $plugin_info['version'];
	$euantor_plugins = $cache->read('euantor_plugins');
	$euantor_plugins['simplelikes'] = array(
		'title'     =>  'SimpleLikes',
		'version'   =>  $plugin_info['version'],
		);
	$cache->update('euantor_plugins', $euantor_plugins);

	$PL->settings('simplelikes',
		'Like System Settings',
		'Settings for the like system.',
		array(
			'enabled'   =>  array(
				'title'         =>  'Enabled?',
				'description'   =>  'Use this switch to globally enable/disable the like system.',
				'value'         =>  '1',
				),
			'num_users'   =>  array(
				'title'         =>  'Number of "likers" to show per post',
				'description'   =>  'Set the number of most recent likers to show in the post like bar.',
				'value'         =>  '3',
				'optionscode'   =>  'text',
				),
		)
	);

	// Templating, like a BAWS - http://www.euantor.com/185-templates-in-mybb-plugins
	$dir = new DirectoryIterator(dirname(__FILE__).'/SimpleLikes/templates');
	$templates = array();
	foreach ($dir as $file) {
		if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}

	$PL->templates(
		'simplelikes',
		'Like System',
		$templates
	);


	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	// Add our JS. We need jQuery and myalerts.js. For jQuery, we check it hasn't already been loaded then load 1.7.2 from google's CDN
	find_replace_templatesets('headerinclude', "#".preg_quote('{$stylesheets}')."#i", '<script type="text/javascript">
if (typeof jQuery == \'undefined\') {
	document.write(unescape("%3Cscript src=\'//cdnjs.cloudflare.com/ajax/libs/jquery/1.9.1/jquery.min.js\' type=\'text/javascript\'%3E%3C/script%3E"));
}
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/like_system.js"></script>'."\n".'{$stylesheets}');
}

function simplelikes_deactivate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('headerinclude', "#".preg_quote('<script type="text/javascript">
if (typeof jQuery == \'undefined\') {
	document.write(unescape("%3Cscript src=\'//cdnjs.cloudflare.com/ajax/libs/jquery/1.9.1/jquery.min.js\' type=\'text/javascript\'%3E%3C/script%3E"));
}
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/like_system.js"></script>'."\n")."#i", '');
}

global $settings;

if ($settings['simplelikes']['enabled']) {
	$plugins->add_hook('postbit', 'simplelikesPostbit');
}
function simplelikesPostbit(&$post)
{

}
