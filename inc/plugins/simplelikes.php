<?php
/**
 *  Core Plugin File
 *
 *  A simple post like system.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0.0
 */

defined(
	'IN_MYBB'
) or die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');

defined('MYBBSTUFF_CORE_PATH') or define('MYBBSTUFF_CORE_PATH', MYBB_ROOT . 'inc/plugins/MybbStuff/Core/');
define('SIMPLELIKES_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MybbStuff/SimpleLikes');

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

require_once MYBBSTUFF_CORE_PATH . 'ClassLoader.php';

$classLoader = new MybbStuff_Core_ClassLoader();
$classLoader->registerNamespace('MybbStuff_SimpleLikes', [SIMPLELIKES_PLUGIN_PATH . '/src']);
$classLoader->register();

$importManager = MybbStuff_SimpleLikes_Import_Manager::getInstance();
$importManager->addImporter('MybbStuff_SimpleLikes_Import_ThankYouLikeImporter');

function simplelikes_info()
{
	return [
		'name'          => 'Like System',
		'description'   => 'A simple post like system.',
		'website'       => 'http://www.mybbstuff.com',
		'author'        => 'euantor',
		'authorsite'    => 'http://www.euantor.com',
		'version'       => '2.0.0',
		'codename'      => 'mybbstuff_simplelikes',
		'compatibility' => '18*',
	];
}

function simplelikes_install()
{
	global $db, $cache;

	$pluginInfo = simplelikes_info();
	$euantorPlugins = $cache->read('euantor_plugins');
	$euantorPlugins['simplelikes'] = [
		'title'   => 'SimpleLikes',
		'version' => $pluginInfo['version'],
	];
	$cache->update('euantor_plugins', $euantorPlugins);

	if (is_dir(SIMPLELIKES_PLUGIN_PATH . '/database/tables')) {
		$dir = new DirectoryIterator(SIMPLELIKES_PLUGIN_PATH . '/database/tables');
		foreach ($dir as $file) {
			if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'sql') {
				$createTableQueryString = str_replace(
					['{PREFIX}', '{COLLATION}'],
					[TABLE_PREFIX, $db->build_create_table_collation()],
					file_get_contents($file->getPathName()));

				$db->write_query($createTableQueryString);
			}
		}
	}

	if (!$db->field_exists('simplelikes_can_like', 'usergroups')) {
		$db->add_column('usergroups', 'simplelikes_can_like', "INT(1) NOT NULL DEFAULT '0'");
	}

	if (!$db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
		$db->add_column('usergroups', 'simplelikes_can_view_likes', "INT(1) NOT NULL DEFAULT '0'");
	}

	$db->update_query(
		'usergroups',
		[
			'simplelikes_can_like'       => 1,
			'simplelikes_can_view_likes' => 1,
		],
		'gid IN (2,3,4,6)'
	);
	$cache->update_usergroups();
}

function simplelikes_is_installed()
{
	global $db;

	return $db->table_exists('post_likes');
}

