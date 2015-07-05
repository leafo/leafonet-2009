<?php


/**
 * Table representing a folder tree
 *
 * @author leafo.net
 * @version 1.0
 * @package folders
 * @subpackage classes
 */
class Folders {
	public $table;

	public function __construct($table = 'folders') 
	{
		$this->table = $table;
	}

	/**
	 * Create the table in the database
	 * and create a root folder
	 */
	public static function install($table = 'folders')
	{
		// create the table
		db::query("create table if not exists `{$table}` (
			`id` int(10) unsigned not null auto_increment,
			`lft` int(10) signed not null,
			`rgt` int(10) signed not null,
			`name` varchar(45) not null,
			`count` int(10) unsigned not null default '0',
			`icon` varchar(80) not null,

			primary key (`id`) 
		) default charset=utf8");
	}

	/**
	 * Insert a new folder. If parent is null, folder is 
	 * created on rool level, otherwise it is placed inside the
	 * parent
	 */
	public function insert($name, $parent = null)
	{
		if ($parent == null) $pid = null;
		else $pid = intval($parent);
		if ($pid < 0) throw new Exception('parent must be positive');

		// are we creating a new root node
		if ($pid == null) {
			// try to find the last node
			list($min) = db::query("select rgt from {$this->table}
				order by rgt desc")->fetchRow();

			if ($min == null)
				$lft = 1;
			else
				$lft = $min + 1;

		} else {
			list($rgt) = db::query("select rgt from {$this->table} where
				id = {$pid}")->fetchRow();
			if ($rgt == null) throw new Exception('failed to find parent');
			db::query("update {$this->table} set
				rgt = rgt + 2 where rgt >= {$rgt}");
			db::query("update {$this->table} set
				lft = lft + 2 where lft > {$rgt}");
			$lft = $rgt; // insert as last element in parent
		}
		
		// Insert the node 
		$name = db::escape($name);
		$rgt = $lft + 1;
		db::query("insert into {$this->table} set 
			name = '{$name}', lft = {$lft}, rgt = {$rgt}");

		return db::insertId();
	}

	public function move($src, $dst) 
	{
		$src = intval($src);
		$dst = intval($dst);
		list($slft, $srgt) = db::query("select lft, rgt from {$this->table} where
			id = {$src}")->fetchRow();
		if ($slft == null) throw new Exception('failed to find src');
		
		// just make sure the destination exists
		if ($dst != null) {
			list($dst) = db::query("select id from {$this->table} where
				id = {$dst}")->fetchRow();
			if ($dst == null) throw new Exception('failed to find dest');
		}

		// move the source into negative space so it doesn't get in the way
		// of repositioning the other nodes
		$noffset = $srgt + 1; // (negative offset)
		db::query("update {$this->table} set lft = lft - {$noffset} 
			where lft >= {$slft} and lft < {$srgt}"); 
		db::query("update {$this->table} set rgt = rgt - {$noffset}
			where rgt > {$slft} and rgt <= {$srgt}");

		// delete the spot from the tree
		$diff = $srgt - $slft + 1;
		db::query("update {$this->table} set
			lft = lft - {$diff} where lft > {$srgt}");
		db::query("update {$this->table} set
			rgt = rgt - {$diff} where rgt > {$srgt}");

		// remember were the source was moved
		$slft -= $noffset;
		$srgt -= $noffset;

		// make room to reinsert the source into the dest
		$diff = $srgt - $slft + 1;
		if ($dst == null) { // no room needed, stick at end
			list($max) = db::query("select rgt from {$this->table}
				order by rgt desc limit 1")->fetchRow();
			$shift = -$slft + 1 + $max;
		} else {
			// ..
			list($dlft, $drgt) = 
				db::query("select lft, rgt from {$this->table} where
					id = {$dst}")->fetchRow();
			db::query("update {$this->table} set lft = lft + {$diff}
				where lft > {$dlft}");
			db::query("update {$this->table} set rgt = rgt + {$diff}
				where rgt > {$dlft}");
			$shift = - $slft + 1 + $dlft;
		}

		// move the source out of negative space
		db::query("update {$this->table} set lft = lft + {$shift},
			rgt = rgt + {$shift} where rgt < 0");

		return true;
	}

	public function rename($fid, $name)
	{
		$fid = intval($fid);
		// just make sure it exists
		list($oname) = db::query("select name from {$this->table}
			where id = ".$fid)->fetchRow();
		if (empty($oname)) throw new Exception('failed to find folder');
		$name = trim($name);
		if ($name == '') throw new Exception('invalid name');
		
		db::query("update {$this->table} set name = '".db::escape($name)."'
			where id = {$fid}");

		return array($oname, $name);
	}

	/**
	 * Delete a folder and all of its children
	 */
	public function delete($fid)
	{
		$fid = intval($fid);
		list($lft, $rgt) = db::query("select lft, rgt from {$this->table}
			where id = ".intval($fid))->fetchRow();

		if ($lft == null) throw new Exception('failed to find folder to delete');

		// delete folder and its subfolders
		db::query("delete from {$this->table} where
			lft >= {$lft} and lft <= {$rgt}");

		// update the tree
		$diff = ($rgt - $lft) + 1;
		db::query("update {$this->table} set
			lft = lft - {$diff} where lft > {$lft}");

		db::query("update {$this->table} set
			rgt = rgt - {$diff} where rgt > {$rgt}");

		return true;
	}

	/**
	 * Get the path to the root from a certain node
	 * TODO: UNDO THE CHANGE FOR LEFT RIGHT
	 *
	 * If there is only a lft then we assume it is an id
	 */
	public function pathFrom($id, $rgt = null)
	{
		$id = intval($id);
		$rgt = intval($rgt);

		if (!$rgt)
			list($lft, $rgt) = db::query("select lft, rgt from {$this->table}
				where id = {$id}")->fetchRow();
		else 
			$lft = $id;

		if ($lft == null) throw new Exception('failed to find parent for path');

		$r = db::query("select * from {$this->table} 
			where lft <= {$lft} and rgt >= {$rgt} 
			order by lft asc");

		$list = array();
		while ($f = $r->fetchAssoc()) {
			$list[] = Folders::context($f);
		}

		return $list;
	}

	// update counter for folder
	public function count($fid, $amount) {
		$fid = intval($fid);
		if ($fid == null) throw new Exception('Failed to find folder for increment');
		$amount = intval($amount);
		db::query("update {$this->table} set count = count + {$amount}
			where id = {$fid}");
	}

	/**
	 * Dump the folder structure in a tree form
	 */
	public function tree($from = null)
	{
		if ($from == null) 
			$q = "select * from {$this->table} order by lft asc";
		else {
			$from = intval($from);
			$q = "select * from {$this->table} where 
				lft >= (select lft from {$this->table} where id = {$from}) and
				rgt <= (select rgt from {$this->table} where id = {$from})
				order by lft asc";
		}

		$r = db::query($q);
		while ($n = $r->fetchAssoc())
			$nodes[] = $n;

		function processNode(&$remaining, $end = 9999)
		{
			if (count($remaining) == 0) return;

			$tree = array();
			$top = $remaining[0];
			while ($top['lft'] < $end) {
				array_shift($remaining);
				// Either leaf or a branch
				if ($top['rgt'] - $top['lft'] > 1) {
					$children = processNode($remaining, $top['rgt']);
				} else {
					$children = null;
				}
				/*
				$tree[] = array_merge(Folders::context($top), 
				array('children' => $children));
				 */
				Folders::insertSorted(array_merge(Folders::context($top), 
					array('children' => $children)), $tree);

				if (count($remaining) == 0) break;
				$top = $remaining[0];
			}

			return $tree;
		}

		$tree = processNode($nodes);		
		return $tree;
	}

	/**
	 * Inserts something into an array that is sorted
	 * The argument array is modified by reference
	 */
	public static function insertSorted($what, &$array)
	{
		$c = count($array);
		if ($c == 0) return $array[] = $what;
		for ($i = 0; $i < $c; $i++)  {
			if (strcasecmp($array[$i]['name'], $what['name']) > 0) {
				return array_splice($array, $i, 0, array($what));
			}
		}
		return $array[] = $what;
	}

	/**
	 * get a folder from its id
	 */
	public function get($fid) 
	{
		$fid = intval($fid);
		$f = db::query("select * from {$this->table} where id = {$fid}
			limit 1")->fetchAssoc();
		if (!$f) throw new Exception('failed to find folder');

		return Folders::context($f);
	}

	public static function context($row) 
	{
		$row['has_children'] = $row['lft'] != ($row['rgt'] - 1);
		return $row;
	}

}

/**
 * A single folder
 * I don't think I use this class anymore
 */
class Folder 
{
	public $lft = -1;
	public $rgt = -1;
	public $name = '';
	public $folders; // reference to where this folder belongs

	protected function __construct($lft, $rgt, $name, $folders) 
	{
		$this->lft = $lft;
		$this->rgt = $rgt;
		$this->name = $name;
		$this->folders = $folders;
	}

	/**
	 * Create new folder object with all data already available
	 */
	static public function loadAll($lft, $rgt, $name, $folders)
	{
		echo 'creating folder';
		return new Folder($lft, $rgt, $name, $folders);
	}

	/**
	 * Load a folder from the left value
	 */
	static public function loadFromLeft($lft, $folders)
	{
		list($lft, $rgt, $name) =
			R::$db->queryRow(
				"select lft, rgt, name from {$folders->table_name}
		where lft = ".intval($lft));
		if ($lft == null) throw new Exception('Failed to load from left');
		return new Folder($lft, $rgt, $name, $folders);
	}

	static public function loadFromName($name, $folders)
	{
		list($lft, $rgt, $name) =
			R::$db->queryRow("
			select lft, rgt, name from {$folders->table_name}
		where name like '".R::$db->escape($name)."' limit 1");
		if ($lft == null) throw new Exception('Failed to load from name');
		return new Folder($lft, $rgt, $name, $folders);
	}

}

?>