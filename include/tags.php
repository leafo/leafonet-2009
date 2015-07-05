<?php


class Tags
{
	static public $table = 'tags';
	static public $link_table = 'tag_link';

	public static function install()
	{
		db::query("create table if not exists `".Tags::$table."` (
			`id` int(10) unsigned not null auto_increment,
			`tag` varchar(80) not null,
			
			primary key (`id`)
		) default charset=utf8");

		db::query("create table if not exists `".Tags::$link_table."` (
			`id` int(10) unsigned not null auto_increment,
			`tid` int(10) unsigned not null,
			`aid` int(10) unsigned not null,

			primary key (`id`),
			index(`tid`),
			index(`aid`)
		) default charset=utf8");

	}

	/**
	 * inserts a tag if it is not stored already
	 * always returns the id of the tag regardless 
	 * of it was created or found 
	 */
	public static function insert($tag)
	{
		$real = strtolower(db::escape($tag));
		// check if tag is already there
		list($tid) = db::query("select id from ".
			Tags::$table." where tag like '{$real}'")->fetchRow();
		if ($tid) return $tid;

		db::query("insert into ".Tags::$table." set 
			tag = '{$real}'");

		return db::insertId();
	}

	public static function relate($aid, $tag)
	{
		$aid = intval($aid);
		$tid = Tags::insert($tag);
		db::query("insert into ".Tags::$link_table." set
			tid = {$tid}, aid = {$aid}");
	}

	/**
	 * takes an array of tags as strings
	 * This will clear all relationships then reinsert them
	 * all, the easiest way to set tags for something
	 */
	public static function clearAndSet($aid, $tags)
	{
		$aid = intval($aid);
		db::query("delete from ".Tags::$link_table." where 
			aid = {$aid}");
		foreach ($tags as $tag)
			Tags::relate($aid, $tag);
	}


	/**
	 * clear the tags for a specific aid
	 * then prune any orphans
	 */
	public static function clear($aid)
	{
		$aid = intval($aid);
		db::query("delete from ".Tags::$link_table." where 
			aid = {$aid}");

		Tags::purgeOrphans();
	}


	/**
	 * search for a tag based on name a return ids of all the articles
	 */
	public static function search($tag)
	{
		$tag = str_replace('_', ' ', $tag);
		list($id, $realTag) = 
			db::query("select id, tag from ".Tags::$table." where tag = ".
			db::sanitize($tag))->fetchRow();


		/*
		db::query("select aid as id from ".Blog::$tag_link."
			inner join ".Blog::$table
		 */

		return array($id, $realTag);
	}


	/**
	 * get the tags for an article, or a set of article
	 * by article id
	 */
	public static function getFor($what)
	{
		if (!is_array($what)) {
			$single = true;
			$what = array($what);
		}
		array_map('intval', $what);

		$r = db::query("select r.aid, t.id, t.tag from ".Tags::$link_table." as r
			inner join ".Tags::$table." as t on t.id = r.tid 
			where r.aid in (".implode(',', $what).")");

		$tagset = array();
		while ($tag = $r->fetchAssoc())
			// $tagset[$tag['aid']][] = array('id' => $tag['id'], 'tag' => $tag['tag'], );
			$tagset[$tag['aid']][] = Tags::context($tag);

		
		if ($single)
			return is_array($tagset[$what[0]]) ? $tagset[$what[0]] : array();
		else
			return $tagset;
	}

	/**
	 * this will delete all orphaned tags (tags that have no relations)
	 */
	public static function purgeOrphans()
	{
		$r = db::query("select t.id
			from ".Tags::$table." as t
			left join ".Tags::$link_table." as r on r.tid = t.id
			where r.id is null");
		
		$orphans = array();
		while ($row = $r->fetchAssoc())
			$orphans[] = $row['id'];
	
		if (count($orphans) == 0) return; // nothing to clean up

		db::query("delete from ".Tags::$table." where
			id in (".implode(',', $orphans).")");
	}


	/**
	 * returns an array of the top $max tags 
	 * where the key is the name of the tag and the 
	 * value is how many times it appeared
	 *
	 * $sort will sort the tags alphabetically, useful for 
	 * tag cloud
	 */
	public static function tagCount($max = 10, $sort = false)
	{
		$r = db::query("select count(*) as `count`, t.* 
			from ".Tags::$link_table." as r, ".Tags::$table." as t
			where r.tid = t.id
			group by r.tid order by `count` desc limit ".intval($max));
			
		$tags = array();
		while ($tag = $r->fetchAssoc())
			if ($sort)
				Tags::insertSorted($tag, $tags);	
			else 
				$tags[] = $tag;

		return $tags;
	}

	private static function insertSorted($what, &$array)
	{
		$c = count($array);
		if ($c == 0) return $array[] = $what;
		for ($i = 0; $i < $c; $i++)  {
			if (strcasecmp($array[$i]['tag'], $what['tag']) > 0) {
				return array_splice($array, $i, 0, array($what));
			}
		}
		return $array[] = $what;
	}

	private function context($tag)
	{
		return '<a href="'.r::$link->to('blog', 'list', 'tag:'.$tag['tag']).'">'.
			$tag['tag'].'</a>';

		return array(
			'id' => $tag['id'],
			'tag' => $tag['tag'],
			'search_link' => r::$link->to('blog', 'list', 'tag:'.$tag['id']),
		);
	}

}


?>