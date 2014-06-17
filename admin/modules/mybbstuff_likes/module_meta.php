<?php
/**
 * SimpleLikes admin module meta information.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.4.0
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function mybbstuff_likes_meta()
{
	global $page, $lang, $plugins;

	if (!$lang->simplelikes) {
		$lang->load('simplelikes');
	}

	$sub_menu = array();
	$sub_menu['10'] = array('id' => 'import', 'title' => $lang->simplelikes_import, 'link' => 'index.php?module=mybbstuff_likes-import');

	$sub_menu = $plugins->run_hooks('admin_simplelikes_menu', $sub_menu);

	$page->add_menu_item($lang->simplelikes, 'mybbstuff_likes', 'index.php?module=mybbstuff_likes', 10, $sub_menu);

	return true;
}

function mybbstuff_likes_action_handler($action)
{
	global $page, $plugins;

	$page->active_module = 'mybbstuff-likes';

	$actions = array(
		'import' => array('active' => 'import', 'file' => 'import.php'),
	);

	$actions = $plugins->run_hooks('admin_simplelikes_action_handler', $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = 'import';
		return 'import.php';
	}
}

function mybbstuff_likes_admin_permissions()
{
	global $lang, $plugins;

	$admin_permissions = array(
		'import' => $lang->simplelikes_admin_perm_can_import,
	);

	$admin_permissions = $plugins->run_hooks('admin_simplelikes_permissions', $admin_permissions);

	return array('name' => $lang->simplelikes, 'permissions' => $admin_permissions, 'disporder' => 10);
}
