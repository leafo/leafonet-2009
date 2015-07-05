<?php
/**
 * A collection of global utility classes that 
 * are interacted with through the page
 */


/**
 * collection of static utility functions
 */
class Util 
{
	public static function version()
	{
		return 'leafo.net';
	}


	// this function is huge 
	public static function renderPage($current, $total, $path)
	{
		if ($total < 1) return '';
		if (!intval($current)) $current = 1;
		if ($current > $total || $current < 1) $current = $total;

		$buffer = 3;
		$status = 'Page '.$current.' of '.$total;

		$items = array();

		list($_act, $_func, $_id) = $path;

		// first link
		if ($current > $buffer + 1)
			$items[] = array( 'value' => 'first', 'class' => 'button', 'link' => r::$link->to($_act, $_func, $_id, 1),);

		// previous link
		if ($current > 1)
			$items[] = array( 'value' => 'previous', 'class' => 'button', 'link' => r::$link->to($_act, $_func, $_id, $current - 1),);

		// do the [1 2 3] before
		$pre = $current - $buffer;
		if ($pre > 1)
			$items[] = array( 'value' => '...', 'class' => 'text',);
		else 
			$pre = 1;

		while ($pre != $current)  {
			$items[] = array( 'value' => $pre, 'class' => 'number', 'link' => r::$link->to($_act, $_func, $_id, $pre),);
			$pre++;
		}

		// current page
		$items[] = array( 'value' => $pre, 'class' => 'current');

		// do the [ 6 7 8] after
		$post = $current + $buffer;
		$rc = $current;
		if ($post > $total)
			$post = $total;

		while ($rc != $post) {
			$rc++;
			$items[] = array( 'value' => $rc, 'class' => 'number', 'link' => r::$link->to($_act, $_func, $_id, $rc),);
		}

		if ($rc != $total)
			$items[] = array( 'value' => '...', 'class' => 'text',);

		if ($current < $total)
			$items[] = array( 'value' => 'next', 'class' => 'button', 'link' => r::$link->to($_act, $_func, $_id, $current + 1),);

		if ($rc < $total) 
			$items[] = array( 'value' => 'last', 'class' => 'button', 'link' => r::$link->to($_act, $_func, $_id, $total),);

		// return $items;	
		ob_start();
		r::$tpl->v__pages = $items;
		r::$tpl->render('pageselector.tpl');
		return ob_get_clean();
	}
}

/**
 * statistics about forum and user registrations
 */
class BoardStats
{
	public static $table = 'board_stats';
	public static $editable = array(
		'topics', 'replies', 'registrations', 'online'
	);
	
	// get the current day index
	public static function today()
	{
		return floor(time() / 86400);
	}	

	// get todays stats
	public static function up($what, $amount) 
	{
		if (!in_array($what, self::$editable))
			return;

		$amount = intval($amount);

		db::query('update '.self::$table.' 
			set '.$what.' = '.$what.' + '.$amount.'
		where day = '.self::today());

		// inset new day
		if (!db::affected()) {
			db::query('insert into '.self::$table.' 
				set day = '.self::today().', '.$what.' = '.$amount);
		}
	}


	public static function install()
	{
		// create the topic log (when we visited a topic)
		db::query("
			create table if not exists `".self::$table."` (
				`day` int(10) unsigned not null,
				`topics` int(10) unsigned not null default 0, 
				`replies` int(10) unsigned not null default 0, 
				`registrations` int(10) unsigned not null default 0, 
				`online` int(10) unsigned not null default 0, 

				primary key (`day`)
			) default charset=utf8;
		");
	}
}




/**
 * registry,
 * the registry is stored in static variable $r, it is an anonymous class
 * there are also some special static variables for accessing other
 * important instanced classes, like the template renderer, and the link
 * generator
 *
 * @author leafo.net
 * @version 0.1
 * @package registry
 * @subpackage classes
 */
class r 
{	
	static public $r;
	// references to utility classes

	static public $tpl; // template manager
	static public $db; // sql database
	static public $link; // link generator
	static public $me; // current user

	/**
	 * Initialize the registry
	 */	
	static function load($initial)
	{
		$r = (object) $initial;

		$r->page_dir = $r->base_path.'pages/';
		$r->act = isset($_GET['act']) ? 
			$_GET['act'] : $r->default_page;

		$r->func = isset($_GET['f']) ? trim($_GET['f']) : '';
		$r->id = isset($_GET['id']) ? trim($_GET['id']) : '';
		$r->page = isset($_GET['page']) ? intval($_GET['page']) : '';

		if ($r->page == 0) $r->page = 1; // default page

		// just make sure the act is something valid
		if (!preg_match('/^\w+$/', $r->act))
		{
			$r->act = $s->default_page;
		}

		return r::$r = $r; 
	}

	static function dump()
	{
		return '<pre>'.print_r(r::$r,1).'</pre>';
	}
}


/**
 * Page template
 *
 * @author leafo.net
 * @version 0.1
 * @package page
 * @subpackage classes
 */
class Page
{
	protected $tpl;
	protected $db;

	public function __construct() 
	{
		// just another way to access some common stuff
		$this->tpl = r::$tpl;
		$this->db = r::$db;
	}

	public function __get($get) 
	{
		return $this->tpl->$get;
	}

	public function __set($set, $value)
	{
		$this->tpl->$set = $value;
	}


	public function _home()
	{
		echo "You forgot to make a home for this page<br />";
	}	

	// when the page is loaded, before select takes place?
	public function onLoad() {
	}

	public function select()
	{
		$this->onLoad();

		$func = '_'.r::$r->func;
		if (is_callable(array($this, $func)))
			$this->$func();
		else $this->_home();
	}
}

/**
 * generates proper links to other pages
 *
 * @author leafo.net
 * @version 0.1
 * @package link
 * @subpackage classes
 */
class Linker 
{
	protected $base_url;
	public $amp = '&amp;';

	/**
	 * bind the linker and set base url
	 */
	public function __construct($base_url = null)
	{
		if ($base_url != NULL)
			$this->base_url = $base_url;
		else
			$this->base_url = r::$r->base_url;

		// bind some useful functions to the template manager
		if (isset(r::$tpl)) {
			r::$tpl->bindFunction('linkme', array($this, 'linkme'));
			r::$tpl->bindFunction('link', array($this, 'to'));
		}
	}


	public function to($act = null, $f = null, $id = null, $page = null)
	{
		if ($act == NULL)
			return $this->linkme();

		return $this->base_url.$act.
			(isset($f) ? '/'.$f.(isset($id) ? '/'.$id : '') : '');

	}

	public function linkme()
	{
		$url = R::$r->act.
			(!empty(R::$r->func) ? '/'.R::$r->func : '');

		return $this->base_url.$url;
	}

	/**
	 * send the user to the link
	 */
	public function go($act = null, $f = null, $id = null, $page = null)
	{
		$this->amp = '&';
		header('location: '.$this->to($act, $f, $id, $page));
	}

}



/**
 * this is a cheap ass linking class that doesn't depend on mod rewrite
 */
class SimpleLink extends Linker
{
	public function to($act = null, $f = null, $id = null, $page = null)
	{
		return $this->base_url. 
			($act ? '?act='.$act : '').
			($f ? $this->amp.'f='.$f : '').
			($id ? $this->amp.'id='.$id : '').
			($page ? $this->amp.'page='.$page : '');
	}

	public function linkme()
	{
		return $this->to(R::$r->act, R::$r->func, R::$r->id);
	}

}


?>