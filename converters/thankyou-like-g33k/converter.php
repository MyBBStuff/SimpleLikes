<?php
define('IN_MYBB', 1);
require_once "../../inc/init.php";

define('SIMPLELIKES_CONVERSION_SCRIPT', 'THANKYOU_LIKE_G33K');

$newLikes = array();
$query = $db->simple_select('g33k_thankyoulike_thankyoulike', '*');
while ($like = $db->fetch_array($query)) {
    $newLikes[] = array(
        'post_id' => (int) $like['pid'],
        'user_id' => (int) $like['uid'],
    );
}

$db->insert_query_multiple('post_likes', $newLikes);

echo 'Converted '.count($newLikes).' old like(s) to Simple Likes';