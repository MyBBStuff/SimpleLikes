<?php
/**
 * SimpleLikes admin module meta information.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0.0
 */

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function mybbstuff_likes_meta()
{
    global $page, $lang, $plugins, $cache;

    if (!isset($cache->cache['plugins']['active']['simplelikes'])) {
        return false;
    }

    if (!isset($lang->simplelikes)) {
        $lang->load('simplelikes');
    }

    $subMenu = [
        10 => [
            'id' => 'import',
            'title' => $lang->simplelikes_import,
            'link' => 'index.php?module=mybbstuff_likes-import',
        ],
    ];

    $subMenu = $plugins->run_hooks('admin_simplelikes_menu', $subMenu);

    $page->add_menu_item($lang->simplelikes, 'mybbstuff_likes', 'index.php?module=mybbstuff_likes', 60, $subMenu);

    return true;
}

function mybbstuff_likes_action_handler($action)
{
    global $page, $plugins;

    $page->active_module = 'mybbstuff_likes';

    $actions = [
        'import' => ['active' => 'import', 'file' => 'import.php'],
    ];

    $actions = $plugins->run_hooks('admin_simplelikes_action_handler', $actions);

    if (isset($actions[$action])) {
        $page->active_action = $actions[$action]['active'];

        return $actions[$action]['file'];
    } else {
        $page->active_action = 'import';

        return 'import.php';
    }
}

function mybbstuff_likes_admin_permissions()
{
    global $lang, $plugins;

    $adminPermissions = [
        'import' => $lang->simplelikes_admin_perm_can_import,
    ];

    $adminPermissions = $plugins->run_hooks('admin_simplelikes_permissions', $adminPermissions);

    return ['name' => $lang->simplelikes, 'permissions' => $adminPermissions, 'disporder' => 60];
}
