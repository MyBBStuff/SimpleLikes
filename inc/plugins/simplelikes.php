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
		'website'       => 'http://www.mybbstuff.com',
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

	if (!$db->field_exists('simplelikes_can_like', 'usergroups')) {
		$db->add_column('usergroups', 'simplelikes_can_like', "INT(1) NOT NULL DEFAULT '0'");
	}

	if (!$db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
		$db->add_column('usergroups', 'simplelikes_can_view_likes', "INT(1) NOT NULL DEFAULT '0'");
	}

	$db->write_query('UPDATE '.TABLE_PREFIX.'usergroups SET `simplelikes_can_like` = 1 WHERE gid IN (2, 3, 4, 6);');
	$db->write_query('UPDATE '.TABLE_PREFIX.'usergroups SET `simplelikes_can_view_likes` = 1 WHERE gid IN (2, 3, 4, 6);');
	$cache->update_usergroups();
}

function simplelikes_is_installed()
{
	global $db;

	return $db->table_exists('post_likes');
}

function simplelikes_uninstall()
{
	global $db, $lang, $PL, $cache;

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

	if ($db->field_exists('simplelikes_can_like', 'usergroups')) {
		$db->drop_column('usergroups', 'simplelikes_can_like');
	}

	if ($db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
		$db->drop_column('usergroups', 'simplelikes_can_view_likes');
	}

	$cache->update_usergroups();
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
			'can_like_own'   =>  array(
				'title'         =>  'Let users like own posts?',
				'description'   =>  'Set whether users can "like" their own posts.',
				'value'         =>  '0',
				),
		),
		false
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

$plugins->add_hook('admin_user_groups_edit_graph_tabs', 'simplelikes_usergroup_perms_tab');
function simplelikes_usergroup_perms_tab(&$tabs)
{
	global $lang;
	if (!$lang->simplelikes) {
		$lang->load('simplelikes');
	}

	$tabs['simplelikes'] = $lang->simplelikes;
}

$plugins->add_hook('admin_user_groups_edit_graph', 'simplelikes_usergroup_perms');
function simplelikes_usergroup_perms()
{
	global $form, $mybb, $lang;

	if (!$lang->simplelikes) {
		$lang->load('simplelikes');
	}

	echo '<div id="tab_simplelikes">';
	$form_container = new FormContainer('Like System');
	$form_container->output_row($lang->simplelikes_perms_can_like, "", $form->generate_yes_no_radio('simplelikes_can_like', $mybb->input['simplelikes_can_like'], true), 'simplelikes_can_like');
	$form_container->output_row($lang->simplelikes_perms_can_view_likes, "", $form->generate_yes_no_radio('simplelikes_can_view_likes', $mybb->input['simplelikes_can_view_likes'], true), 'simplelikes_can_view_likes');
	$form_container->end();
	echo '</div>';
}

$plugins->add_hook('admin_user_groups_edit_commit', 'simplelikes_usergroup_perms_save');
function simplelikes_usergroup_perms_save()
{
	global $updated_group, $mybb;

	$updated_group['simplelikes_can_like'] = (int) $mybb->input['simplelikes_can_like'];
	$updated_group['simplelikes_can_view_likes'] = (int) $mybb->input['simplelikes_can_view_likes'];
}

global $settings;

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('postbit', 'simplelikesPostbit');
}
function simplelikesPostbit(&$post)
{
	global $mybb, $db, $templates, $pids, $postLikeBar, $lang;

	require_once SIMPLELIKES_PLUGIN_PATH.'Likes.php';
	try {
		$likeSystem = new Likes($mybb, $db, $lang);
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

	$post['button_like'] = '';
	if ($mybb->usergroup['simplelikes_can_like']) {
		eval("\$post['button_like'] = \"".$templates->get('simplelikes_likebutton')."\";");
	}
}

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('myalerts_load_lang', 'simplelikesAlertSettings');
}
function simplelikesAlertSettings()
{
	global $lang, $baseSettings, $lang;

	if (!$lang->simplelikes) {
		$lang->load('simplelikes');
	}

	$baseSettings[] = 'simplelikes';
	$lang->myalerts_setting_simplelikes = $lang->simplelikes_alert_setting;
}

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('myalerts_alerts_output_start', 'simplelikesAlertOutput');
}
function simplelikesAlertOutput(&$alert)
{
	global $mybb, $lang;

	if (!$lang->simplelikes) {
		$lang->load('simplelikes');
	}

	if ($alert['alert_type'] == 'simplelikes' AND $mybb->settings['myalerts_alert_simplelikes']) {
		$alert['message'] = $lang->sprintf($lang->simplelikes_alert, $alert['user'], get_post_link((int) $alert['tid'], (int) $alert['content']['tid']).'#pid'.(int) $alert['tid'], $alert['dateline']);
	}
}

