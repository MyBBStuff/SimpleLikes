<?php
/**
 *  Core Plugin File
 *
 *  A simple post like system.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.4.0
 */

defined(
    'IN_MYBB'
) or die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');

define('SIMPLELIKES_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MybbStuff/SimpleLikes/');

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

require_once SIMPLELIKES_PLUGIN_PATH . 'vendor/autoload.php';

$importManager = MybbStuff_SimpleLikes_Import_Manager::getInstance();
$importManager->addImporter('MybbStuff_SimpleLikes_Import_ThankYouLikeImporter');

function simplelikes_info()
{
    return array(
        'name'          => 'Like System',
        'description'   => 'A simple post like system.',
        'website'       => 'http://www.mybbstuff.com',
        'author'        => 'euantor',
        'authorsite'    => 'http://www.euantor.com',
        'version'       => '1.4.0',
        'guid'          => '',
        'compatibility' => '17*',
    );
}

function simplelikes_install()
{
    global $db, $cache;

    $plugin_info                    = simplelikes_info();
    $euantor_plugins                = $cache->read('euantor_plugins');
    $euantor_plugins['simplelikes'] = array(
        'title'   => 'SimpleLikes',
        'version' => $plugin_info['version'],
    );
    $cache->update('euantor_plugins', $euantor_plugins);

    if (!$db->table_exists('post_likes')) {
        $collation = $db->build_create_table_collation();
        $db->write_query(
            "
            CREATE TABLE " . TABLE_PREFIX . "post_likes(
				id INT(10) NOT NULL AUTO_INCREMENT,
				post_id INT(10) unsigned NOT NULL,
				user_id INT(10) unsigned NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB{$collation};"
        );
    }

    if ($db->table_exists('alert_settings')) {
        $db->insert_query('alert_settings', array('code' => 'simplelikes'));
    }

    if (!$db->field_exists('simplelikes_can_like', 'usergroups')) {
        $db->add_column('usergroups', 'simplelikes_can_like', "INT(1) NOT NULL DEFAULT '0'");
    }

    if (!$db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
        $db->add_column('usergroups', 'simplelikes_can_view_likes', "INT(1) NOT NULL DEFAULT '0'");
    }

    $db->update_query(
        'usergroups',
        array(
            'simplelikes_can_like'       => 1,
            'simplelikes_can_view_likes' => 1,
        ),
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
    global $db, $lang, $PL, $cache;

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message('This plugin requires PluginLibrary, please ensure it is installed correctly.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or require_once PLUGINLIBRARY;

    if ($db->table_exists('post_likes')) {
        $db->drop_table('post_likes');
    }

    if ($db->table_exists('alert_settings')) {
        $db->delete_query('alert_settings', "code = 'simplelikes'");
    }

    $PL->settings_delete('postlikes', true);
    $PL->templates_delete('postlikes');

    if ($db->field_exists('simplelikes_can_like', 'usergroups')) {
        $db->drop_column('usergroups', 'simplelikes_can_like');
    }

    if ($db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
        $db->drop_column('usergroups', 'simplelikes_can_view_likes');
    }

    $cache->update_usergroups();

    if ($db->table_exists('alerts')) {
        $db->delete_query('alerts', "alert_type = 'simplelikes'");
    }

    if ($db->table_exists('alert_settings')) {
        $db->delete_query('alert_settings', "code = 'simplelikes'");
    }
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

    $pluginInfo                  = simplelikes_info();
    $pluginsCache                = $cache->read('mybbstuff_plugins');
    $pluginsCache['simplelikes'] = array(
        'title'   => 'SimpleLikes',
        'version' => $pluginInfo['version'],
    );
    $cache->update('mybbstuff_plugins', $pluginsCache);

    $PL->settings(
        'simplelikes',
        'Like System Settings',
        'Settings for the like system.',
        array(
            'num_users'                  => array(
                'title'       => 'Number of "likers" to show per post',
                'description' => 'Set the number of most recent likers to show in the post like bar.',
                'value'       => '3',
                'optionscode' => 'text',
            ),
            'can_like_own'               => array(
                'title'       => 'Let users like own posts?',
                'description' => 'Set whether users can "like" their own posts.',
                'value'       => '0',
            ),
            'get_num_likes_user_postbit' => array(
                'title'       => 'Number of likes received in postbit?',
                'description' => 'Do you wish to get how many likes a user has received in the postbit? Beware that this adds an extra query.',
                'value'       => '0',
            ),
        ),
        false
    );

    $query       = $db->simple_select('settinggroups', 'gid', "name = 'myalerts'", array('limit' => '1'));
    $gid         = (int) $db->fetch_field($query, 'gid');
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

    if (is_dir(SIMPLELIKES_PLUGIN_PATH . 'templates')) {
        $dir       = new DirectoryIterator(SIMPLELIKES_PLUGIN_PATH . 'templates');
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
}

function simplelikes_deactivate()
{
    global $db;

    $db->delete_query('settings', "name = 'myalerts_alert_simplelikes'");
    rebuild_settings();

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
    $form_container->output_row(
        $lang->simplelikes_perms_can_like,
        "",
        $form->generate_yes_no_radio('simplelikes_can_like', $mybb->input['simplelikes_can_like'], true),
        'simplelikes_can_like'
    );
    $form_container->output_row(
        $lang->simplelikes_perms_can_view_likes,
        "",
        $form->generate_yes_no_radio('simplelikes_can_view_likes', $mybb->input['simplelikes_can_view_likes'], true),
        'simplelikes_can_view_likes'
    );
    $form_container->end();
    echo '</div>';
}

$plugins->add_hook('admin_user_groups_edit_commit', 'simplelikes_usergroup_perms_save');
function simplelikes_usergroup_perms_save()
{
    global $updated_group, $mybb;

    $updated_group['simplelikes_can_like']       = (int) $mybb->input['simplelikes_can_like'];
    $updated_group['simplelikes_can_view_likes'] = (int) $mybb->input['simplelikes_can_view_likes'];
}

$plugins->add_hook('postbit', 'simplelikesPostbit');
function simplelikesPostbit(&$post)
{
    global $mybb, $db, $templates, $pids, $postLikeBar, $lang;

    if (!$lang->simplelikes) {
        $lang->load('simplelikes');
    }

    try {
        $likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);
    } catch (InvalidArgumentException $e) {
        return;
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
        eval("\$post['simplelikes'] = \"" . $templates->get('simplelikes_likebar') . "\";");
    }

    $post['button_like'] = '';

    $canLikePost = true;

    if ($mybb->user['uid'] == $post['uid'] && !$mybb->settings['simplelikes_can_like_own']) {
        $canLikePost = false;
    }

    if ($mybb->usergroup['simplelikes_can_like'] && $canLikePost) {
        $buttonText = $lang->simplelikes_like;
        if (isset($postLikes[(int) $post['pid']][(int) $mybb->user['uid']])) {
            $buttonText = $lang->simplelikes_unlike;
        }

        eval("\$post['button_like'] = \"" . $templates->get('simplelikes_likebutton') . "\";");
    }

    // Get number of likes user has received
    if ($mybb->settings['simplelikes_get_num_likes_user_postbit']) {
        if (is_string($pids)) {
            static $postLikesReceived = null;
            if (!is_array($postLikesReceived)) {
                $postLikesReceived = array();
                $queryString       = "SELECT p.uid, (SELECT COUNT(*) FROM %spost_likes l LEFT JOIN %sposts mp ON (l.post_id = mp.pid) WHERE mp.uid = p.uid) AS count FROM %sposts p WHERE {$pids} GROUP BY p.uid";
                $query             = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX));
                while ($row = $db->fetch_array($query)) {
                    $postLikesReceived[(int) $row['uid']] = (int) $row['count'];
                }
            }
        } else {
            $postLikesReceived                     = array();
            $pid                                   = (int) $post['pid'];
            $queryString                           = "SELECT p.uid, (SELECT COUNT(*) FROM %spost_likes l LEFT JOIN %sposts mp ON (l.post_id = mp.pid) WHERE mp.uid = p.uid) AS count FROM %sposts p WHERE pid = {$pid} GROUP BY p.uid";
            $query                                 = $db->write_query(
                sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX)
            );
            $postLikesReceived[(int) $post['uid']] = (int) $db->fetch_field($query, 'count');
        }

        if (array_key_exists((int) $post['uid'], $postLikesReceived)) {
            $post['likes_received'] = $postLikesReceived[(int) $post['uid']];
        } else {
            $post['likes_received'] = 0;
        }
    }
}

