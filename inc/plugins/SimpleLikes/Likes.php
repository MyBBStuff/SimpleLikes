<?php
/**
 * Likes class.
 *
 * Handles CRUD operations for post likes.
 *
 * @package Simple Likes
 * @author  Euan T. <euan@euantor.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.0
 */

class Likes
{
	/**
	 * Our Database connection object.
	 *
	 * @access private
	 * @var mixed
	 */
	private $db;

	/**
	 * Our MyBB object.
	 *
	 * @access private
	 * @var MyBB
	 */
	private $mybb;

	/**
	 * Our Language object from MyBB.
	 *
	 * @access private
	 * @var MyLanguage
	 */
	private $lang;

	/**
	 * Create a new Likes object.
	 *
	 * @param MyBB $mybbIn The MyBB object.
	 * @param      DB_     * $dbIn A Database instance object of type DB_MySQL, DB_MySQLi, DB_PgSQL or DB_SQLite.
	 *
	 * @return null
	 */
	public function __construct(MyBB $mybbIn, $dbIn, MyLanguage $langIn)
	{
		$this->mybb = $mybbIn;

		if ($dbIn instanceof DB_MySQL OR $dbIn instanceof DB_MySQLi OR $dbIn instanceof DB_PgSQL OR $dbIn instanceof DB_SQLite) {
			$this->db = $dbIn;
		} else {
			throw new InvalidArgumentException('Expected object of class DB_MySQL|DB_MySQLi|DB_PgSQL|DB_SQLite, but found '.get_class($dbIn));
		}

		$this->lang = $langIn;
	}

	/**
	 * Add or remove a like for a specific post. Likes act as if toggled.
	 *
	 * @param int $pid The post id to (un)like.
	 *
	 * @return int|string The insert id or a string "like deleted".
	 */
	public function likePost($pid)
	{
		$pid = (int) $pid;
		$uid = (int) $this->mybb->user['uid'];

		$query = $this->db->simple_select('post_likes', '*', "post_id = {$pid} AND user_id = {$uid}", array('limit' => 1));
		if ($this->db->num_rows($query) > 0) {
			$this->db->delete_query('post_likes', "post_id = {$pid} AND user_id = {$uid}", 1);

			return 'like deleted';
		} else {
			$insertArray = array(
				'post_id' => $pid,
				'user_id' => $uid,
			);

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
		$likes = array();
		if (is_string($pid)) {
			$inClause = str_replace('pid', 'l.post_id', $pid);

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE {$inClause}";
			$query       = $this->db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[(int) $like['post_id']][(int) $like['user_id']] = $like;
			}
		} else {
			$pid = (int) $pid;

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE l.post_id = {$pid}";
			$query       = $this->db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[(int) $like['user_id']] = $like;
			}
		}

		return $likes;
	}

	/**
	 * Format likes into a string for output in the psotbit.
	 *
	 * @param array $postLikes An array of likes for posts.
	 * @param array $post      The originator post's array.
	 *
	 * @return string The formatted likes.
	 */
	public function formatLikes($postLikes, $post)
	{
		$goTo       = (int) $this->mybb->settings['simplelikes_num_users'];
		$likeArray  = array();
		$likeString = '';

		if (!$this->lang->simplelikes) {
			$this->lang->load('simplelikes');
		}

		if ($goTo == 0) {
			return '';
		}

		if (array_key_exists($this->mybb->user['uid'], $postLikes[(int) $post['pid']])) {
			$likeArray[] = $this->lang->simplelikes_you;
			unset($postLikes[(int) $post['pid']][(int) $this->mybb->user['uid']]);
			$goTo--;
		}

		if ($goTo > 0) {
			for ($i = 0; $i < $goTo; $i++) {
				if (!empty($postLikes[(int) $post['pid']])) {
					$random      = $postLikes[$post['pid']][array_rand($postLikes[(int) $post['pid']])];
					$likeArray[] = build_profile_link(htmlspecialchars_uni($random['username']), $random['user_id']);
					unset($postLikes[(int) $post['pid']][$random['user_id']]);
				}
			}
		}

		if (!empty($likeArray)) {
			if (count($likeArray) == 1 AND $likeArray[0] != 'You') {
				$likePhrase = $this->lang->simplelikes_like_plural;
			} else {
				$likePhrase = $this->lang->simplelikes_like_singular;
			}
			$likeString = implode(', ', $likeArray);
			if (!empty($postLikes[(int) $post['pid']])) {
				$likeString .= ' and <a href="#pid'.$post['pid'].'" onclick="MyBB.popupWindow(\''.$this->mybb->settings['bburl'].'/misc.php?action=post_likes&amp;post_id='.$post['pid'].'\', \'buddyList\', 350, 350); return false;">'.(int) count($postLikes[(int) $post['pid']]).' '.$this->lang->simplelikes_others.'</a>';
			}
			$likeString .= ' '.$likePhrase.' '.$this->lang->simplelikes_this_post;
		}

		return $likeString;
	}
}