if ($settings['simplelikes_enabled']) {
	$plugins->add_hook('misc_start', 'simplelikesMisc');
}
function simplelikesMisc()
{
	global $mybb;

	if ($mybb->input['action'] == 'post_likes') {
		if (!$mybb->usergroup['simplelikes_can_view_likes']) {
			error_no_permission();
		}

		global $db, $templates, $theme, $post, $likes, $headerinclude, $lang;

		if (!isset($mybb->input['post_id'])) {
			error('No post ID set. Did you access this function correctly?');
		}

		$pid = (int) $mybb->input['post_id'];
		$post = get_post($pid);

		require_once SIMPLELIKES_PLUGIN_PATH.'Likes.php';
		try {
			$likeSystem = new Likes($mybb, $db, $lang);
		} catch (InvalidArgumentException $e) {
			xmlhttp_error($e->getMessage());
		}

		$likeArray = $likeSystem->getLikes($pid);

		if (empty($likeArray)) {
			error('Nobody has liked this post yet. Why not be the first to do so?');
		}

		$likes = '';
		foreach ($likeArray as $like) {
			$like['username']     = htmlspecialchars_uni($like['username']);
			$like['avatar']       = htmlspecialchars_uni($like['avatar']);
			$like['profile_link'] = build_profile_link(format_name(htmlspecialchars_uni($like['username']), $like['usergroup'], $like['displaygroup']), $like['user_id']);
			eval("\$likes .= \"".$templates->get('simplelikes_likes_popup_liker')."\";");
		}

		eval("\$page = \"".$templates->get('simplelikes_likes_popup')."\";");
		output_page($page);
	}
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

		$pid = (int) $mybb->input['post_id'];
		$post = get_post($pid);

		if (!$mybb->settings['simplelikes_can_like_own'] AND $post['uid'] == $mybb->user['uid']) {
			if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				header('Content-type: application/json');
				echo json_encode(array('error' => 'You cannot like your own post.'));
			} else {
				error('You cannot like your own post.');
			}
			die();
		}

		if (!$mybb->usergroup['simplelikes_can_like']) {
			if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				header('Content-type: application/json');
				echo json_encode(array('error' => 'Your usergroup is not allowed to like posts.'));
			} else {
				error('Your usergroup is not allowed to like posts.');
			}
			die();
		}

		require_once SIMPLELIKES_PLUGIN_PATH.'Likes.php';
		try {
			$likeSystem = new Likes($mybb, $db, $lang);
		} catch (InvalidArgumentException $e) {
			xmlhttp_error($e->getMessage());
		}

		if ($result = $likeSystem->likePost((int) $mybb->input['post_id'])) {
			if ($mybb->settings['myalerts_alert_simplelikes']) {
				global $Alerts;

				$buttonText = 'Like';

				if (isset($Alerts) AND $Alerts instanceof Alerts AND $mybb->settings['myalerts_enabled']) {
					if ($result == 'like deleted') {
						$query = $db->simple_select('alerts', 'id', "alert_type = 'simplelikes' AND tid = {$pid} AND uid = ".(int) $mybb->user['uid']);
						$alertId = $db->fetch_field($query, 'id');
						$Alerts->deleteAlerts($alertId);
					} else {
						$query = $db->simple_select('alerts', 'id', "alert_type = 'simplelikes' AND tid = {$pid} AND uid = ".(int) $mybb->user['uid']);
						if ($db->num_rows($query) == 0) {
							unset($query);
							$queryString = "SELECT s.*, v.*, u.uid FROM %salert_settings s LEFT JOIN %salert_setting_values v ON (v.setting_id = s.id) LEFT JOIN %susers u ON (v.user_id = u.uid) WHERE u.uid = ". (int) $post['uid'] ." AND s.code = 'simplelikes' LIMIT 1";
							$query = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX));

							$userSetting = $db->fetch_array($query);

							if ((int) $userSetting['value'] == 1) {
								$Alerts->addAlert($post['uid'], 'simplelikes', $pid, $mybb->user['uid'], array('tid' => $post['tid']));
							}
							$buttonText = 'UnLike';
						}
					}
				}
			}

			if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				header('Content-type: application/json');
				$postLikes = array();
				$postLikes[$pid] = $likeSystem->getLikes($pid);
				$likeString = '';
				$likeString = $likeSystem->formatLikes($postLikes, $post);
				eval("\$templateString = \"".$templates->get('simplelikes_likebar')."\";");
				echo json_encode(array('message' => 'Thanks for liking this post.', 'likeString' => $likeString, 'templateString' => $templateString, 'buttonString' => $buttonText));
			} else {
				redirect(get_post_link($pid), 'Thanks for liking a post. We\'re taking you back to it now.', 'Thanks for liking!');
			}
		}
	}
}
