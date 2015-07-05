<?php

/**
 *
 * get rid of the folder stuff
 * 
 *
 * VIEW vs GET
 *
 * get - run the query, return the result
 *
 * view - get it (run the query) and put result in context
 *
 *
 */

require_once "./include/parser.php";
require_once "./include/tags.php";
require_once "./include/comments.php";

Blog::$comments = new Comments(Blog::$table);

class Blog
{
	static public $table = 'blog';
	static public $raw = false; // don't parse any plaintext
	static public $comments;

	public static function install()
	{
		db::query("create table if not exists `".Blog::$table."` (
			`id` int(10) unsigned not null auto_increment,
			`title` tinytext not null,
			`search_title` tinytext not null,
			`icon` tinytext not null,
			`author_id` int(10) unsigned not null,
			`post_date` int(10) unsigned not null,
			`num_comments` int(10) unsigned not null default '0',
			`body` text not null,
			
			primary key (`id`)
		) default charset=utf8");
	}

	// set and format the search title
	public static function searchTitle($post)
	{
		if (!$post['search_title']) $post['search_title'] = $post['title'];
		return strtolower(preg_replace(array("/\t|\n| /", '/\W/'), array('_', ''), $post['search_title']));
	}

	/**
	 * insert a row into the blog
	 */
	public static function insert($what, $update = null)
	{
		$what['search_title'] = self::searchTitle($what);
		$set = "set
			title 		= '".db::escape($what['title'])."',
			search_title= '".db::escape($what['search_title'])."',
			icon 		= '".db::escape($what['icon'])."',
			author_id 	= ".intval($what['author_id']).",
			post_date 	= ".intval($what['post_date']).",
			body 		= '".db::escape($what['body'])."'";

		db::query("insert into ".Blog::$table." ".$set);
		return db::insertId();
	}

	/**
	 * update a row in the blog
	 */
	public static function update($id, $data)
	{
		$id = intval($id);
		if ($id == null) throw new Exception('invalid id');

		$data['search_title'] = self::searchTitle($data);

		// sanitize all the inputs
		foreach ($data as &$val) {
			$val = db::sanitize($val);
		}

		$query = 'update '.self::$table.' set ';
		// build the query
		foreach ($data as $k=>$v) {
			$query .= $k.' = '.$v.', '; 
		}

		$query = substr($query, 0, -2).' where id = '.$id;

		db::query($query);
	}

	/**
	 * delete a post
	 */
	public static function delete($id) 
	{
		$id = intval($id);
		if ($id == null) throw new Exception('invalid id');

		db::query('delete from '.Blog::$table.' where id = '.$id);
		Tags::clear($id);
	}
	

	/**
	 * get query dataset of specified blog posts
	 * $where sql where statement (not including the where)
	 * $body whether to fetch the body or not
	 * $join array of 'table_name' => 'table_alias'
	 * $select additional parameters to select (comma separated)
	 */
	private static function get($where, $body = true, $join = array(), $select = '')
	{
		$joinString = '';
		if (count($join) > 0) {
			foreach ($join as $k=>$v)
				$joinString .= 'inner join '.$k.' on '.$v."\n";
		}

		return db::query("select a.title, a.author_id, a.icon, a.post_date,
				a.num_comments, a.id, m.name as member_name, a.search_title,
				m.group_id".($body ? ', body' : '')."
			".$select."
		   	from ".Blog::$table." as a
			".$joinString."
			left join members as m on a.author_id = m.id".
			($where ? " where ".$where : '' ));
	}

	/**
	 * view a specified topic
	 * also get the tags for the topic
	 */
	public static function view($id)
	{
		if (($id = intval($id)) == NULL)
			throw new Exception('invalid id');
		
		$r = self::get('a.id = '.$id, true);
		if ($r->numRows == 0) throw new Exception('failed to find article');
		$a = $r->fetchAssoc();

		$article = self::context($a);
		$article['tags'] = Tags::getFor($a['id']);

		return $article;
	}

	// try to find the post with the friendly name $name
	public static function search($name)
	{
		throw new Exception('stop');
	}


	/**
	 * view the set of articles that satisfy $where
	 * $body get the body as well?
	 */
	public static function viewWhere($where, $body = false)
	{ 
		$r = Blog::get($where, $body);
		if ($r->numRows == 0) return null;

		$articles = array();	
		while ($a = $r->fetchAssoc())
			$articles[] = Blog::context($a);

		return $articles;
	}

	/**
	 * view the set of articles that have the specified tag
	 */
	public static function viewForTag($tag, $body = false)
	{
		if (($tag = intval($tag)) == NULL)
			throw new Exception('invalid id');

		$r = Blog::get('tag_link.tid = '.$tag, 
			false, array('tag_link' => 'tag_link.aid = a.id'));

		$articles = array();	
		while ($a = $r->fetchAssoc())
			$articles[] = Blog::context($a);

		return $articles;
	}

	/**
	 * view the latest blog posts and their body 
	 * $body	whether or not to retrieve the body of the entries
	 * $count	how many to get
	 */
	public static function latest($body = true, $limit = 5)
	{
		$r = Blog::get('1 order by post_date desc limit '.$limit, $body);

		$articles = array();
		while ($a = $r->fetchAssoc())
			$articles[] = Blog::context($a);

		return $articles;
	}

	/**
	 * context for a entry in the blog
	 * the body is done outside this
	 * $body	does the row have the body in it (should the body be in context)
	 * $raw		should the body be parsed?
	 */
	public static function context($row)
	{
		if (!$row['title']) $row['title'] = "Untitled";

		if ($row['body'] && !self::$raw) {
			$p = new WikiParser();
			$row['body'] = $p->parse($row['body']);
		} else if ($row['body']) {
			$row['body'] = htmlentities($row['body']);
		}

		return array(
			'id' => $row['id'],
			'title' => $row['title'],
			'search_title' => $row['search_title'],
			'link' => '<a href="'.r::$link->to('blog','view',$row['id']).'">'.
				$row['title'].'</a>',
			'author' => array(
				'name' => $row['member_name'] ? $row['member_name'] : "Guest", 
				'id' => $row['author_id'],
				'link' => $row['author_id'] ? 
					'<a href="'.r::$link->to('members','profile',$row['author_id']).'">'.
					$row['member_name'].'</a>' : "Guest",
			),
			'icon' => $row['icon'],
			'icon_url' => 'not sure yet!',
			'num_comments' => $row['num_comments'],
			// 'timestamp' => Util::timestamp($row['post_date']),
			'post_date' => $row['post_date'],
			'formatted_date' => date('F jS Y', $row['post_date']),
			'body' => $row['body'],
		);

	}
}



?>