$plugins->add_hook('member_profile_end', 'simplelikesProfile');
function simplelikesProfile()
{
    global $mybb, $db, $lang, $memprofile, $templates, $postsLiked, $likesReceived;

    if (!$lang->simplelikes) {
        $lang->load('simplelikes');
    }

    $uid = (int) $memprofile['uid'];

    // Number of likes user has made
    $query      = $db->simple_select('post_likes', 'COUNT(id) AS count', 'user_id = ' . $uid);
    $usersLikes = (int) $db->fetch_field($query, 'count');
    eval("\$postsLiked = \"" . $templates->get('simplelikes_profile_total_likes') . "\";");
    unset($query);

    // Number of likes user's posts have
    $queryString = "SELECT COUNT(l.id) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$uid}";
    $query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
    $postLikes   = (int) $db->fetch_field($query, 'count');
    eval("\$likesReceived = \"" . $templates->get('simplelikes_profile_likes_received') . "\";");
    unset($query);
}

$plugins->add_hook('myalerts_load_lang', 'simplelikesAlertSettings');
function simplelikesAlertSettings()
{
    global $lang, $baseSettings, $lang;

    if (!$lang->simplelikes) {
        $lang->load('simplelikes');
    }

    $baseSettings[]                     = 'simplelikes';
    $lang->myalerts_setting_simplelikes = $lang->simplelikes_alert_setting;
}