function simplelikes_uninstall()
{
	global $db, $PL, $cache;

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message('This plugin requires PluginLibrary, please ensure it is installed correctly.', 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	if ($db->table_exists('post_likes')) {
		$db->drop_table('post_likes');
	}
	
	$PL->settings_delete('simplelikes', true);
	$PL->templates_delete('simplelikes');
	$PL->stylesheet_delete('simplelikes.css');

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

	$oldVersion = '';
	$newVersion = '';

	$pluginInfo = simplelikes_info();
	$pluginsCache = $cache->read('euantor_plugins');

	if (isset($pluginsCache['simplelikes']['version'])) {
		$oldVersion = $pluginsCache['simplelikes']['version'];
	}
	$newVersion = $pluginInfo['version'];

	$pluginsCache['simplelikes'] = [
		'title'   => 'SimpleLikes',
		'version' => $pluginInfo['version'],
	];
	$cache->update('euantor_plugins', $pluginsCache);

	simplelikes_upgrade($oldVersion, $newVersion);

	$PL->settings(
		'simplelikes',
		'Like System Settings',
		'Settings for the like system.',
		[
			'num_users'                  => [
				'title'       => 'Number of "likers" to show per post',
				'description' => 'Set the number of most recent likers to show in the post like bar.',
				'value'       => '3',
				'optionscode' => 'text',
			],
			'can_like_own'               => [
				'title'       => 'Let users like own posts?',
				'description' => 'Set whether users can "like" their own posts.',
				'value'       => '0',
			],
			'get_num_likes_user_postbit' => [
				'title'       => 'Number of likes received in postbit?',
				'description' => 'Do you wish to get how many likes a user has received in the postbit? Beware that this adds an extra query.',
				'value'       => '0',
			],
			'likes_per_page'             => [
				'title'       => 'Likes per page',
				'description' => 'The number of likes to show per page.',
				'value'       => 1,
				'optionscode' => 'numeric',
			],
			'avatar_dimensions'          => [
				'title'       => 'Avatar Dimensions',
				'description' => 'The maximum avatar dimensions to use in the "who liked this" modal; width by height (e.g. 64|64).',
				'value'       => '64|64',
				'optionscode' => 'text',
			],
		],
		false
	);

	$stylesheet = file_get_contents(
		SIMPLELIKES_PLUGIN_PATH . '/stylesheets/simplelikes.css'
	);
	$PL->stylesheet('simplelikes.css', $stylesheet, 'showthread.php');

	if (is_dir(SIMPLELIKES_PLUGIN_PATH . '/templates')) {
		$dir = new DirectoryIterator(SIMPLELIKES_PLUGIN_PATH . '/templates');
		$templates = [];
		foreach ($dir as $file) {
			if (!$file->isDot() && !$file->isDir() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'html') {
				$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
			}
		}

		$PL->templates(
			'simplelikes',
			'Like System',
			$templates
		);
	}

	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';

	$simpleLikesJavascript = <<<HTML
<script type="text/javascript" src="{\$mybb->asset_url}/jscripts/like_system.min.js"></script>
HTML;

	find_replace_templatesets(
		'headerinclude',
		'/$/',
		$simpleLikesJavascript
	);

	// Like bar
	find_replace_templatesets(
		'postbit',
		"#" . preg_quote('{$post[\'attachments\']}') . "#i",
		'{$post[\'simplelikes\']}' . "\n" . '{$post[\'attachments\']}'
	);
	find_replace_templatesets(
		'postbit_classic',
		"#" . preg_quote('{$post[\'attachments\']}') . "#i",
		'{$post[\'simplelikes\']}' . "\n" . '{$post[\'attachments\']}'
	);

	// Like button
	find_replace_templatesets(
		'postbit',
		"#" . preg_quote('{$post[\'button_edit\']}') . "#i",
		'{$post[\'button_like\']}{$post[\'button_edit\']}'
	);
	find_replace_templatesets(
		'postbit_classic',
		"#" . preg_quote('{$post[\'button_edit\']}') . "#i",
		'{$post[\'button_like\']}{$post[\'button_edit\']}'
	);

	// Profile
	find_replace_templatesets(
		'member_profile',
		"#" . preg_quote('{$referrals}') . "#i",
		'{$postsLiked}' . "\n" . '{$likesReceived}' . "\n" . '{$referrals}'
	);

	simplelikesInstallMyAlerts();
}

function simplelikes_deactivate()
{
	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';

	$simpleLikesJavascript = <<<HTML
<script type="text/javascript" src="{\$mybb->asset_url}/jscripts/like_system.min.js"></script>
HTML;

	find_replace_templatesets(
		'headerinclude',
		"#" . preg_quote($simpleLikesJavascript) . "#i",
		''
	);

	// Like bar
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'simplelikes\']}') . "#i", '');
	find_replace_templatesets('postbit_classic', "#" . preg_quote('{$post[\'simplelikes\']}') . "#i", '');

	// Like button
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'button_like\']}') . "#i", '');
	find_replace_templatesets('postbit_classic', "#" . preg_quote('{$post[\'button_like\']}') . "#i", '');

	// Profile
	find_replace_templatesets(
		'member_profile',
		"#" . preg_quote('{$postsLiked}' . "\n" . '{$likesReceived}') . "#i",
		''
	);

	simpleLikesUninstallMyAlerts();
}

