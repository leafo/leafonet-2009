<?php
/**
 * User persistant class
 *
 * @author Leaf Corocran
 * @version 1.0
 * @pakcge user
 * @subpackage classes
 */

class Guest {
	public $loggedIn = false;
	public $color;
	protected $info = array(
		'id' => 0,
		'name' => 'Guest',
	);

	public $can = array();

	public function update() { trace('you are updating as guest.. bad'); return false; }
	public function can($what) { return false; } // can't do anything

	public function __get($name) {
		return array_key_exists($name, $this->info) ? 
			$this->info[$name] : null;
	}
}

class User extends Guest 
{
	private $altered; // User has changed since last save/load

	// session and table data
	static private $salt = 'hello -my fog';
	static public $cookieName = 'leafox';
	static public $table = 'members';
	static public $table_groups = 'groups';

	/**
	 * hash a string for a password
	 */
	static public function hash($string) 
	{
		return md5(User::$salt.$string);
	}

	/**
	 * Fetch id of user from name
	 */
	static public function idFromName($name)
	{
		list($id) = db::query("select id from `members` where 
			name = '".db::escape($name)."'")->fetchRow();

		return $id;
	}


	// get the list of online users
	public static function getOnline()
	{
		$td  = time () - 60*10;

		$r = db::query('select member.name, member.id, color, group_id, g.name group_name, location
			from '.self::$table.' member
			inner join '.self::$table_groups.' g on member.group_id = g.id
			where last_active > '.$td.'
			order by last_active desc');

		$users = array();
		while ($user = $r->fetchAssoc())
			$users[] =  '<a href="'.r::$link->to('members','profile',$user['id']).'" '.($user['color'] ? 'style="color: '.$user['color'].'" ' : '').'>'.$user['name'].'</a>';

		return implode(', ', $users);
	}

	/**
	 * create a blank user
	 * with default values
	 */
	public static function create()
	{
		$u = new User();

		// load the default values
		$q = db::query('describe `members`');

		while ($f = $q->fetchAssoc()) {		
			$u->info[$f['Field']] = $f['Default'];
		}

		$u->info['id'] = null;
		$u->info['join_date'] = time();
		$u->info['permissions'] = serialize(array());
		$u->info['location'] = '';
		$u->info['last_active'] = 0;

		return $u;
	}

	/**
	 * login a user from id and hash
	 * this happens on every page load for a logged in user
	 */
	public static function login($id, $hash)
	{
		$id = intval($id);
		$hash = mysql_real_escape_string($hash);

		$data = db::query("select m.*, g.color, g.permissions base from `members` m
			inner join `groups` g on m.group_id = g.id
			where m.id = {$id} and m.password = '{$hash}'")->fetchAssoc();

		if (!$data)
			throw new Exception("User ({$id}, {$hash}) could not be loaded");

		$u = new User();
		$u->setData($data);

		// see if this is the first time logging on today
		if (floor($data['last_active'] / 86400) != BoardStats::today()) {
			BoardStats::up('online', 1);
		}

		// update the member
		db::query('update `members` set 
			last_active = '.time().',
			location = '.db::sanitize(implode(':', array(r::$r->act, r::$r->func, r::$r->id, r::$r->page))).'
			where id = '.$id);

		return $u;
	}

	// set the objects default set of data, where data is a result from query
	private function setData($data)
	{
		$this->can = (object)(unserialize($data['base']) + ($data['permissions'] ? unserialize($data['permissions']) : array()));
		$this->loggedIn = true;
		$this->color = $data['color'];

		// get rid of the uneditable things
		unset($data['base']);
		unset($data['permissions']);
		unset($data['color']);

		$this->info = $data;
	}



	/**
	 * load a user object from user id
	 */
	public static function load($id)
	{
		$id = intval($id);

		$data = db::query("select m.*, g.color, g.permissions base from `members` m
			inner join `groups` g on m.group_id = g.id
			where m.id = {$id}")->fetchAssoc();

		if (!$data) throw new Exception("User could not be loaded");

		$u = new User();
		$u->setData($data);

		return $u;
	}

	private function __construct()
	{
		$this->loggedIn = false;
	}

	// is this user able to do something/
	public function can($what)
	{ 
		return $this->can->$what;
	}

	// create a context array for this user
	public function context()
	{
		return array(
			'id' => $this->id,
			'name' => $this->name,
			'link' => '<a '.($this->color ? 'style="background: black; color: '.$this->color.'" ' : '').'href="'.r::$link->to('members', 'profile', $this->id).'">'.$this->name.'</a>',
			'join_date' =>  date('F jS Y', $this->join_date),
			'member_for' => time() - $this->join_date,
			'last_active' =>  date('F jS Y', $this->last_active),
			'since_active' =>  time() - $this->last_active,
			'is_online' => time() - $this->last_active < 600,
			'topics' => $this->topics,
			'posts' => $this->posts,
			'group_id' => $this->group_id,
		);
	}

	// save the user's session
	public function createCookie()
	{
		setcookie(User::$cookieName, serialize(array(
			$this->id,
			$this->password)));
	}

	// clear the user's session
	public function deleteCookie()
	{
		setcookie(User::$cookieName, "");
		$_COOKIES[User::$cookieName] = "";
	}

	// update the user object, or insert if it has not been made yet
	public function update() 
	{
		if ($this->id == null) // insert new user
		{
			$query = 'insert into `members` set ';
			foreach ($this->info as $key=>$value) {
				if ($key == 'id') continue;
				$query.= $key.' = '.db::sanitize($value).', ';
			}
			$query = substr($query, 0, -2); // trim trailing commma

			db::query($query);

			$this->id = db::insertId();


			// register the registration
			BoardStats::up('registrations', 1);

		} 
		else // update the user
		{
			$query = 'update `members` set ';
			foreach ($this->altered as $key=>$value) {
				$query.= $key.' = '.db::sanitize($value).', ';
			}
			$query = substr($query, 0, -2);
			$query .= " where id = ".$this->id;

			db::query($query);
		}

		// clear altered rows
		$this->altered = array();
	}

	// get persistent user property
	public function __get($name) {
		return array_key_exists($name, $this->info) ? 
			$this->info[$name] : null;
	}


	// set a persistent user property
	public function __set($name, $value) {
		// only change keys that exist to prevent error on update
		if (array_key_exists($name, $this->info))
			$this->info[$name] = $this->altered[$name] = $value;
	}

	// install the user and group table, set default properties
	public function install() {
		// Create user table
		db::query("create table if not exists `members` (
			`id` int(10) unsigned not null auto_increment,
			`name` varchar(80) not null,
			`password` varchar(32) not null,
			`email` varchar(80) not null default '',
			`group_id` int(10) unsigned not null default '2',
			`join_date` int(10) unsigned not null default '0',
			`last_active` int(10) unsigned not null default '0',
			`topics` int(10) unsigned not null default '0',
			`posts` int(10) unsigned not null default '0',
			`valid` tinyint(1) not null default '1',

			`permissions` tinytext not null,
			`location` varchar(45) not null,
			primary key (`id`)
		) default charset=utf8");

		// Create user group table
		db::query("create table if not exists `groups` (
			`id` int(10) unsigned not null auto_increment,
			`name` varchar(80) not null,
			`color` varchar(20) not null,
			`permissions` tinytext not null,
			primary key (`id`)
		) default charset=utf8");


		// Create default groups
		// Admins
		db::query("insert ignore into `groups` set
			`id` = 1,
			color = '#FF4646',
			`name` = 'Adminstrator'");

		// Members
		db::query("insert ignore into `groups` set
			`id` = 2,
			`name` = 'Member'");

		// Insert me
		db::query("insert ignore into `members` set
			`id` = 1,
			`name` = 'leafo',
			`password` = '".User::hash('duck')."',
			`group_id` = 1,
			`join_date` = ".time());

		self::setP();
	}

	// reset the group permissions
	public static function setP()
	{
		$user = array(
			'view_members' => true,
			'forum_post' => true,
			'forum_edit_own' => true,
			'forum_delete_own' => false,
		);

		$admin = $user + array(
			'forum_edit' => true,
			'forum_delete' => true,
			'blog_post' => true,
		);

		db::query('update groups set permissions = '.db::sanitize(serialize($admin)).' where id = 1');
		db::query('update groups set permissions = '.db::sanitize(serialize($user)).' where id = 2');
	}

	/**
	 * remove all member/group tables
	 */
	public function uninstall() {
		db::query("drop table `groups`");
		db::query("drop table `members`");
	}

}

function mb_unserialize($serial_str) {
    $out = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
    return unserialize($out);   
}


// Attempt to read cookie
if (array_key_exists(User::$cookieName, $_COOKIE))
{
	list($id, $pass) = @unserialize(stripslashes($_COOKIE[User::$cookieName]));
	try {$user = User::login($id, $pass);} catch (Exception $e) {
		// Invalid cookie
		$user = new Guest();
	}
} 
else $user = new Guest();

?>
