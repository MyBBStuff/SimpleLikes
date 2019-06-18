<?php
declare(strict_types=1);

use MybbStuff\Core\ClassLoader;
use MybbStuff\SimpleLikes\Import\Manager;
use MybbStuff\SimpleLikes\Import\ThankYouLikeImporter;
use MybbStuff\SimpleLikes\LikeManager;

defined(
    'IN_MYBB'
) or die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');

defined('MYBBSTUFF_CORE_PATH') || define('MYBBSTUFF_CORE_PATH', __DIR__ . '/MybbStuff/Core');
define('SIMPLELIKES_PLUGIN_PATH', __DIR__ . '/MybbStuff/SimpleLikes');

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

require_once MYBBSTUFF_CORE_PATH . '/src/ClassLoader.php';

$classLoader = ClassLoader::getInstance();
$classLoader->registerNamespace(
    'MybbStuff\\SimpleLikes\\',
    SIMPLELIKES_PLUGIN_PATH . '/src/'
);
$classLoader->register();

$importManager = Manager::getInstance();
$importManager->addImporter(ThankYouLikeImporter::class);

function simplelikes_info()
{
    global $lang, $cache;
    if (isset($cache->cache['plugins']['active']['simplelikes'])) {
        simplelikes_tapatalk_integration();
    }

    return [
        'name' => 'Like System',
        'description' => 'A simple post like system.' . $lang->simplelikes_tapatalk_core_edits,
        'website' => 'http://www.mybbstuff.com',
        'author' => 'Euan T',
        'authorsite' => 'http://www.euantor.com',
        'version' => '2.0.1',
        'codename' => 'mybbstuff_simplelikes',
        'compatibility' => '18*',
    ];
}