function simplelikesInstallMyAlerts()
{
	global $db, $cache;

	if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if ($alertTypeManager === false) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCanBeUserDisabled(true);
		$alertType->setCode('simplelikes');
		$alertType->setEnabled(true);

		$alertTypeManager->add($alertType);
	}

}

function simpleLikesUninstallMyAlerts()
{
	global $db, $cache;

	if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if ($alertTypeManager === false) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('simplelikes');
	}
}

function simplelikes_upgrade($oldVersion, $newVersion)
{
	if (empty($oldVersion)) {
		$oldVersion = '1.0.0';
	}

	if (version_compare($oldVersion, $newVersion) === -1) {
		if (version_compare($newVersion, '2.0.0', '>=')) {
			global $db;

			if (!$db->field_exists('created_at', 'post_likes')) {
				$db->add_column('post_likes', 'created_at', 'TIMESTAMP');
			}
		}
	}
}

$plugins->add_hook('admin_user_groups_edit_graph_tabs', 'simplelikes_usergroup_perms_tab');
function simplelikes_usergroup_perms_tab(&$tabs)
{
	global $lang;

	if (!isset($lang->simplelikes)) {
		$lang->load('simplelikes');
	}

	$tabs['simplelikes'] = $lang->simplelikes;
}

$plugins->add_hook('admin_user_groups_edit_graph', 'simplelikes_usergroup_perms');
function simplelikes_usergroup_perms()
{
	global $form, $mybb, $lang;

	if (!isset($lang->simplelikes)) {
		$lang->load('simplelikes');
	}

	echo '<div id="tab_simplelikes">';
	$form_container = new FormContainer('Like System');
	$form_container->output_row(
		$lang->simplelikes_perms_can_like,
		"",
		$form->generate_yes_no_radio('simplelikes_can_like', $mybb->get_input('simplelikes_can_like'), true),
		'simplelikes_can_like'
	);
	$form_container->output_row(
		$lang->simplelikes_perms_can_view_likes,
		"",
		$form->generate_yes_no_radio('simplelikes_can_view_likes', $mybb->get_input('simplelikes_can_view_likes'),
			true),
		'simplelikes_can_view_likes'
	);
	$form_container->end();
	echo '</div>';
}

$plugins->add_hook('admin_user_groups_edit_commit', 'simplelikes_usergroup_perms_save');
function simplelikes_usergroup_perms_save()
{
	global $updated_group, $mybb;

	$updated_group['simplelikes_can_like'] = $mybb->get_input('simplelikes_can_like', MyBB::INPUT_INT);
	$updated_group['simplelikes_can_view_likes'] = $mybb->get_input('simplelikes_can_view_likes', MyBB::INPUT_INT);
}

