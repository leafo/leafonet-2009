<?php
/**
 * Database interface
 *
 * @author leafo.net
 * @version 0.1
 * @package database
 */


/**
 * Global database access class
 */
class db
{
	private static $ref = null;
	public static function load($ref) {
		db::$ref = $ref; 
	}
	
	public static function query($q, $file = 0, $line = 0)
	{
		if (is_null(db::$ref)) exit('database has not been loaded');
		return db::$ref->query($q, $file, $line);
	}

	public static function escape($what) 
	{
		return db::$ref->escape($what);
	}

	public static function sanitize($what)
	{
		return db::$ref->sanitize($what);
	}

	public static function get()
	{
		return db::$ref;
	}

	public static function insertId()
	{
		return db::$ref->insertId();
	}

	public static function affected()
	{
		return db::$ref->affected();
	}
}

/**
 * an instanced database result
 * @package database
 * @subpackage classes
 */
class DBResult {
	private $result;
	public $numRows;
	
	function __construct(&$r) 
	{
		$this->result = $r;
		$this->numRows = @mysql_num_rows($r);
	}
	
	function fetchAssoc()
   	{
		return mysql_fetch_assoc($this->result);
	}
	
	function fetchRow() 
	{
		return mysql_fetch_row($this->result);
	}
	
	function close()
   	{
		return mysql_free_result($this->result);
	}

	function resource() {
		return $this->result;
	}
	
}

/**
 * an instanced database connection to mysql
 *
 * @package database
 * @subpackage classes
 */
class DBHandle {
	public $num_queries = 0;
	public $query_history = array();
	public $rowsAffected = 0;
	private $link;

	// are we in a transaction
	private $transaction = false;
	
	public function __construct($username, $password, 
		$database = null, $host = 'localhost')
   	{
		$this->link =
			@mysql_connect("localhost", $username, $password);

		if (!$this->link) {
			exit("Could not connect to mysql.");
		}
		
		if ($database && !mysql_select_db($database)) {
			exit("Could not select mysql database");
		}
		
	}
	
	/**
	 * Run a query and get a query result
	 */
	public function query($q, $file = 0, $line = 0)
   	{
		$this->num_queries++;
		$r = mysql_query($q);
		if (!$r) {
			exit("<b>Query Error:</b> file: {$file}, ".
				"line: {$line}<br/>".mysql_error().
				"<br/><pre>{$q}</pre>");
		}
		$this->query_history[] = $q;

		if ($r === true) { // not a query with a result
			return true; 
		}
		else return new DBResult($r);
	}


	public function insertId() {
		return mysql_insert_id();
	}

	public function affected() {
		return mysql_affected_rows();
	}

	public function escape($string)
	{
		return mysql_real_escape_string($string);
	}

	public function sanitize($what)
	{
		if (is_string($what))
			return "'".mysql_real_escape_string($what)."'";

		if (is_numeric($what))
			return $what;

		if (is_bool($what))
			return $what ? 1 : 0;

		if (is_null($what))
			return "NULL";

		// non insertable object ?
		throw new Exception("Failed to sanitize input: ". $what);
	}

	public function connection()
	{
		return $this->link;
	}


	public function startTransaction()
	{
		if ($this->transaction) return; // already in one
		$this->query('START TRANSACTION');
		$this->transaction = true;
	}

	/**
	 * roll back from a transaction
	 */
	public function rollback()
	{
		if (!$this->transaction) return;
		$this->query('ROLLBACK;');
		$this->transaction = false;
	}

	public function commit()
	{
		if (!$this->transaction) return;
		$this->query('COMMIT;');
		$this->transaction = false;
	}

}

$db = new DBHandle('leaf', '', 'leaf_leafot');
db::load($db);


?>
