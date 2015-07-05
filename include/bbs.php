<?php


// TODO: Don't forget to add HEAT (cooldown for posting)

require './include/folders.php';
require './include/bbcode.php';
require_once "parser.php";




Board::$tree = new Folders(Board::$table_boards);
class Board
{
	public static $table_boards = 'board_folders';
	public static $table_topics = 'board_topics';
	public static $table_posts = 'board_posts';
	public static $table_topic_log = 'board_topic_log';
	public static $table_board_log = 'board_board_log';

	public static $per_page = 25;

	public static $tree; 
		/* = new Folders(Board::$table_boards);*/ // can't do that

	// create a new board
	public static function createBoard($title, $desc, $pid = null)
	{
		$id = self::$tree->insert($title, $pid);

		// set the description
		db::query('update `'.self::$table_boards.
			'` set description = '.db::sanitize($desc));

		return db::insertId();
	}

	// get a board's basic information and existance
	// basic : set to false to get all fields
	// null if it can't be found
	public static function getBoard($id, $basic = true)
	{
		$id = intval($id);

		$b = db::query('select '.($basic ? 'name, rgt, lft ' : '* ').
			'from '.self::$table_boards.' where id = '.$id)->fetchAssoc();

		return $b; // null if board doesn't exist
	}

	// get at topic's basic information
	// null if it can't be found
	public static function getTopic($id, $user = null)
	{
		$id = intval($id);
		$topic = db::query('select topic.*, board.lft, board.rgt 
			'.($user ? ', log.time access_time' : '').'
			from '.self::$table_topics.' as topic
			inner join '.self::$table_boards.' as board 
				on board.id = topic.board_id
			'.
				($user ? 'left join '.self::$table_topic_log.' as log on 
				log.topic_id = topic.id and log.member_id = '.$user->id : '')	
			.'
			where topic.id = '.$id)->fetchAssoc();

		return $topic;
	}

	public function getReply($id)
	{
		$id = intval($id);
		$post = db::query('select topic.title topic_title, post.* 
			from '.self::$table_posts.' post
			inner join '.self::$table_topics.' topic on post.topic_id = topic.id
			where post.id = '.$id)->fetchAssoc();


		// unparse the body
		$parse = new BBCodeParser();
		if ($parse->load($post['body']))
			$post['body'] = $parse->render(true);

		return $post;
	}

	// returns a tuple of the board and its topics
	// and a integer specifying the total number of pages
	public static function viewBoard($id, $page = 1)
	{
		$id = intval($id);
		$page = intval($page);

		$board = self::getBoard($id, false);

		// count the number of topics for page totals
		list($total_topics) = db::query('select count(*) from 
			'.self::$table_topics.' topic where topic.board_id = '.$id)->fetchRow();

		$pages = ceil($total_topics / self::$per_page);
		if ($page < 1) $page = r::$r->page = $pages;

		$realBoard = array(
			'id' => $board['id'],
			'name' => $board['name'],
			'description' => $board['description'],
			'link' => 
				'<a href="'.r::$link->to('board',null,$board['id']).'">'.$board['name'].'</a>',
			'num_topics' => $board['topics'],
			'num_replies' => $board['replies'],
			'pages' => $pages,
			'page' => $page,
		);

		// get the topics
		$tr = db::query('select topic.*, member.name author_name, 
				lpmember.name last_post_name, log.time access_time
			from '.self::$table_topics.' topic
			inner join '.User::$table.' member on topic.author_id = member.id
			left join '.User::$table.' lpmember on topic.last_post_author = lpmember.id
			left join '.self::$table_topic_log.' log 
				on log.topic_id = topic.id and log.member_id = '.r::$me->id.' 
			where topic.board_id = '.$id.'
			order by last_post_date desc
			limit '.(self::$per_page * ($page -1)).', '.self::$per_page);

		while ($topic = $tr->fetchAssoc())
		{
			$realBoard['topics'][] = array(
				'title' => $topic['title'],
				'subtitle' => $topic['subtitle'],
				'link' => 
					'<a href="'.r::$link->to('board','topic',$topic['id']).'">'.$topic['title'].'</a>',
				'views' => $topic['views'],
				'replies' => $topic['replies'],
				'has_new' => r::$me->loggedIn ? $topic['last_post_date'] > $topic['access_time']: false,
				'author' => array(
					'id' => $topic['author_id'],
					'name' => $topic['author_name'],
					'link' => 
						'<a href="'.r::$link->to('members','profile',$topic['author_id']).'">'.$topic['author_name'].'</a>',
					),

				'last_post_author' => array(
					'id' => $topic['last_post_author'],
					'name' => $topic['last_post_name'],
					'link' => 
						'<a href="'.r::$link->to('members','profile',$topic['last_post_author']).'">'.$topic['last_post_name'].'</a>',

					),

				'rdate' => date('F jS Y', $topic['post_date']),
				'date' => self::dateFormat($topic['post_date']),
				'last_post' => self::dateFormat($topic['last_post_date']),
			);
		}


			
		return array($realBoard, $pages);
	}

	// return a pretty string for unix time
	private static function dateFormat($time)
	{
		$diff = time() - $time;
		if ($diff < 60)
			return $diff.' seconds ago';
		else if ($diff < 60*60)
			return round($diff/60).' minutes ago';
		else if ($diff < 60*60*24)
			return round($diff/(60*60)).' hours ago';
		else 
			return date('F jS Y', $time);
	}


	// get a parsed topic
	// $user who is viewing the topic (update views and last time)
	// $page what page to reqruest
	// returns a tuple of the topic and the total number of pages
	public static function viewTopic($id, $user = null, $page = 1, $raw = false)
	{
		$id = intval($id);
		$page = intval($page);

		/**
		 * 1. Get Topic
		 *   - update view count
		 *   - update access log
		 * 2. Get Posts
		 * 3. Get Path
		 */

		$topic = self::getTopic($id, $user);
		if (!$topic) return null;

		// update views if the user didn't just come from here
		// TODO:: make it work with guests
		if ($user) list($uact, $ufunc, $uid) = explode(':', $user->location);

		if ($uact != 'board' || $ufunc != 'topic' || $uid != $id) {
			db::query('update '.self::$table_topics.
				' set views = views + 1 where id = '.$id);
			$topic['views']++;
		}

		// update the access log
		if ($user && $user->loggedIn && 
			$topic['last_post_date'] > $topic['access_time'])
		{

			if ($topic['access_time']) // update it
				db::query('update '.self::$table_topic_log.
					' set time = '.time().' where member_id = '.$user->id.' and
					topic_id = 	'.$topic['id']);
			else // create a new one
				db::query('insert into '.self::$table_topic_log.
					' set time = '.time().', member_id = '.$user->id.',
					topic_id = 	'.$topic['id']);
		}

		// create the topic context
		$realTopic = array(
			'id' => $topic['id'],
			'title' => $topic['title'],
			'subtitle' => $topic['subtitle'],
			'link' => 
				'<a href="'.r::$link->to('board','topic',$topic['id']).'">'.$topic['title'].'</a>',
			'views' => $topic['views'],
			'replies' => $topic['replies'],
			'author' => array(
				'id' => $topic['author_id'],
				),

			'date' => date('F jS Y', $topic['post_date']),
			'last_post' => date('F jS Y', $topic['last_post_date']),
		);

		// '<a href="'.r::$link->to('members','profile',$row['author_id']).'">'.

		list($total_posts) = db::query('select count(*) from '.self::$table_posts.
			' where topic_id = '.$id)->fetchRow();

		$pages = ceil($total_posts / self::$per_page);
		if ($page < 1) $page = r::$r->page = $pages;

		// get the posts
		$pr = db::query('select post.*, member.name as author_name, color,
			g.name group_name, group_id, member.posts, member.join_date
			from '.self::$table_posts.' as post
			inner join '.User::$table.' member
				on post.author_id = member.id
			inner join '.User::$table_groups.' g 
				on g.id = member.group_id
			where topic_id = '.$id.'
			order by post_date asc
			limit '.(self::$per_page * ($page - 1)).', '.self::$per_page);

		$parse = new BBCodeParser();

		$posts = array();
		while ($p = $pr->fetchAssoc())
		{
			$posts[] = array(
				'id' => $p['id'],
				'author' => array(
					'id' => $p['author_id'],
					'name' => $p['author_name'],
					'link' => 
					'<a '.($p['color'] ? ' style="color: '.$p['color'].';" ' : '').'href="'.r::$link->to('members','profile',$p['author_id']).'">'.$p['author_name'].'</a>',
					'group_id' => $p['group_id'],
					'group_name' => $p['group_name'],
					'join_date' => date('F j, Y', $p['join_date']),
					'posts' => $p['posts'],

				),
				'date' => self::dateFormat($p['post_date']),
				'body' => $raw || !$parse->load($p['body']) ? '<b>not parsed</b>: '.$p['body'] : $parse->render(),
				'edited' => $p['edit_by'] != 0,
				'edit_date' => date('F jS Y', $topic['edit_date']),
				'edit_by' => $p['edit_by'],
				'can_edit' => r::$me->can('forum_edit') || ($p['author_id'] == r::$me->id && r::$me->can('forum_edit_own')),
				'can_delete' => r::$me->can('forum_delete') || ($p['author_id'] == r::$me->id && r::$me->can('forum_delete_own')),

				'is_topic' => $p['topic_post'],
				
			);
		}

		// get the path
		$path = self::$tree->pathFrom($topic['lft'], $topic['rgt']);
		foreach ($path as $crumb)
			$realTopic['path'][] = '<a href="'.r::$link->to('board',null,$crumb['id']).'">'.
				$crumb['name'].'</a>';

		$realTopic['posts'] = $posts;

		return array($realTopic, $pages);
	}


	// post a new topic
	// topic requires
	// $board is where the topic is posted, either array or id
	// title, body
	// returns the id
	public static function postTopic($board, $user, $topic = array())
	{
		$time = time();

		if (!is_array($board))
			$board = self::getBoard($board);

		if (!$board) throw new Exception('need board to post topic');

		/**
		 * 1. Insert topic
		 * 2. Insert the post
		 * 3. Update the board
		 * 4. Update user
		 */

		db::query('INSERT INTO `'.self::$table_topics.'` SET 
			board_id = '.$board['lft'].',
			title = '.db::sanitize($topic['title']).', 
			author_id = '.$user->id.',
			post_date = '.$time.',
			last_post_date = '.$time.',
			last_post_author = '.$user->id);

		$topic_id = db::insertId();

		$parse = new BBCodeParser();
		$parse->parse($topic['body']);

		// insert the post
		db::query('INSERT INTO '.self::$table_posts.' SET
			title = '.db::sanitize($topic['title']).',
			author_id = '.$user->id.',
			post_date = '.$time.',
			body = '.db::sanitize($parse->save()).',
			post_ip = '.db::sanitize('0').',
			topic_post = 1,
			topic_id = '.$topic_id);

		db::query('UPDATE '.self::$table_boards.' SET
			topics = topics + 1,
			latest_topic = '.$topic_id.'
			WHERE lft <= '.$board['lft'].' AND rgt >= '.$board['rgt']);

		$user->topics++;
		$user->update();

		BoardStats::up('topics', 1);

		return $topic_id;
	}

	// delete at topic and all of its posts. 
	// Fix the latest post for the board
	// TODO: delete all the logs for deleted topics/posts
	public static function deleteTopic($tid)
	{
		$tid = intval($tid);

		/**
		 * delete topic
		 * delete posts
		 * update board count
		 * update board latest (fixBoard)
		 */

		list($bid, $lft, $rgt) =
		   	db::query('select board_id, lft, rgt from '.self::$table_topics.' topic
			inner join '.self::$table_boards.' board on board_id = board.id
			where topic.id = '.$tid)->fetchRow();

		if (!$bid) return false; // topic doesn't exist

		db::query('delete from '.self::$table_topics.' where id = '.$tid);
		db::query('delete from '.self::$table_posts.' where topic_id = '.$tid);

		$numposts = db::affected() - 1;

		// decrease counters
		db::query('update '.self::$table_boards.' set topics = topics - 1,
			replies = replies - '.$numposts.'
		   where lft <= '.$lft.' and rgt >= '.$rgt);

		return self::fixBoard($bid);
	}

	// edit a post
	public function editPost($pid, $edit)
	{
		$pid = intval($pid);		
		
		// need to update the title
		if ($edit['title'] && $edit['topic_id'])
			db::query('update '.self::$table_topics.' set title = 
				'.db::sanitize($edit['title']).' 
			where id = '.intval($edit['topic_id']));

		$parse = new BBCodeParser();
		$parse->parse($edit['body']);

		return db::query('update '.self::$table_posts.' set
				body = '.db::sanitize($parse->save()).',
				'.($edit['title'] ? 'title = '.db::sanitize($edit['title']).',' : '').'
				edit_date = '.time().',
				edit_by = '.r::$me->id.'
			where id ='.$pid);
	}

	public function deletePost($pid)
	{
		$pid = intval($pid);

		/**
		 * delete the post
		 * update the topic 
		 * update the board (latest and counters)
		 * update the user
		 */

		// get the board information
		list($bid, $lft, $rgt, $tid, $date, $last) = 
			db::query('select board.id, board.lft, board.rgt, 
				topic.id, post.post_date, topic.last_post_date from 
			'.self::$table_posts.' post
			inner join '.self::$table_topics.' topic on topic.id = post.topic_id
			inner join '.self::$table_boards.' board on board.id = topic.board_id
			where post.id = '.$pid)->fetchRow();

		if (!$bid) return;

		db::query('delete from '.self::$table_posts.' where id = '.$pid);

		// deleted latest post need to update topic
		if ($date == $last) {
			$fix = true;
			list($ldate, $lauthor) = db::query(
				'select post_date, author_id from '.self::$table_posts.'
				where topic_id = '.$tid.' order by post_date desc limit 1'
				)->fetchRow();
			db::query('update '.self::$table_topics.' set 
				last_post_date = '.$ldate.',
				last_post_author = '.$lauthor.',
				replies = replies - 1
				where id = '.$tid);
		} else db::query('update '.self::$table_topics.' set 
			replies = replies - 1 where id = '.$tid);

		// decrease board counter
		db::query('update '.self::$table_boards.' set 
			replies = replies - 1 
			where lft <= '.$lft.' and rgt >= '.$rgt);

		return $fix ? self::fixBoard($bid) : true;
	}


	// set the latest post for board and and boards to top
	// is this potentiall slow? has to look through every post...
	public static function fixBoard($bid)
	{
		$bid = intval($bid);
		try {
			$path = self::$tree->pathFrom($bid);
		} catch (Exception $ex) { 
			return false; // do nothing
		}

		/**
		 * this isn't atomic
		 * so it is possible for the time between queries to
		 * cause the latest to be wrong?
		 *
		 * lock the table (for writing)
		 * but are http requrests even concurrent?
		 */

		// update nodes in reverse order so we know when to quit
		$updates = array();
		for ($i = count($path)-1; $i >= 0; $i--)
		{
			$node =& $path[$i];

			// this is the slow part (I think?)
			list($topic) = db::query('select id from '.self::$table_topics.' 
				where board_id in (select id from board_folders 
					where lft >= '.$node['lft'].' and rgt <= '.$node['rgt'].')
				order by last_post_date desc limit 1')->fetchRow();

			if (!$topic) $topic = 0; // if no topics found board is empty

			// see if we can get out early
			if ($topic == $node['latest_topic']) break;

			$updates[$node['id']] = $topic;
		}

		// this part is faster so do it all at once
		foreach ($updates as $board_id => $topic_id) {
			db::query('update '.self::$table_boards.' 
				set latest_topic = '.$topic_id.' 
				where id = '.$board_id);
		}

		return true;
	}

	// set the latest for a topic 
	// recount the posts too?
	private static function fixTopic($tid)
	{
		$topic = self::getTopic($id);
	}

	// post a reply
	// reply array requires a body..
	public static function postReply($topic, $user, $reply = array())
	{
		$time = time();

		// get the topic
		if (!is_array($topic))
			$topic = self::getTopic($topic);

		/**
		 * 1. Insert Post
		 * 2. Update Topic
		 * 3. Update Forum
		 * 4. Update User
		 */		

		$parse = new BBCodeParser();
		$parse->parse($reply['body']);

		db::query('insert into '.self::$table_posts.' set
			author_id = '.$user->id.',
			post_date = '.$time.',
			body = '.db::sanitize($parse->save()).',
			post_ip = '.db::sanitize($_SERVER['REMOTE_ADDR']).',
			topic_post = 0,
			topic_id = '.$topic['id']);

		$post_id = db::insertId();

		// update the topic
		db::query('update '.self::$table_topics.' set
				replies = replies + 1,
				last_post_date = '.$time.',
				last_post_author = '.$user->id.'
			where id = '.$topic['id']);

		// update boards
		db::query('update '.self::$table_boards.' set
				replies = replies + 1,
				latest_topic = '.$topic['id'].'
			where lft <= '.$topic['lft'].' and rgt >= '.$topic['rgt']);


		$user->posts++;
		$user->update();

		BoardStats::up('replies', 1);

		return $post_id;
	}

	public static function install()
	{
		// create the boards
		db::query("
			create table if not exists `".self::$table_boards."` (
				`id` int(10) unsigned not null auto_increment,
				`lft` int(10) unsigned NOT NULL,
				`rgt` int(10) unsigned NOT NULL,
				`name` varchar(45) NOT NULL,
				`description` text NOT NULL,
				`topics` int(10) unsigned NOT NULL default '0',
				`replies` int(10) unsigned NOT NULL default '0',
				`latest_topic` int(10) unsigned NOT NULL,
				`closed` tinyint(1) unsigned NOT NULL,
				PRIMARY KEY  (`id`)
			) DEFAULT CHARSET=utf8;
		");	

		// create the posts table
		db::query("
			create table if not exists `".self::$table_posts."` (
				`id` int(10) unsigned NOT NULL auto_increment,
				`title` varchar(45) NOT NULL,
				`author_id` int(10) unsigned NOT NULL,
				`post_date` int(10) unsigned NOT NULL,
				`body` text NOT NULL,
				`post_ip` varchar(45) NOT NULL,
				`edit_date` int(10) unsigned NOT NULL,
				`edit_by` int(10) unsigned NOT NULL,
				`topic_id` int(10) unsigned NOT NULL,
				`topic_post` tinyint(1) unsigned NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) DEFAULT CHARSET=utf8;
		");

		// create the topics table
		db::query("
			create table if not exists `".self::$table_topics."` (
				`id` int(10) unsigned NOT NULL auto_increment,
				`board_id` int(10) unsigned NOT NULL,
				`title` varchar(200) NOT NULL,
				`subtitle` varchar(200) NOT NULL,
				`author_id` int(10) unsigned NOT NULL,
				`views` int(10) unsigned NOT NULL default '0',
				`replies` int(10) unsigned NOT NULL default '0',
				`post_date` int(10) unsigned NOT NULL,
				`last_post_date` int(10) unsigned NOT NULL,
				`last_post_author` int(10) unsigned NOT NULL,
				PRIMARY KEY  (`id`)
			) DEFAULT CHARSET=utf8;
		");


		// create the topic log (when we visited a topic)
		db::query("
			create table if not exists `".self::$table_topic_log."` (
				`member_id` int(10) unsigned not null,
				`topic_id` int (10) unsigned not null, 
				`time` int(10) unsigned not null, 
				primary key (`member_id`, `topic_id`)
			) default charset=utf8;
		");
				
		// this isn't used yet..
		db::query("
			create table if not exists `".self::$table_board_log."` (
				`member_id` int(10) unsigned not null,
				`board_id` int (10) unsigned not null, 
				`time` int(10) unsigned not null, 
				primary key (`member_id`, `board_id`)
			) default charset=utf8;
		");
	

	}





	// set the latest post for board and and boards to top
	// this one should be faster I think? just a lot more queries...
	public static function fixBoardOld($bid)
	{
		$bid = intval($bid);

		$board = db::query('select board.id, lft, rgt, latest_topic, 
				post_date latest_date
			from '.self::$table_boards.' board
			left join '.self::$table_topics.' topic on latest_topic = topic.id
			where board.id = '.$bid)->fetchAssoc();

		if (!$board) return; // not found

		/**
		 * fixBoard loads the specified board, it then selects the most recent
		 * topic from both its children topics and its children boards
		 *
		 * it updates it own data with the most recent topic.
		 *
		 * It then selects its parent node and tell it to fix itself
		 *
		 *
		 * this recurses to the root node and fixes the latest post for
		 * all boards from the one that was affected
		 *
		 * we can terminate early if we don't have to update the current board
		 * we can also terminate if the latest id of the current board doesn't
		 *  equal the topic we just deleted
		 *
		 *  also is there a faster way to do this with just the tree?
		 *  and no queries, then save the tree when done
		 *
		 */

		// get the most recent topic from child boards
		// it shouldn't matter that we go deeper...
		$lBoard = db::query('select topic.id, topic.last_post_date 
			from '.self::$table_boards.' board
			inner join '.self::$table_topics.' topic on latest_topic = topic.id
			where lft > '.$board['lft'].' and rgt < '.$board['rgt'].'
			order by last_post_date desc
			limit 1')->fetchAssoc();


		// get the most recent topic from the child topics
		$lTopic = db::query('select id, last_post_date
			from '.self::$table_topics.'
			where board_id = '.$board['id'].'
			order by last_post_date desc
			limit 1')->fetchAssoc();

		$newTopic = null; // the new latest topic

		// this means there is nothing in the board
		// TODO: make it set nothing at latest topic and propigate up
		if (!$lBoard && !$lTopic) return; // done

		if (!$lBoard) $newTopic = $lTopic['id'];
		else if (!$lTopic) $newTopic = $lBoard['id'];

		else if ($lBoard['last_post_date'] > $lTopic['last_post_date'])
			$newTopic = $lBoard['id'];
		else 
			$newTopic = $lTopic['id'];

		// update this board with the new topic
		// if nothing needs to be changed then we are done
		if ($newTopic == $board['latest_topic']) return;

		db::query('update '.self::$table_boards.'
			set latest_topic = '.$newTopic.'
			where id = '.$board['id']);

		// tell my parent to do it 
		list($parent) = db::query('select id from '.self::$table_boards.' where 
			lft < '.$board['lft'].' order by lft desc limit 1')->fetchRow();

		if ($parent) 
			self::fixBoardOld($parent);
	}




}

?>