$plugins->add_hook('postbit', 'simplelikesPostbit');
function simplelikesPostbit(&$post)
{
	global $mybb, $db, $templates, $pids, $postLikeBar, $lang;

	if (!isset($lang->simplelikes)) {
		$lang->load('simplelikes');
	}

	$likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);

	if (is_string($pids)) {
		static $postLikes = null;
		if (!is_array($postLikes)) {
			$postLikes = [];
			$postLikes = $likeSystem->getLikes($pids);
		}
	} else {
		$postLikes[(int)$post['pid']] = $likeSystem->getLikes((int)$post['pid']);
	}

	$post['simplelikes'] = '';

	if (!empty($postLikes[$post['pid']])) {
		$likeString = $likeSystem->formatLikes($postLikes, $post);

		$post['simplelikes'] = eval($templates->render('simplelikes_likebar'));
	}

	$post['button_like'] = '';

	$canLikePost = true;

	if ($mybb->user['uid'] == $post['uid'] && !$mybb->settings['simplelikes_can_like_own']) {
		$canLikePost = false;
	}

	if ($mybb->usergroup['simplelikes_can_like'] && $canLikePost) {
		$buttonText = $lang->simplelikes_like;
		if (isset($postLikes[(int)$post['pid']][(int)$mybb->user['uid']])) {
			$buttonText = $lang->simplelikes_unlike;
		}

		$post['button_like'] = eval($templates->render('simplelikes_likebutton'));
	}

	// Get number of likes user has received
	if ($mybb->settings['simplelikes_get_num_likes_user_postbit']) {
		if (is_string($pids)) {
			static $postLikesReceived = null;
			if (!is_array($postLikesReceived)) {
				$postLikesReceived = [];
				$queryString = "SELECT p.uid, (SELECT COUNT(*) FROM %spost_likes l LEFT JOIN %sposts mp ON (l.post_id = mp.pid) WHERE mp.uid = p.uid) AS count FROM %sposts p WHERE {$pids} GROUP BY p.uid";
				$query = $db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX));
				while ($row = $db->fetch_array($query)) {
					$postLikesReceived[(int)$row['uid']] = (int)$row['count'];
				}
			}
		} else {
			$postLikesReceived = [];
			$pid = (int)$post['pid'];
			$queryString = "SELECT p.uid, (SELECT COUNT(*) FROM %spost_likes l LEFT JOIN %sposts mp ON (l.post_id = mp.pid) WHERE mp.uid = p.uid) AS count FROM %sposts p WHERE pid = {$pid} GROUP BY p.uid";
			$query = $db->query(
				sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX)
			);
			$postLikesReceived[(int)$post['uid']] = (int)$db->fetch_field($query, 'count');
		}

		if (array_key_exists((int)$post['uid'], $postLikesReceived)) {
			$post['likes_received'] = my_number_format($postLikesReceived[(int)$post['uid']]);
		} else {
			$post['likes_received'] = 0;
		}
	}
}

$plugins->add_hook('member_profile_end', 'simplelikesProfile');
function simplelikesProfile()
{
	global $mybb, $db, $lang, $memprofile, $templates, $postsLiked, $likesReceived;

	if (!isset($lang->simplelikes)) {
		$lang->load('simplelikes');
	}

	$uid = (int)$memprofile['uid'];

	// Number of likes user has made
	$query = $db->simple_select('post_likes', 'COUNT(id) AS count', 'user_id = ' . $uid);
	$usersLikes = my_number_format((int)$db->fetch_field($query, 'count'));
	$postsLiked = eval($templates->render('simplelikes_profile_total_likes'));

	// Number of likes user's posts have
	$queryString = "SELECT COUNT(l.id) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$uid}";
	$query = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
	$postLikes = my_number_format((int)$db->fetch_field($query, 'count'));
	$likesReceived = eval($templates->render('simplelikes_profile_likes_received'));
}

$plugins->add_hook('myalerts_load_lang', 'simplelikesAlertSettings');
function simplelikesAlertSettings()
{
	global $lang;

	if (!isset($lang->simplelikes)) {
		$lang->load('simplelikes');
	}

	$lang->myalerts_setting_simplelikes = $lang->simplelikes_alert_setting;
}

$plugins->add_hook('global_start', 'simplelikesGlobal');
function simplelikesGlobal()
{
	global $templatelist, $mybb;

	if (!isset($templatelist) || empty($templatelist)) {
		$templatelist = '';
	}

	simplelikesInitMyAlertsFormatter();

	$templatelist .= ',';

	if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes_by_user') {
		$templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_by_user_row,simplelikes_likes_by_user';
	}

	if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes_received_by_user') {
		$templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_received_by_user_row,simplelikes_likes_by_user';
	}

	if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes') {
		$templatelist .= 'simplelikes_likes_popup_liker,simplelikes_likes_popup';
	}

	if (THIS_SCRIPT == 'member.php' && $mybb->input['action'] == 'profile') {
		$templatelist .= 'simplelikes_profile_total_likes,simplelikes_profile_likes_received';
	}

	if (THIS_SCRIPT == 'showthread.php') {
		$templatelist .= 'simplelikes_likebutton,simplelikes_likebar';
	}
}

