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
	 * Create a new Likes object.
	 *
	 * @param MyBB $mybbIn The MyBB object.
	 * @param DB_* $dbIn A Database instance object of type DB_MySQL, DB_MySQLi, DB_PgSQL or DB_SQLite.
	 * @return null
	 */
	public function __construct(MyBB $mybbIn, $dbIn)
	{
		$this->mybb = $mybbIn;

		if ($dbIn instanceof DB_MySQL OR $dbIn instanceof DB_MySQLi OR $dbIn instanceof DB_PgSQL OR $dbIn instanceof DB_SQLite) {
			$this->db = $dbIn;
		} else {
			throw new InvalidArgumentException('Expected object of class DB_MySQL|DB_MySQLi|DB_PgSQL|DB_SQLite, but found '.get_class($dbIn));
		}
	}

	/**
	 * Add or remove a like for a specific post. Likes act as if toggled.
	 *
	 * @param int $pid The post id to (un)like.
	 * @return int|resource The insert id or the query data.
	 */
	public function likePost($pid)
	{
		$pid = (int) $pid;
		$uid = (int) $this->mybb->user['uid'];

		$query = $this->db->simple_select('post_likes', '*', "post_id = {$pid} AND user_id = {$uid}", array('limit' => 1));
		if ($this->db->num_rows($query) > 0) {
			return $this->db->delete_query('post_likes', "post_id = {$pid} AND user_id = {$uid}", 1);
		} else {
			$insertArray = [
				'post_id' => $pid,
				'user_id' => $uid,
			];

			return $this->db->insert_query('post_likes', $insertArray);
		}
	}

	/**
	 * Get all likes for a specific post or set of posts.
	 *
	 * @param int|array $pid The post id(s) to fetch likes for.
	 * @return array The likes, along with the user details for the user that performed the like.
	 */
	public function getLikes($pid)
	{
		$likes = array();
		if (is_string($pid)) {
			$inClause = str_replace('pid', 'l.post_id', $pid);

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE {$inClause}";
			$query = $this->db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[(int) $like['post_id']][] = $like;
			}
		} else {
			$pid = (int) $pid;

			$queryString = "SELECT l.*, u.username, u.avatar, u.usergroup, u.displaygroup FROM %spost_likes l LEFT JOIN %susers u ON (l.user_id = u.uid) WHERE l.post_id = {$pid}";
			$query = $this->db->write_query(sprintf($queryString, TABLE_PREFIX, TABLE_PREFIX));
			while ($like = $this->db->fetch_array($query)) {
				$likes[] = $like;
			}
		}

		return $likes;
	}
}