$plugins->add_hook('myalerts_alerts_output_start', 'simplelikesAlertOutput');
function simplelikesAlertOutput(&$alert)
{
    global $mybb, $lang;

    if (!$lang->simplelikes) {
        $lang->load('simplelikes');
    }

    if ($alert['alert_type'] == 'simplelikes' AND $mybb->settings['myalerts_alert_simplelikes']) {
        $alert['message'] = $lang->sprintf(
            $lang->simplelikes_alert,
            $alert['user'],
            get_post_link((int) $alert['tid'], (int) $alert['content']['tid']) . '#pid' . (int) $alert['tid'],
            $alert['dateline']
        );
    }
}

$plugins->add_hook('global_start', 'simplelikesGlobal');
function simplelikesGlobal()
{
    global $templatelist, $mybb;

    if (!$templatelist) {
        $templatelist = '';
    }

    $templatelist .= ',';

    if (THIS_SCRIPT == 'misc.php' AND $mybb->input['action'] == 'post_likes_by_user') {
        $templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_by_user_row,simplelikes_likes_by_user';
    }

    if (THIS_SCRIPT == 'misc.php' AND $mybb->input['action'] == 'post_likes_received_by_user') {
        $templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_received_by_user_row,simplelikes_likes_by_user';
    }

    if (THIS_SCRIPT == 'misc.php' AND $mybb->input['action'] == 'post_likes') {
        $templatelist .= 'simplelikes_likes_popup_liker,simplelikes_likes_popup';
    }

    if (THIS_SCRIPT == 'member.php' AND $mybb->input['action'] == 'profile') {
        $templatelist .= 'simplelikes_profile_total_likes,simplelikes_profile_likes_received';
    }

    if (THIS_SCRIPT == 'showthread.php') {
        $templatelist .= 'simplelikes_likebutton,simplelikes_likebar';
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

        if (!$lang->simplelikes) {
            $lang->load('simplelikes');
        }

        if (!isset($mybb->input['post_id'])) {
            error($lang->simplelikes_error_post_id);
        }

        $pid  = (int) $mybb->input['post_id'];
        $post = get_post($pid);

        try {
            $likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);
        } catch (InvalidArgumentException $e) {
            xmlhttp_error($e->getMessage());
        }

        $likeArray = $likeSystem->getLikes($pid);

        if (empty($likeArray)) {
            error($lang->simplelikes_error_no_likes);
        }

        $likes = '';
        foreach ($likeArray as $like) {
            $like['username']     = htmlspecialchars_uni($like['username']);
            $like['avatar']       = htmlspecialchars_uni($like['avatar']);
            $like['profile_link'] = build_profile_link(
                format_name(htmlspecialchars_uni($like['username']), $like['usergroup'], $like['displaygroup']),
                $like['user_id']
            );
            eval("\$likes .= \"" . $templates->get('simplelikes_likes_popup_liker') . "\";");
        }

        $page = '';
        eval("\$page = \"" . $templates->get('simplelikes_likes_popup') . "\";");
        output_page($page);
    } else {
        if ($mybb->input['action'] == 'post_likes_by_user') {
            if (!$mybb->usergroup['simplelikes_can_view_likes']) {
                error_no_permission();
            }

            global $db, $templates, $lang;
            global $headerinclude, $header, $footer, $theme;

            if (!$lang->simplelikes) {
                $lang->load('simplelikes');
            }

            if (!isset($mybb->input['user_id']) OR (int) $mybb->input['user_id'] == 0) {
                error($lang->simplelikes_error_user_id);
            }

            $user_id = (int) $mybb->input['user_id'];
            $user    = get_user($user_id);

            $count = (int) $db->fetch_field(
                $db->simple_select('post_likes', 'COUNT(id) AS count', "user_id = {$user_id}"),
                'count'
            );

            $page  = (int) $mybb->input['page'];
            $pages = $count / 20;
            $pages = ceil($pages);
            if ($mybb->input['page'] == "last") {
                $page = $pages;
            }

            if ($page > $pages OR $page <= 0) {
                $page = 1;
            }

            if ($page AND $page > 0) {
                $start = ($page - 1) * 20;
            } else {
                $start = 0;
                $page  = 1;
            }
            $multipage = multipage($count, 20, $page, "misc.php?action=post_likes_by_user&user_id={$user_id}");

            $lang->simplelikes_likes_by_user = $lang->sprintf(
                $lang->simplelikes_likes_by_user,
                htmlspecialchars_uni($user['username'])
            );

            add_breadcrumb($lang->simplelikes_likes_by_user, "misc.php?action=post_likes_by_user&user_id={$user_id}");

            $likes       = '';
            $queryString = "SELECT * FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE l.user_id = {$user_id} ORDER BY l.id DESC LIMIT {$start}, 20";
            $query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
            while ($like = $db->fetch_array($query)) {
                $altbg            = alt_trow();
                $like['postlink'] = get_post_link((int) $like['post_id']) . '#pid' . (int) $like['post_id'];
                $like['subject']  = htmlspecialchars_uni($like['subject']);
                eval("\$likes .= \"" . $templates->get('simplelikes_likes_by_user_row') . "\";");
            }

            eval("\$page = \"" . $templates->get('simplelikes_likes_by_user') . "\";");
            output_page($page);
        } else {
            if ($mybb->input['action'] == 'post_likes_received_by_user') {
                if (!$mybb->usergroup['simplelikes_can_view_likes']) {
                    error_no_permission();
                }

                global $db, $templates, $lang;
                global $headerinclude, $header, $footer, $theme;

                if (!$lang->simplelikes) {
                    $lang->load('simplelikes');
                }

                if (!isset($mybb->input['user_id']) OR (int) $mybb->input['user_id'] == 0) {
                    error($lang->simplelikes_error_user_id);
                }

                $user_id = (int) $mybb->input['user_id'];
                $user    = get_user($user_id);

                $queryString = "SELECT COUNT(*) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$user_id} GROUP BY p.pid";
                $query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
                $count       = (int) $db->fetch_field($query, 'count');
                unset($query);

                $page  = (int) $mybb->input['page'];
                $pages = $count / 20;
                $pages = ceil($pages);
                if ($mybb->input['page'] == "last") {
                    $page = $pages;
                }

                if ($page > $pages OR $page <= 0) {
                    $page = 1;
                }

                if ($page AND $page > 0) {
                    $start = ($page - 1) * 20;
                } else {
                    $start = 0;
                    $page  = 1;
                }
                $multipage = multipage(
                    $count,
                    20,
                    $page,
                    "misc.php?action=post_likes_received_by_user&user_id={$user_id}"
                );

                $lang->simplelikes_likes_by_user = $lang->sprintf(
                    $lang->simplelikes_likes_received_by_user,
                    htmlspecialchars_uni($user['username'])
                );

                add_breadcrumb(
                    $lang->simplelikes_likes_by_user,
                    "misc.php?action=post_likes_by_user&user_id={$user_id}"
                );

                $likes       = '';
                $queryString = "SELECT *, COUNT(id) AS count FROM %spost_likes l LEFT JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$user_id} GROUP BY p.pid ORDER BY l.id DESC LIMIT {$start}, 20";
                $query       = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
                while ($like = $db->fetch_array($query)) {
                    $altbg            = alt_trow();
                    $like['postlink'] = get_post_link((int) $like['post_id']) . '#pid' . (int) $like['post_id'];
                    $like['subject']  = htmlspecialchars_uni($like['subject']);
                    $like['count']    = (int) $like['count'];
                    eval("\$likes .= \"" . $templates->get('simplelikes_likes_received_by_user_row') . "\";");
                }

                eval("\$page = \"" . $templates->get('simplelikes_likes_by_user') . "\";");
                output_page($page);
            }
        }
    }
}