function simplelikesInitMyAlertsFormatter()
{
	if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
		global $mybb, $lang;

		/** @var MybbStuff_MyAlerts_AlertFormatterManager $formatterManager */
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if ($formatterManager === false) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter('MybbStuff_SimpleLikes_LikeFormatter');
	}
}

$plugins->add_hook('misc_start', 'simplelikesMisc');
function simplelikesMisc()
{
	global $mybb;

	if ($mybb->input['action'] == 'post_likes') {
		if (!$mybb->usergroup['simplelikes_can_view_likes']) {
			error_no_permission();
		}

		global $db, $templates, $theme, $headerinclude, $lang;

		if (!isset($lang->simplelikes)) {
			$lang->load('simplelikes');
		}

		if (!isset($mybb->input['post_id'])) {
			error($lang->simplelikes_error_post_id);
		}

		$pid = $mybb->get_input('post_id', MyBB::INPUT_INT);
		$post = get_post($pid);

		$likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);

		$likeArray = $likeSystem->getLikes($pid);

		if (empty($likeArray)) {
			error($lang->simplelikes_error_no_likes);
		}

		$maxAvatarDimensions = str_replace('|', 'x', $mybb->settings['simplelikes_avatar_dimensions']);

		$likes = '';
		foreach ($likeArray as $like) {
			$altbg = alt_trow();
			$like['username'] = htmlspecialchars_uni($like['username']);

			$like['avatar'] = format_avatar($like['avatar'], $mybb->settings['simplelikes_avatar_dimensions'],
				$maxAvatarDimensions);

			$like['profile_link'] = build_profile_link(
				format_name(htmlspecialchars_uni($like['username']), $like['usergroup'], $like['displaygroup']),
				$like['user_id']
			);

			$createdAt = new DateTime($like['created_at']);

			$like['created_at'] = my_date($mybb->settings['dateformat'],
					$createdAt->getTimestamp()) . ' ' . my_date($mybb->settings['timeformat'],
					$createdAt->getTimestamp());

			$likes .= eval($templates->render('simplelikes_likes_popup_liker', true, false));
		}

		$page = '';
		$page = eval($templates->render('simplelikes_likes_popup', true, false));
		echo $page;
	} else {
		if ($mybb->input['action'] == 'post_likes_by_user') {
			if (!$mybb->usergroup['simplelikes_can_view_likes']) {
				error_no_permission();
			}

			global $db, $templates, $lang;
			global $headerinclude, $header, $footer, $theme, $multipage;

			if (!isset($lang->simplelikes)) {
				$lang->load('simplelikes');
			}

			if (!isset($mybb->input['user_id']) || (int)$mybb->input['user_id'] == 0) {
				error($lang->simplelikes_error_user_id);
			}

			$userId = $mybb->get_input('user_id', MyBB::INPUT_INT);
			$user = get_user($userId);

			require_once MYBB_ROOT . 'inc/functions_search.php';
			$where_sql = '';
			$unsearchforums = get_unsearchable_forums();
			if ($unsearchforums) {
				$where_sql .= " AND p.fid NOT IN ({$unsearchforums})";
			}
			$inactiveforums = get_inactive_forums();
			if ($inactiveforums) {
				$where_sql .= " AND p.fid NOT IN ({$inactiveforums})";
			}

			$queryString = "SELECT COUNT(*) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE l.user_id = {$userId}{$where_sql}";
			$query = $db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			$count = $db->fetch_field($query, 'count');

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$perPage = $mybb->settings['simplelikes_likes_per_page'];
			$pages = $count / $perPage;
			$pages = ceil($pages);
			if ($mybb->input['page'] == "last") {
				$page = $pages;
			}

			if ($page > $pages OR $page <= 0) {
				$page = 1;
			}

			if ($page AND $page > 0) {
				$start = ($page - 1) * $perPage;
			} else {
				$start = 0;
				$page = 1;
			}
			$multipage = multipage($count, $perPage, $page, "misc.php?action=post_likes_by_user&user_id={$userId}");

			$lang->simplelikes_likes_by_user = $lang->sprintf(
				$lang->simplelikes_likes_by_user,
				htmlspecialchars_uni($user['username'])
			);

			add_breadcrumb($lang->simplelikes_likes_by_user, "misc.php?action=post_likes_by_user&user_id={$userId}");

			$likes = '';
			$queryString = "SELECT * FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE l.user_id = {$userId}{$where_sql} ORDER BY l.id DESC LIMIT {$start}, {$perPage}";
			$query = $db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $db->fetch_array($query)) {
				$altbg = alt_trow();
				$like['postlink'] = get_post_link((int)$like['post_id']) . '#pid' . (int)$like['post_id'];
				$like['subject'] = htmlspecialchars_uni($like['subject']);

				$createdAt = new DateTime($like['created_at']);

				$like['created_at'] = my_date($mybb->settings['dateformat'],
						$createdAt->getTimestamp()) . ' ' . my_date($mybb->settings['timeformat'],
						$createdAt->getTimestamp());

				$likes .= eval($templates->render('simplelikes_likes_by_user_row'));
			}

			$page = eval($templates->render('simplelikes_likes_by_user'));
			output_page($page);
		} else {
			if ($mybb->input['action'] == 'post_likes_received_by_user') {
				if (!$mybb->usergroup['simplelikes_can_view_likes']) {
					error_no_permission();
				}

				global $db, $templates, $lang;
				global $headerinclude, $header, $footer, $theme, $multipage;

				if (!isset($lang->simplelikes)) {
					$lang->load('simplelikes');
				}

				if (!isset($mybb->input['user_id']) OR (int)$mybb->input['user_id'] == 0) {
					error($lang->simplelikes_error_user_id);
				}

				$userId = $mybb->get_input('user_id', MyBB::INPUT_INT);
				$user = get_user($userId);

				require_once MYBB_ROOT . 'inc/functions_search.php';
				$where_sql = '';
				$unsearchforums = get_unsearchable_forums();
				if ($unsearchforums) {
					$where_sql .= " AND p.fid NOT IN ({$unsearchforums})";
				}
				$inactiveforums = get_inactive_forums();
				if ($inactiveforums) {
					$where_sql .= " AND p.fid NOT IN ({$inactiveforums})";
				}

				$queryString = "SELECT COUNT(*) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$userId}{$where_sql} GROUP BY p.pid";
				$query = $db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
				$count = $db->num_rows($query);

				$page = $mybb->get_input('page', MyBB::INPUT_INT);
				$perPage = $mybb->settings['simplelikes_likes_per_page'];
				$pages = $count / $perPage;
				$pages = ceil($pages);
				if ($mybb->get_input('page') == "last") {
					$page = $pages;
				}

				if ($page > $pages OR $page <= 0) {
					$page = 1;
				}

				if ($page AND $page > 0) {
					$start = ($page - 1) * $perPage;
				} else {
					$start = 0;
					$page = 1;
				}
				$multipage = multipage(
					$count,
					$perPage,
					$page,
					"misc.php?action=post_likes_received_by_user&user_id={$userId}"
				);

				$lang->simplelikes_likes_received_by_user = $lang->sprintf(
					$lang->simplelikes_likes_received_by_user,
					htmlspecialchars_uni($user['username'])
				);

				add_breadcrumb(
					$lang->simplelikes_likes_received_by_user,
					"misc.php?action=post_likes_received_by_user&user_id={$userId}"
				);

				$likes = '';
				$queryString = "SELECT *, COUNT(id) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$userId}{$where_sql} GROUP BY p.pid ORDER BY l.id DESC LIMIT {$start}, {$perPage}";
				$query = $db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
				while ($like = $db->fetch_array($query)) {
					$altbg = alt_trow();
					$like['postlink'] = get_post_link((int)$like['post_id']) . '#pid' . (int)$like['post_id'];
					$like['subject'] = htmlspecialchars_uni($like['subject']);
					$like['count'] = my_number_format((int)$like['count']);

					$createdAt = new DateTime($like['created_at']);

					$like['created_at'] = my_date($mybb->settings['dateformat'],
							$createdAt->getTimestamp()) . ' ' . my_date($mybb->settings['timeformat'],
							$createdAt->getTimestamp());

					$likes .= eval($templates->render('simplelikes_likes_received_by_user_row'));
				}

				$page = eval($templates->render('simplelikes_likes_received_by_user'));
				output_page($page);
			}
		}
	}
}