function simplelikes_install()
{
    global $db, $cache;

    $pluginInfo = simplelikes_info();
    $euantorPlugins = $cache->read('euantor_plugins');
    $euantorPlugins['simplelikes'] = [
        'title' => 'SimpleLikes',
        'version' => $pluginInfo['version'],
    ];
    $cache->update('euantor_plugins', $euantorPlugins);

    $prefix = TABLE_PREFIX;

    if (!$db->table_exists('post_likes')) {
        switch ($db->type) {
            case 'pgsql':
            case 'mysql':
            case 'mysqli':
                $createTableQuery = <<<SQL
CREATE TABLE {$prefix}post_likes(
	id SERIAL PRIMARY KEY,
	post_id INT NOT NULL,
	user_id INT NOT NULL,
	created_at TIMESTAMP,
	CONSTRAINT unique_post_user_id UNIQUE (post_id,user_id)
);

CREATE INDEX post_id_index ON {$prefix}post_likes (post_id);

CREATE INDEX user_id_index ON {$prefix}post_likes (user_id);
SQL;
                break;
            case 'sqlite':
                $createTableQuery = <<<SQL
CREATE TABLE IF NOT EXISTS {$prefix}post_likes(
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	post_id INTEGER unsigned NOT NULL,
	user_id INTEGER unsigned NOT NULL,
	created_at TIMESTAMP,
	CONSTRAINT unique_post_user_id UNIQUE (post_id,user_id)
);

CREATE INDEX post_id_index ON {$prefix}post_likes (post_id);

CREATE INDEX user_id_index ON {$prefix}post_likes (user_id);
SQL;
                break;
            default:
                flash_message("Unsupported database engine '{$db->type}'", 'error');
                admin_redirect('index.php?module=config-plugins');

                return;
        }

        $db->write_query($createTableQuery);
    }

    if (!$db->field_exists('simplelikes_can_like', 'usergroups')) {
        switch ($db->type) {
            case 'pgsql':
                $columnDefinition = "smallint NOT NULL default '0'";
                break;
            default:
                $columnDefinition = "tinyint(1) NOT NULL default '0'";
                break;
        }

        $db->add_column('usergroups', 'simplelikes_can_like', $columnDefinition);
    }

    if (!$db->field_exists('simplelikes_can_view_likes', 'usergroups')) {
        switch ($db->type) {
            case 'pgsql':
                $columnDefinition = "smallint NOT NULL default '0'";
                break;
            default:
                $columnDefinition = "tinyint(1) NOT NULL default '0'";
                break;
        }

        $db->add_column('usergroups', 'simplelikes_can_view_likes', $columnDefinition);
    }

    $db->update_query(
        'usergroups',
        [
            'simplelikes_can_like' => 1,
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
    global $db, $PL, $cache, $lang;

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message('This plugin requires PluginLibrary, please ensure it is installed correctly.', 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or require_once PLUGINLIBRARY;

    // Revert changes to tapatalk files
    if (simplelikes_tapatalk_edits_revert() !== true) {
        flash_message($lang->simplelikes_tapatalk_revert_error, 'error');
        admin_redirect('index.php?module=config-plugins');
    }

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
    global $PL, $cache;

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

    $pluginInfo = simplelikes_info();
    $pluginsCache = $cache->read('euantor_plugins');

    if (isset($pluginsCache['simplelikes']['version'])) {
        $oldVersion = $pluginsCache['simplelikes']['version'];
    }
    $newVersion = $pluginInfo['version'];

    $pluginsCache['simplelikes'] = [
        'title' => 'SimpleLikes',
        'version' => $pluginInfo['version'],
    ];
    $cache->update('euantor_plugins', $pluginsCache);

    simpleLikesUpgrade($oldVersion, $newVersion);

    $PL->settings(
        'simplelikes',
        'Like System Settings',
        'Settings for the like system.',
        [
            'num_users' => [
                'title' => 'Number of "likers" to show per post',
                'description' => 'Set the number of most recent likers to show in the post like bar.',
                'value' => '3',
                'optionscode' => 'text',
            ],
            'can_like_own' => [
                'title' => 'Let users like own posts?',
                'description' => 'Set whether users can "like" their own posts.',
                'value' => '0',
            ],
            'get_num_likes_user_postbit' => [
                'title' => 'Number of likes received in postbit?',
                'description' => 'Do you wish to get how many likes a user has received in the postbit? Beware that this adds an extra query.',
                'value' => '0',
            ],
            'likes_per_page' => [
                'title' => 'Likes per page',
                'description' => 'The number of likes to show per page.',
                'value' => 20,
                'optionscode' => 'numeric',
            ],
            'avatar_dimensions' => [
                'title' => 'Avatar Dimensions',
                'description' => 'The maximum avatar dimensions to use in the "who liked this" modal; width by height (e.g. 64|64).',
                'value' => '64|64',
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

function simpleLikesUpgrade($oldVersion, $newVersion)
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

$plugins->add_hook('admin_user_groups_edit_graph_tabs', 'simpleLikesAdminUserGroupPermissionsTab');
function simpleLikesAdminUserGroupPermissionsTab(&$tabs)
{
    global $lang;

    if (!isset($lang->simplelikes)) {
        $lang->load('simplelikes');
    }

    $tabs['simplelikes'] = $lang->simplelikes;
}

$plugins->add_hook('admin_user_groups_edit_graph', 'simpleLikesAdminUserGroupPermissions');
function simpleLikesAdminUserGroupPermissions()
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

$plugins->add_hook('admin_user_groups_edit_commit', 'simpleLikesAdminUserGroupPermissionsSave');
function simpleLikesAdminUserGroupPermissionsSave()
{
    global $updated_group, $mybb;

    $updated_group['simplelikes_can_like'] = $mybb->get_input('simplelikes_can_like', MyBB::INPUT_INT);
    $updated_group['simplelikes_can_view_likes'] = $mybb->get_input('simplelikes_can_view_likes', MyBB::INPUT_INT);
}

$plugins->add_hook('postbit', 'simpleLikesPostBit');
function simpleLikesPostBit(&$post)
{
    global $mybb, $db, $templates, $pids, $postLikeBar, $lang;

    if (!isset($lang->simplelikes)) {
        $lang->load('simplelikes');
    }

    $likeSystem = new LikeManager($mybb, $db, $lang);

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
        $tablePrefix = TABLE_PREFIX;

        if (is_string($pids)) {
            static $postLikesReceived = null;
            if (!is_array($postLikesReceived)) {
                $postLikesReceived = [];

                $queryString = <<<SQL
SELECT p.uid, 
       (SELECT COUNT(*) 
       FROM {$tablePrefix}post_likes l 
           INNER JOIN {$tablePrefix}posts mp ON (l.post_id = mp.pid) 
       WHERE mp.uid = p.uid) 
           AS numlikes 
FROM {$tablePrefix}posts p 
WHERE {$pids} 
GROUP BY p.uid
SQL;
                $query = $db->query($queryString);

                while ($row = $db->fetch_array($query)) {
                    $postLikesReceived[(int)$row['uid']] = (int)$row['numlikes'];
                }
            }
        } else {
            $postLikesReceived = [];
            $pid = (int)$post['pid'];

            $queryString = <<<SQL
SELECT p.uid, 
       (SELECT COUNT(*) 
       FROM {$tablePrefix}post_likes l 
           INNER JOIN {$tablePrefix}posts mp ON (l.post_id = mp.pid) 
       WHERE mp.uid = p.uid) 
           AS numlikes 
FROM {$tablePrefix}posts p 
WHERE pid = {$pid} 
GROUP BY p.uid
SQL;

            $query = $db->query($queryString);

            $postLikesReceived[(int)$post['uid']] = (int)$db->fetch_field($query, 'numlikes');
        }

        if (array_key_exists((int)$post['uid'], $postLikesReceived)) {
            $post['likes_received'] = my_number_format($postLikesReceived[(int)$post['uid']]);
        } else {
            $post['likes_received'] = 0;
        }
    }
}

$plugins->add_hook('member_profile_end', 'simpleLikesProfile');
function simpleLikesProfile()
{
    global $mybb, $db, $lang, $memprofile, $templates, $postsLiked, $likesReceived;

    if (!isset($lang->simplelikes)) {
        $lang->load('simplelikes');
    }

    $uid = (int)$memprofile['uid'];

    // Number of likes user has made
    $query = $db->simple_select('post_likes', 'COUNT(*) AS count', 'user_id = ' . $uid);
    $usersLikes = my_number_format((int)$db->fetch_field($query, 'count'));
    $postsLiked = eval($templates->render('simplelikes_profile_total_likes'));

    $tablePrefix = TABLE_PREFIX;

    // Number of likes user's posts have
    $queryString = <<<SQL
SELECT COUNT(*) AS numlikes 
FROM {$tablePrefix}post_likes l 
    INNER JOIN {$tablePrefix}posts p ON (l.post_id = p.pid) 
WHERE p.uid = {$uid};
SQL;

    $query = $db->write_query($queryString);
    $postLikes = my_number_format((int)$db->fetch_field($query, 'numlikes'));
    $likesReceived = eval($templates->render('simplelikes_profile_likes_received'));
}

$plugins->add_hook('myalerts_load_lang', 'simpleLikesAlertSettings');
function simpleLikesAlertSettings()
{
    global $lang;

    if (!isset($lang->simplelikes)) {
        $lang->load('simplelikes');
    }

    $lang->myalerts_setting_simplelikes = $lang->simplelikes_alert_setting;
}

$plugins->add_hook('global_start', 'simpleLikesGlobal', -1);
function simpleLikesGlobal()
{
    global $templatelist, $mybb;

    if (!isset($templatelist) || empty($templatelist)) {
        $templatelist = '';
    }

    simpleLikesInitMyAlertsFormatter();

    $templatelist .= ',';

    if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes_by_user') {
        $templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_by_user_row,simplelikes_likes_by_user';
    }

    if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes_received_by_user') {
        $templatelist .= 'multipage_page_current,multipage_page,multipage_nextpage,multipage,simplelikes_likes_received_by_user_row,simplelikes_likes_received_by_user';
    }

    if (THIS_SCRIPT == 'misc.php' && $mybb->input['action'] == 'post_likes') {
        $templatelist .= 'simplelikes_likes_popup_liker,simplelikes_likes_popup,simplelikes_likes_popup_nopermission';
    }

    if (THIS_SCRIPT == 'member.php' && $mybb->input['action'] == 'profile') {
        $templatelist .= 'simplelikes_profile_total_likes,simplelikes_profile_likes_received';
    }

    if (THIS_SCRIPT == 'showthread.php') {
        $templatelist .= 'simplelikes_likebutton,simplelikes_likebar';
    }
}

function simpleLikesInitMyAlertsFormatter()
{
    if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {
        global $mybb, $lang;

        /** @var MybbStuff_MyAlerts_AlertFormatterManager $formatterManager */
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if ($formatterManager === false) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(\MybbStuff\SimpleLikes\LikeFormatter::class);
    }
}

$plugins->add_hook('misc_start', 'simpleLikesMisc');
function simpleLikesMisc()
{
    global $mybb;

    switch ($mybb->get_input('action', MyBB::INPUT_STRING)) {
        case 'post_likes':
            simpleLikesMiscPostLikes();
            break;
        case 'post_likes_by_user':
            simpleLikesMiscPostLikesByUser();
            break;
        case 'post_likes_received_by_user':
            simpleLikesMiscPostLikesReceivedByUser();
            break;
    }
}

function simpleLikesMiscPostLikes()
{
    global $mybb, $db, $templates, $theme, $headerinclude, $lang;

    $lang->load('simplelikes');

    if (!$mybb->usergroup['simplelikes_can_view_likes']) {
        $likes = eval($templates->render('simplelikes_likes_popup_nopermission', true, false));
    } else {
        if (!isset($mybb->input['post_id'])) {
            error($lang->simplelikes_error_post_id);
        }

        $pid = $mybb->get_input('post_id', MyBB::INPUT_INT);

        $likeSystem = new LikeManager($mybb, $db, $lang);

        $likeArray = $likeSystem->getLikes($pid);

        if (empty($likeArray)) {
            $likes = eval($templates->render('simplelikes_likes_popup_no_likes', true, false));
        } else {
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
        }
    }

    $page = eval($templates->render('simplelikes_likes_popup', true, false));
    echo $page;
}

function simpleLikesMiscPostLikesByUser()
{
    global $mybb, $db, $templates, $lang, $headerinclude, $header, $footer, $theme, $multipage;

    if (!$mybb->usergroup['simplelikes_can_view_likes']) {
        error_no_permission();

        return;
    }

    $lang->load('simplelikes');

    if (!isset($mybb->input['user_id']) || (int)$mybb->input['user_id'] == 0) {
        error($lang->simplelikes_error_user_id);

        return;
    }

    $userId = $mybb->get_input('user_id', MyBB::INPUT_INT);
    $user = get_user($userId);

    require_once MYBB_ROOT . 'inc/functions_search.php';
    $whereSql = '';
    $unSearchableForums = get_unsearchable_forums();
    if ($unSearchableForums) {
        $whereSql .= " AND p.fid NOT IN ({$unSearchableForums})";
    }
    $inactiveForums = get_inactive_forums();
    if ($inactiveForums) {
        $whereSql .= " AND p.fid NOT IN ({$inactiveForums})";
    }

    $tablePrefix = TABLE_PREFIX;

    $queryString = <<<SQL
SELECT COUNT(*) AS numlikes 
FROM {$tablePrefix}post_likes l 
    INNER JOIN {$tablePrefix}posts p ON (l.post_id = p.pid) 
WHERE l.user_id = {$userId}{$whereSql};
SQL;

    $query = $db->query($queryString);
    $count = $db->fetch_field($query, 'numlikes');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $perPage = $mybb->settings['simplelikes_likes_per_page'];
    $pages = $count / $perPage;
    $pages = ceil($pages);
    if ($mybb->input['page'] == "last") {
        $page = $pages;
    }

    if ($page > $pages || $page <= 0) {
        $page = 1;
    }

    if ($page && $page > 0) {
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
    $queryString = <<<SQL
SELECT p.*, l.created_at FROM {$tablePrefix}post_likes l 
    INNER JOIN {$tablePrefix}posts p ON (l.post_id = p.pid) 
WHERE l.user_id = {$userId}{$whereSql} 
ORDER BY l.id DESC 
LIMIT {$perPage} OFFSET {$start}
SQL;

    $query = $db->query($queryString);
    while ($like = $db->fetch_array($query)) {
        $altbg = alt_trow();
        $like['postlink'] = get_post_link((int)$like['pid']) . '#pid' . (int)$like['pid'];
        $like['subject'] = htmlspecialchars_uni($like['subject']);

        $createdAt = new DateTime($like['created_at']);

        $like['created_at'] = my_date($mybb->settings['dateformat'],
                $createdAt->getTimestamp()) . ' ' . my_date($mybb->settings['timeformat'],
                $createdAt->getTimestamp());

        $likes .= eval($templates->render('simplelikes_likes_by_user_row'));
    }

    $page = eval($templates->render('simplelikes_likes_by_user'));
    output_page($page);
}

function simpleLikesMiscPostLikesReceivedByUser()
{
    global $mybb, $db, $templates, $lang, $headerinclude, $header, $footer, $theme, $multipage;

    if (!$mybb->usergroup['simplelikes_can_view_likes']) {
        error_no_permission();
    }

    $lang->load('simplelikes');

    if (!isset($mybb->input['user_id']) OR (int)$mybb->input['user_id'] == 0) {
        error($lang->simplelikes_error_user_id);

        return;
    }

    $userId = $mybb->get_input('user_id', MyBB::INPUT_INT);
    $user = get_user($userId);

    require_once MYBB_ROOT . 'inc/functions_search.php';
    $where_sql = '';
    $unsearchableForums = get_unsearchable_forums();
    if ($unsearchableForums) {
        $where_sql .= " AND p.fid NOT IN ({$unsearchableForums})";
    }
    $inactiveForums = get_inactive_forums();
    if ($inactiveForums) {
        $where_sql .= " AND p.fid NOT IN ({$inactiveForums})";
    }

    $tablePrefix = TABLE_PREFIX;

    $queryString = <<<SQL
SELECT COUNT(*) AS num
FROM {$tablePrefix}post_likes l 
    INNER JOIN {$tablePrefix}posts p ON (l.post_id = p.pid) 
WHERE p.uid = {$userId}{$where_sql};
SQL;

    $query = $db->query($queryString);
    $count = (int) $db->fetch_field($query, 'num');

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
    $queryString = <<<SQL
SELECT p.*, (SELECT COUNT(*) FROM {$tablePrefix}post_likes l WHERE l.post_id = p.pid) AS numlikes 
FROM {$tablePrefix}posts p
INNER JOIN {$tablePrefix}post_likes l ON (p.pid = l.post_id)
WHERE p.uid = {$userId}{$where_sql} 
ORDER BY p.pid DESC 
LIMIT {$perPage} OFFSET {$start};
SQL;

    $query = $db->query($queryString);

    while ($like = $db->fetch_array($query)) {
        $altbg = alt_trow();
        $like['postlink'] = get_post_link((int)$like['pid']) . '#pid' . (int)$like['pid'];
        $like['subject'] = htmlspecialchars_uni($like['subject']);
        $like['count'] = my_number_format((int)$like['numlikes']);
        $like['pid'] = (int) $like['pid'];

        $likes .= eval($templates->render('simplelikes_likes_received_by_user_row'));
    }

    $page = eval($templates->render('simplelikes_likes_received_by_user'));
    output_page($page);
}

$plugins->add_hook('xmlhttp', 'simplelikesAjax');
function simpleLikesAjax()
{
    global $mybb, $db, $lang, $templates, $theme;

    if ($mybb->get_input('action', MyBB::INPUT_STRING) !== 'like_post') {
        return;
    }

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        xmlhttp_error($lang->invalid_post_code);

        return;
    }

    $lang->load('simplelikes');

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

        $likeSystem = new LikeManager($mybb, $db, $lang);

        $buttonText = $lang->simplelikes_like;

        $result = $likeSystem->likePost($postId);

        if ($result === LikeManager::RESULT_LIKED) {
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
                            'subject' => $post['subject'],
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
            $likeString = $likeSystem->formatLikes($postLikes, $post);

            $templateString = eval($templates->render('simplelikes_likebar'));
            echo json_encode(
                [
                    'postId' => $postId,
                    'likeString' => $likeString,
                    'templateString' => $templateString,
                    'buttonString' => $buttonText,
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

function simplelikes_tapatalk_integration()
{
    global $lang, $cache, $mybb;
    if (is_dir(MYBB_ROOT . 'mobiquo/')) {
        if (!isset($lang->simplelikes)) {
            $lang->load('simplelikes');
        }
        $lang->simplelikes_tapatalk_core_edits = '<br /><br />' . $lang->simplelikes_tapatalk_integration;
        if (simplelikes_tapatalk_edits() !== true) {
            $lang->simplelikes_tapatalk_core_edits .= '<a href="index.php?module=config-plugins&amp;action=simplelikes_tapatalk_apply_changes&amp;my_post_key=' . $mybb->post_code . '">' . $lang->simplelikes_tapatalk_apply_changes . '</a>';
        } else {
            $lang->simplelikes_tapatalk_core_edits .= '<a href="index.php?module=config-plugins&amp;action=simplelikes_tapatalk_revert_changes&amp;my_post_key=' . $mybb->post_code . '">' . $lang->simplelikes_tapatalk_revert_changes . '</a>';
        }
    } else {
        $lang->simplelikes_tapatalk_core_edits = '';
    }
}

$plugins->add_hook('admin_config_plugins_begin', 'simplelikes_tapatalk_apply_revert_edits');
function simplelikes_tapatalk_apply_revert_edits()
{
    global $mybb, $lang;
    if ($mybb->input['my_post_key'] == $mybb->post_code) {
        if (!isset($lang->simplelikes)) {
            $lang->load('simplelikes');
        }
        if ($mybb->input['action'] == 'simplelikes_tapatalk_apply_changes') {
            if (simplelikes_tapatalk_edits(true) === true) {
                flash_message($lang->simplelikes_tapatalk_apply_success, 'success');
                admin_redirect('index.php?module=config-plugins');
            } else {
                flash_message($lang->simplelikes_tapatalk_apply_error, 'error');
                admin_redirect('index.php?module=config-plugins');
            }
        }

        if ($mybb->input['action'] == 'simplelikes_tapatalk_revert_changes') {
            if (simplelikes_tapatalk_edits_revert() === true) {
                flash_message($lang->simplelikes_tapatalk_revert_success, 'success');
                admin_redirect('index.php?module=config-plugins');
            } else {
                flash_message($lang->simplelikes_tapatalk_revert_error, 'error');
                admin_redirect('index.php?module=config-plugins');
            }
        }
    }
}

function simplelikes_tapatalk_edits($apply = false)
{
    global $PL;
    $PL or require_once PLUGINLIBRARY;
    $edits = simplelikes_tapatalk_get_edits();
    foreach ($edits as $edit) {
        $result = $PL->edit_core('simplelikes_tapatalk', $edit['file'], $edit['changes'], $apply);
        if ($result !== true) {
            return false;
        }
    }
    return true;
}

function simplelikes_tapatalk_edits_revert()
{
    global $PL;
    $PL or require_once PLUGINLIBRARY;
    $edits = simplelikes_tapatalk_get_edits();
    foreach ($edits as $edit) {
        $result = $PL->edit_core('simplelikes_tapatalk', $edit['file'], [], true);
        if ($result !== true) {
            return false;
        }
    }
    return true;
}

function simplelikes_tapatalk_get_edits()
{
    $edits = [];

    $edits[] = [
        'file' => 'mobiquo/env_setting.php',
        'changes' => [
            'search' => ['if ($request_method && isset($server_param[$request_method]))', '{'],
            'after' => [
                '// Thank you/like or simple likes?',
                'if ($function_file_name == \'thankyoulike\' && isset($cache->cache[\'plugins\'][\'active\'][\'simplelikes\'])) {',
                '	$function_file_name = \'simplelikes\';',
                '	$_GET[\'post_id\'] = $mybb->input[\'post_id\'] = $request_params[0];',
                '	$_GET[\'action\'] = $mybb->input[\'action\'] = \'like_post\';',
                '	include dirname(__DIR__) . \'/xmlhttp.php\';',
                '} else',
            ],
        ],
    ];

    $edits[] = [
        'file' => 'mobiquo/include/get_thread.php',
        'changes' => [
            [
                'search' => ['while($post = $db->fetch_array($query))', '{'],
                'before' => [
                    '// Simple likes get post likes',
                    '$likeSystem = new MybbStuff_SimpleLikes_LikeManager($mybb, $db, $lang);',
                    '$postLikes = $likeSystem->getLikes($pids);',
                ],
            ],
            [
                'search' => ['$post_list[] = new xmlrpcval($post_xmlrpc, \'struct\')', ';'],
                'before' => [
                    '// add for simple likes support',
                    'if (isset($post[\'button_like\']) && $mybb->user[\'uid\']) {',
                    '	$liked = false;',
                    '	$likes_list = array();',
                    '	if ($post[\'simplelikes\'] && isset($postLikes[$post[\'pid\']])) {',
                    '		foreach ($postLikes[$post[\'pid\']] as $like) {',
                    '			if ($like[\'user_id\'] == $mybb->user[\'uid\']) {',
                    '				$liked = true;',
                    '			}',
                    '			if ($mybb->usergroup[\'simplelikes_can_view_likes\'] || $like[\'user_id\'] == $mybb->user[\'uid\']) {',
                    '				$likes_list[] = new xmlrpcval(array(',
                    '					\'userid\'    => new xmlrpcval($like[\'user_id\'], \'string\'),',
                    '					\'username\'  => new xmlrpcval(basic_clean($like[\'username\']), \'base64\')',
                    '				), \'struct\');',
                    '			}',
                    '		}',
                    '	}',
                    '	if ($post[\'button_like\']) {',
                    '		$post_xmlrpc[\'can_like\'] = new xmlrpcval(true, \'boolean\');',
                    '	}',
                    '	if ($liked) {',
                    '		$post_xmlrpc[\'is_liked\'] = new xmlrpcval(true, \'boolean\');',
                    '	}',
                    '	if ($likes_list) {',
                    '		$post_xmlrpc[\'likes_info\'] = new xmlrpcval($likes_list, \'array\');',
                    '	}',
                    '}',
                ],
            ],
        ],
    ];

    $edits[] = [
        'file' => 'mobiquo/include/get_user_info.php',
        'changes' => [
            'search' => '// thank you/like field',
            'before' => [
                '// simple likes field',
                'if (isset($cache->cache[\'plugins\'][\'active\'][\'simplelikes\'])) {',
                '    $lang->load(\'simplelikes\');',
                '',
                '    $query = $db->simple_select(\'post_likes\', \'COUNT(id) AS count\', \'user_id = \' . $uid);',
                '    $usersLikes = my_number_format((int)$db->fetch_field($query, \'count\'));',
                '    ',
                '    $queryString = "SELECT COUNT(l.id) AS count FROM %spost_likes l INNER JOIN %sposts p ON (l.post_id = p.pid) WHERE p.uid = {$uid}";',
                '    $query = $db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));',
                '    $postLikes = my_number_format((int)$db->fetch_field($query, \'count\'));',
                '',
                '    addCustomField($lang->simplelikes_total_likes_received, $postLikes, $custom_fields_list);',
                '    addCustomField($lang->simplelikes_total_likes, $usersLikes, $custom_fields_list);',
                '',
                '    $custom_fields_list_arr[] = array(',
                '        \'name\'  => basic_clean($lang->simplelikes_total_likes_received),',
                '        \'value\' => basic_clean($postLikes),',
                '    );',
                '    $custom_fields_list_arr[] = array(',
                '        \'name\'  => basic_clean($lang->simplelikes_total_likes),',
                '        \'value\' => basic_clean($usersLikes),',
                '    );',
                '}',
            ],
        ],
    ];

    return $edits;
}
