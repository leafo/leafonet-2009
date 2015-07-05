<?php


class Comments 
{
	public $table;
	public $parent;

	// comments for table $table
	public function __construct($table)
	{
		$this->parent = $table;
		$this->table = 'comments_'.$table;
	}

	public function install()
	{
		$query = "create table if not exists `{$this->table}` (
			`id` int(10) unsigned not null auto_increment,
			`post_id` int(10) unsigned not null,
			`member_id` int(10) unsigned not null,

			`name` varchar(100) not null,
			`email` varchar(100) not null,
			`website` varchar(100) not null,
			`ip` varchar(100) not null,

			`post_date` int(10) not null default '0',
			`body` text not null,

			primary key (`id`)
		) default charset=utf8";
		db::query($query);
	}

	public function insert($pid, $comment)
	{
		$pid = intval($pid);
		$member_id = r::$me->loggedIn ? r::$me->id : 0;

		db::query('insert into '.$this->table.' set
			post_id = '.$pid.',
			member_id = '.$member_id.',

			name = '.db::sanitize(htmlentities($comment['name'])).',
			email = '.db::sanitize(htmlentities($comment['email'])).',
			website = '.db::sanitize(htmlentities($comment['website'])).',

			post_date = '.time().',
			body = '.db::sanitize($comment['body']).'
		');

		return db::insertId();
	}

	// get all the comments for post id
	public function view($pid)
	{
		$pid = intval($pid);

		$r = db::query('select comment.*, member.name member_name
			from '.$this->table.' comment
			left join members member on member.id = comment.member_id
			where post_id = '.$pid.' order by post_date asc');

		$comments = array();
		while ($c = $r->fetchAssoc()) {
			$comments[] = self::context($c);		
		}

		return $comments;
	}

	// delete comment by its id
	public function delete($id)
	{
		$id = intval($id);
		db::query('delete from '.self::$table.' where id = '.$id);

		return db::affected();
	}

	// create template context for a row
	static function context($row)
	{
		$link = 'Guest \''.$row['name'].'\'';
		if ($row['member_id']) {
			$link = '<a href="'.
				r::$link->to('members', 'profile', $row['member_id']).
				'">'.$row['member_name'].'</a>';
		} else if ($row['website']) { // check if it is website
			$link = 'Guest \'<a href="'.$row['website'].'">'.$row['name'].'</a>\'';
		}

		return array(
			'body' => nl2br(htmlentities($row['body'])),
			'date' => date('F j, Y, g:i a', $row['post_date']),
			'author' => array(
				'link' => $link,
				'name' => $row['name'],
				'email' => $row['email'],
				'website' => $row['website'],
			),
		);
	}
}

?>