$plugins->add_hook('xmlhttp', 'simplelikesAjax');
function simplelikesAjax()
{
	global $mybb, $db, $lang, $templates, $theme;

	if ($mybb->get_input('action') == 'like_post') {
		if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
			xmlhttp_error($lang->invalid_post_code);
		}

		if (!isset($lang->simplelikes)) {
			$lang->load('simplelikes');
		}

		if (!isset($mybb->input['post_id'])) {
			xmlhttp_error($lang->simplelikes_error_post_id);
		}

		$postId = $mybb->get_input('post_id', MyBB::INPUT_INT);
		$post = get_post($postId);

		if (!$mybb->settings['simplelikes_can_like_own'] AND $post['uid'] == $mybb->user['uid']) {
			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower(
					$_SERVER['HTTP_X_REQUESTED_WITH']
				) == 'xmlhttprequest'
			) {
				header('Content-type: application/json');
				echo json_encode(['error' => $lang->simplelikes_error_own_post]);
			} else {
				xmlhttp_error($lang->simplelikes_error_own_post);
			}

			return;
		}

		if (!$mybb->usergroup['simplelikes_can_like']) {
			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower(
					$_SERVER['HTTP_X_REQUESTED_WITH']
				) == 'xmlhttprequest'
			) {
				header('Content-type: application/json');
				echo json_encode(['error' => $lang->simplelikes_error_perms]);
			} else {
				xmlhttp_error($lang->simplelikes_error_perms);
			}

			return;
		}

		$likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);

		$buttonText = $lang->simplelikes_like;

		$result = $likeSystem->likePost($postId);

		if ($result === MybbStuff_SimpleLikes_LikeManager::RESULT_LIKED) {
			$buttonText = $lang->simplelikes_unlike;
		}

		if ((int)$post['uid'] !== (int)$mybb->user['uid']) {
			if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
				global $cache, $plugins;

				$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

				if ($alertTypeManager === false) {
					$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
				}

				$alertType = $alertTypeManager->getByCode('simplelikes');

				if ($alertType != null && $alertType->getEnabled()) {
					/** @var MybbStuff_MyAlerts_AlertManager $alertManager */
					$alertManager = MybbStuff_MyAlerts_AlertManager::getInstance();

					if ($alertManager === false) {
						$alertManager = MybbStuff_MyAlerts_AlertManager::createInstance($mybb, $db, $cache, $plugins,
							$alertTypeManager);
					}

					if ($result === MybbStuff_SimpleLikes_LikeManager::RESULT_UNLIKED) {
						$db->delete_query(
							'alerts',
							"alert_type_id = '{$alertType->getId()}' AND object_id = {$postId} AND from_user_id = " . (int)$mybb->user['uid']
						);
					} else {
						$alert = new MybbStuff_MyAlerts_Entity_Alert($post['uid'], $alertType, $post['pid']);
						$alert->setExtraDetails([
							'tid' => (int)$post['tid'],
						]);


						$alertManager->addAlert($alert);
					}
				}
			}
		}

		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
				$_SERVER['HTTP_X_REQUESTED_WITH']
			) == 'xmlhttprequest'
		) {
			header('Content-type: application/json');
			$postLikes = [];
			$postLikes[$postId] = $likeSystem->getLikes($postId);
			$likeString = '';
			$likeString = $likeSystem->formatLikes($postLikes, $post);
			$templateString = '';

			$templateString = eval($templates->render('simplelikes_likebar'));
			echo json_encode(
				[
					'postId'         => $postId,
					'likeString'     => $likeString,
					'templateString' => $templateString,
					'buttonString'   => $buttonText,
				]
			);
		} else {
			redirect(
				get_post_link($postId) . '#pid' . $postId,
				$lang->simplelikes_thanks,
				$lang->simplelikes_thanks_title
			);
		}
	}
}
