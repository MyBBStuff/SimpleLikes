<?php

/**
 * Likes class.
 *
 * Handles CRUD operations for post likes.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0.0
 */
class MybbStuff_SimpleLikes_LikeManager
{
	/**
	 * @var DB_Base $db
	 */
	private $db;

	/**
	 * @var MyBB $mybb
	 */
	private $mybb;

	/**
	 * @var MyLanguage $lang
	 */
	private $lang;

	/**
	 * Create a new Likes object.
	 *
	 * @param MyBB $mybb The MyBB object.
	 * @param DB_Base $db A Database instance object of type DB_MySQL, DB_MySQLi, DB_PgSQL or DB_SQLite.
	 * @param MyLanguage $lang The language class from MyBB used to manage language files and strings.
	 */
	public function __construct(MyBB $mybb, DB_Base $db, MyLanguage $lang)
	{
		$this->mybb = $mybb;
		$this->db = $db;
		$this->lang = $lang;
	}

	/**
	 * Add or remove a like for a specific post. Likes act as if toggled.
	 *
	 * @param int $postId The post id to (un)like.
	 *
	 * @return int The insert id or 0 if the action was a like deletion.
	 */
	public function likePost($postId)
	{
		$postId = (int)$postId;
		$userId = (int)$this->mybb->user['uid'];

		$query = $this->db->simple_select(
			'post_likes',
			'*',
			"post_id = {$postId} AND user_id = {$userId}",
			['limit' => 1]
		);

		$createdAt = new DateTime();
		$timestamp = $createdAt->format('Y-m-d H:i:s');

		if ($this->db->num_rows($query) > 0) {
			$this->db->delete_query('post_likes', "post_id = {$postId} AND user_id = {$userId}", 1);

			return 0;
		} else {
			$insertArray = [
				'post_id'    => $postId,
				'user_id'    => $userId,
				'created_at' => $this->db->escape_string($timestamp),
			];

			return $this->db->insert_query('post_likes', $insertArray);
		}
	}

	/**
	 * Get all likes for a specific post or set of posts.
	 *
	 * @param int|array $pid The post id(s) to fetch likes for.
	 *
	 * @return array The likes, along with the user details for the user that performed the like.
	 */
	public function getLikes($pid)
	{
		$likes = [];
		if (is_string($pid)) {
			$inClause = str_replace('pid', 'l.post_id', $pid);

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE {$inClause}";
			$query = $this->db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[(int)$like['post_id']][(int)$like['user_id']] = $like;
			}
		} else {
			$pid = (int)$pid;

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE l.post_id = {$pid}";
			$query = $this->db->query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[(int)$like['user_id']] = $like;
			}
		}

		return $likes;
	}

	/**
	 * Format likes into a string for output in the psotbit.
	 *
	 * @param array $postLikes An array of likes for posts.
	 * @param array $post The originator post's array.
	 *
	 * @return string The formatted likes.
	 */
	public function formatLikes($postLikes, $post)
	{
		$goTo = (int)$this->mybb->settings['simplelikes_num_users'];
		$likeArray = [];
		$likeString = '';

		if (!isset($this->lang->simplelikes)) {
			$this->lang->load('simplelikes');
		}

		if ($goTo == 0) {
			return '';
		}

		if (array_key_exists($this->mybb->user['uid'], $postLikes[(int)$post['pid']])) {
			$likeArray[] = $this->lang->simplelikes_you;
			unset($postLikes[(int)$post['pid']][(int)$this->mybb->user['uid']]);
			$goTo--;
		}

		if ($goTo > 0) {
			for ($i = 0; $i < $goTo; $i++) {
				if (!empty($postLikes[(int)$post['pid']])) {
					$random = $postLikes[$post['pid']][array_rand($postLikes[(int)$post['pid']])];
					$likeArray[] = build_profile_link(htmlspecialchars_uni($random['username']), $random['user_id']);
					unset($postLikes[(int)$post['pid']][$random['user_id']]);
				}
			}
		}

		if (!empty($likeArray)) {
			if (count($likeArray) == 1 AND $likeArray[0] != 'You') {
				$likePhrase = $this->lang->simplelikes_like_plural;
			} else {
				$likePhrase = $this->lang->simplelikes_like_singular;
			}
			$sep = (count($likeArray) == 2) ? ' ' . strtolower($this->lang->simplelikes_and) . ' ' : ', ';
			$likeString = implode($sep, $likeArray);
			if (!empty($postLikes[(int)$post['pid']])) {
				$likeString .= ' ' . $this->lang->simplelikes_and . ' <a href="#pid' . $post['pid'] . '" onclick="MyBB.popupWindow(\'/misc.php?action=post_likes&amp;post_id=' . $post['pid'] . '\', \'postLikes\', 350, 350); return false;">' . (int)count(
						$postLikes[(int)$post['pid']]
					) . ' ' . $this->lang->simplelikes_others . '</a>';
			}
			$likeString .= ' ' . $likePhrase . ' ' . $this->lang->simplelikes_this_post;
		}

		return $likeString;
	}
}
