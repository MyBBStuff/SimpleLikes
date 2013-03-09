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

	$db->insert_query('alert_settings', array('code' => 'simplelikes'));
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

	$db->delete_query('alert_settings', "code = 'simplelikes'");

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

	$query = $db->simple_select('settinggroups', 'gid', "name = 'myalerts'", array('limit' => '1'));
	$gid = (int) $db->fetch_field($query, 'gid');
	$insertArray = array(
		'name'        => 'myalerts_alert_simplelikes',
		'title'       => 'Alert on post like?',
		'description' => 'Alert users when their posts are liked?',
		'optionscode' => 'yesno',
		'value'       => 1,
		'disporder'   => 5,
		'gid'         => $gid,
	);
	$db->insert_query('settings', $insertArray);
	rebuild_settings();

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
	find_replace_templatesets('postbit', "#".preg_quote('{$post[\'attachments\']}')."#i", '{$post[\'simplelikes\']}'."\n".'{$post[\'attachments\']}');
	find_replace_templatesets('postbit_classic', "#".preg_quote('{$post[\'attachments\']}')."#i", '{$post[\'simplelikes\']}'."\n".'{$post[\'attachments\']}');
}

function simplelikes_deactivate()
{
	global $db;

	$db->delete_query('settings', "name = 'myalerts_alert_simplelikes'");
	rebuild_settings();

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('headerinclude', "#".preg_quote('<script type="text/javascript">
if (typeof jQuery == \'undefined\') {
	document.write(unescape("%3Cscript src=\'//cdnjs.cloudflare.com/ajax/libs/jquery/1.9.1/jquery.min.js\' type=\'text/javascript\'%3E%3C/script%3E"));
}
</script>
<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/like_system.js"></script>'."\n")."#i", '');
	find_replace_templatesets('postbit', "#".preg_quote('{$post[\'simplelikes\']}'."\n")."#i", '');
	find_replace_templatesets('postbit_classic', "#".preg_quote('{$post[\'simplelikes\']}'."\n")."#i", '');
}

global $settings;

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('postbit', 'simplelikesPostbit');
}
function simplelikesPostbit(&$post)
{
	global $mybb, $db, $templates, $pids, $postLikeBar;

	require_once SIMPLELIKES_PLUGIN_PATH.'Likes.php';
	try {
		$likeSystem = new Likes($mybb, $db);
	} catch (InvalidArgumentException $e) {
		die($e->getMessage());
	}

	if (is_string($pids)) {
		static $postLikes = null;
		if (!is_array($postLikes)) {
			$postLikes = array();
			$postLikes = $likeSystem->getLikes($pids);
		}
	} else {
		$postLikes[(int) $post['pid']] = $likeSystem->getLikes((int) $post['pid']);
	}

	$post['simplelikes'] = '';

	if (!empty($postLikes[$post['pid']])) {
		$likeString = $likeSystem->formatLikes($postLikes, $post);
		eval("\$post['simplelikes'] = \"".$templates->get('simplelikes_likebar')."\";");
	}

	eval("\$post['button_like'] = \"".$templates->get('simplelikes_likebutton')."\";");
}

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('xmlhttp', 'simplelikesAjax');
}
function simplelikesAjax()
{
	global $mybb, $db, $lang, $templates;

	if ($mybb->input['action'] == 'like_post') {
		if (!verify_post_check($mybb->input['my_post_key'], true)) {
			xmlhttp_error($lang->invalid_post_code);
		}

		if (!isset($mybb->input['post_id'])) {
			xmlhttp_error('No post ID provided.');
		}

		require_once SIMPLELIKES_PLUGIN_PATH.'Likes.php';
		try {
			$likeSystem = new Likes($mybb, $db);
		} catch (InvalidArgumentException $e) {
			xmlhttp_error($e->getMessage());
		}

		$pid = (int) $mybb->input['post_id'];
		$post = get_post($pid);

		if ($likeSystem->likePost((int) $mybb->input['post_id'])) {
			if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			header('Content-type: application/json');
				$postLikes = array();
				$postLikes[$pid] = $likeSystem->getLikes($pid);
				$likeString = '';
				$likeString = $likeSystem->formatLikes($postLikes, $post);
				eval("\$templateString = \"".$templates->get('simplelikes_likebar')."\";");
				echo json_encode(array('message' => 'Thanks for liking this post.', 'likeString' => $likeString, 'templateString' => $templateString));
			} else {
				redirect(get_post_link($pid), 'Thanks for liking a post. We\'re taking you back to it now.', 'Thanks for liking!');
			}
		}
	}
}
