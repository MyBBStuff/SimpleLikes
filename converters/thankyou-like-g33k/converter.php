<?php
define('IN_MYBB', 1);
require_once "../../inc/init.php";

define('SIMPLELIKES_CONVERSION_SCRIPT', 'THANKYOU_LIKE_G33K');
ini_set('max_execution_time', 300);

$batch = 0;
$total = 0;
$newLikes = array();

$query = $db->simple_select('g33k_thankyoulike_thankyoulike', '*');
while ($like = $db->fetch_array($query)) {
    $newLikes[] = array(
        'post_id' => (int) $like['pid'],
        'user_id' => (int) $like['uid'],
    );

	$batch++;
	$total++;

	if($batch == 1000) {
		$db->insert_query_multiple('post_likes', $newLikes);

		$newLikes = array();
		$batch = 0;
		echo "Converted {$total} old like(s) to Simple Likes<br/>";
	}
}

$db->insert_query_multiple('post_likes', $newLikes);

echo "Converted {$total} old like(s) to Simple Likes";