$plugins->add_hook('xmlhttp', 'simplelikesAjax');
function simplelikesAjax()
{
    global $mybb, $db, $lang, $templates, $theme;

    if ($mybb->input['action'] == 'like_post') {
        if (!verify_post_check($mybb->input['my_post_key'], true)) {
            xmlhttp_error($lang->invalid_post_code);
        }

        if (!$lang->simplelikes) {
            $lang->load('simplelikes');
        }

        if (!isset($mybb->input['post_id'])) {
            xmlhttp_error($lang->simplelikes_error_post_id);
        }

        $postId = (int) $mybb->input['post_id'];
        $post   = get_post($postId);

        if (!$mybb->settings['simplelikes_can_like_own'] AND $post['uid'] == $mybb->user['uid']) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower(
                    $_SERVER['HTTP_X_REQUESTED_WITH']
                ) == 'xmlhttprequest'
            ) {
                header('Content-type: application/json');
                echo json_encode(array('error' => $lang->simplelikes_error_own_post));
            } else {
                xmlhttp_error($lang->simplelikes_error_own_post);
            }
            die();
        }

        if (!$mybb->usergroup['simplelikes_can_like']) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) AND strtolower(
                    $_SERVER['HTTP_X_REQUESTED_WITH']
                ) == 'xmlhttprequest'
            ) {
                header('Content-type: application/json');
                echo json_encode(array('error' => $lang->simplelikes_error_perms));
            } else {
                xmlhttp_error($lang->simplelikes_error_perms);
            }
            die();
        }

        try {
            $likeSystem = new MybbStuff\SimpleLikes\LikeManager($mybb, $db, $lang);
        } catch (InvalidArgumentException $e) {
            xmlhttp_error($e->getMessage());

            return;
        }

        $buttonText = $lang->simplelikes_like;

        $result = $likeSystem->likePost($postId);

        if ($mybb->settings['myalerts_alert_simplelikes']) {
            global $Alerts;

            if (isset($Alerts) AND $Alerts instanceof Alerts AND $mybb->settings['myalerts_enabled']) {
                if ($result == 0) {
                    $query   = $db->simple_select(
                        'alerts',
                        'id',
                        "alert_type = 'simplelikes' AND tid = {$postId} AND uid = " . (int) $mybb->user['uid']
                    );
                    $alertId = $db->fetch_field($query, 'id');
                    $Alerts->deleteAlerts($alertId);
                } else {
                    $query = $db->simple_select(
                        'alerts',
                        'id',
                        "alert_type = 'simplelikes' AND tid = {$postId} AND uid = " . (int) $mybb->user['uid']
                    );
                    if ($db->num_rows($query) == 0) {
                        unset($query);
                        $queryString = "SELECT s.*, v.*, u.uid FROM %salert_settings s LEFT JOIN %salert_setting_values v ON (v.setting_id = s.id) LEFT JOIN %susers u ON (v.user_id = u.uid) WHERE u.uid = " . (int) $post['uid'] . " AND s.code = 'simplelikes' LIMIT 1";
                        $query       = $db->write_query(
                            sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX, TABLE_PREFIX)
                        );

                        $userSetting = $db->fetch_array($query);

                        if ((int) $userSetting['value'] == 1) {
                            $Alerts->addAlert(
                                $post['uid'],
                                'simplelikes',
                                $postId,
                                $mybb->user['uid'],
                                array('tid' => $post['tid'])
                            );
                        }
                    }
                }
            }

            if ($result != 0) {
                $buttonText = $lang->simplelikes_unlike;
            }
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
                $_SERVER['HTTP_X_REQUESTED_WITH']
            ) == 'xmlhttprequest'
        ) {
            header('Content-type: application/json');
            $postLikes          = array();
            $postLikes[$postId] = $likeSystem->getLikes($postId);
            $likeString         = '';
            $likeString         = $likeSystem->formatLikes($postLikes, $post);
            $templateString     = '';
            eval("\$templateString = \"" . $templates->get('simplelikes_likebar') . "\";");
            echo json_encode(
                array(
                    'postId'         => $postId,
                    'likeString'     => $likeString,
                    'templateString' => $templateString,
                    'buttonString'   => $buttonText
                )
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
