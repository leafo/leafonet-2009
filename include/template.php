<?php

require_once 'compiler.php';

/**
 * Compiled template class 
 *
 * @author leafo.net
 * @version 0.1
 * @package template
 * @subpackage classes
 */
class Template 
{
	private $template_dir = 'templates/';
	private $compile_dir = 'compiled/';
	private $vars = array(); // The template context
	private $funcs = array();

	private $grab_stack = array();

	/**
	 * create a new template dataset
	 */
	public function __construct($template_dir = null, 
		$compile_dir = null)
	{
		if (isset($template_dir))
			$this->template_dir = $template_dir;

		if (isset($compile_dir))
			$this->compile_dir = $compile_dir;

		$template_url = r::$r->base_url.$this->template_dir;

		// Register some important variables to template context
		$this->merge(array(
			'base_url' => r::$r->base_url,
			'template_url' => $template_url,
			'image_url' => $template_url.'images/',
			'lib_url' => r::$r->base_url.'lib/',
		));

		$this->v__javascript_include = array();

		// bind our template manager's "library"
		$this->bindFunction('alternate', array($this, 'template_alternate'));
		$this->bindFunction('truncate', array($this, 'template_truncate'));
		$this->bindFunction('implode', array($this, 'template_implode'));
		$this->bindFunction('timestring', array($this, 'template_timestring'));
	}

	/**
	 * Bind a function to template
	 */
	public function bindFunction($name, $func)
	{
		$this->funcs[$name] = $func;
	}

	/**
	 * Call a bound function
	 */
	private function callFunction($name, $args = array())
	{
		if (array_key_exists($name, $this->funcs))
			echo call_user_func_array($this->funcs[$name], $args);
		else
		   	echo ''; // do nothing? 
	}

	// implode an array with a separator
	private function template_implode($glue, $array) {
		return implode($glue, $array->array);
	}

	// alternate between variable number of arguments
	private function template_alternate() {
		static $v = 0;
		$args = func_get_args();
		echo $args[$v++ % count($args)];
	}

	// truncate a string and add something at the end if set
	private function template_truncate($what, $length = 40, $attach = null)
	{
		if (strlen($what) <= $length) 
			return print $what;

		echo substr($what, 0, $length).($attach ? $attach : '');
	}

	// turn an amount of seconds into a readable time
	private function template_timestring($what)
	{
		$list = array(); // final list of times
		// years
		$y = round($what / (60*60*24*356));
		$what = $what % (60*60*24*356);
		if ($y) $list[] = $y. ' year'.($y > 1 ? 's' : '');

		$d = round($what / (60*60*24));
		$what = $what % (60*60*24);
		if ($d) $list[] = $d. ' day'.($d > 1 ? 's' : '');

		$h = round($what / (60*60));
		$what = $what % (60*60);
		if ($h) $list[] = $h. ' hour'.($h > 1 ? 's' : '');

		$m = round($what / 60);
		$what = $what % 60;
		if ($m) $list[] = $m. ' minute'.($m > 1 ? 's' : '');

		$s = $what;
		if ($s) $list[] = $s. ' second'.($s > 1 ? 's' : '');

		for ($i = 0, $len = count($list); $i < $len; $i++)
		{
			if ($i > 1 && $i == $len - 1)
				echo ' and ';
			else if ($i > 0 && $i < $len)
				echo ', ';

			echo $list[$i];
		}
	}
		

	/**
	 * Set a template variable by reference
	 */
	public function set($what, &$to)
	{
		$this->vars[$what] =& $to;
	}

	/**
	 * Add another array element to a template var
	 */
	public function append($var, $what)
	{
		$this->vars[$var][] = $what;
	}

	/**
	 * Start grabbing all output into an object buffer
	 * to put in template var $vname
	 */
	public function grab($vname) 
	{
		if (count($this->grab_stack) > 32)
			throw new Exception("Grab stack too large");

		array_push($this->grab_stack, $vname);
		ob_start();
	}

	/**
	 * close a grab stack and save the output
	 */
	public function end()
	{
		$vname = array_pop($this->grab_stack);
		if ($vname == NULL) 
			return;

		$this->vars[$vname] = ob_get_clean();
	}

	/**
	 * Merge another array into the current template variables
	 */
	public function merge($array) 
	{
		$this->vars = array_merge($this->vars, $array);
	}

	/**
	 * Magic getter for template variables
	 */
	public function __get($get)
	{
		if (substr($get, 0, 3) == 'v__') {
			// return false if it doesn't exist (prevent warnings)
			if (array_key_exists(($vname = substr($get, 3)), $this->vars)) {
				if (is_array($this->vars[$vname]))
					return new ArOb($this->vars[$vname]);
				else 
					return $this->vars[$vname];
			} else {
				return false;
			}
		}
	}

	/**
	 * Magic function to set template varialbe
	 */
	public function __set($set, $value) 
	{
		if (substr($set, 0, 3) == 'v__') {
			$this->vars[substr($set, 3)] = $value;;
		}

	}

	/**
	 * Fetch a template file
	 * Compile if required
	 *
	 * @returns path of the compiled template file
	 */
	public function fetch($file) 
	{
		$fname = R::$r->base_path.$this->template_dir.$file;
		$target = 
			R::$r->base_path.$this->compile_dir.md5($fname).'.php';

		// Compile the template only if there is newer uncompiled version
		if (is_readable($target) &&
			filemtime($fname) <= filemtime($target))
		{
			return $target;
		}

		$c = new Compiler($fname);
		try {
			file_put_contents($target, $c->compile());
		} catch (Exception $ex) {
			exit("Failed to compile '{$fname}': ".$ex->getMessage());
		}

		return $target;
	}

	/**
	 * Render a template to output immediately
	 */
	public function render($file)
	{
		$fname = $this->fetch($file);

		// process any template arguments 
		// this just sets the template values listed to true
		for ($i = 1; $i < func_num_args(); $i++) 
			$this->__set('v__'.func_get_arg($i), true);

		// All compiled template files are run inside this scope
		include $fname;
	}

	public function __tostring()
	{
		return '<pre>'.htmlentities(print_r($this->vars,1)).'</pre>';
	}


}

/**
 * Array wrapper class
 * This allows assoc arrays to be accessed and modified with -> operator
 * while still preserving the original array as an array
 *
 * @author leafo.net
 * @version 0.1
 * @package template
 * @subpackage classes
 */
class ArOb
{
	public $array;
	public function __construct(&$array)
   	{
		$this->array =& $array;
	}

	public function __get($what) 
	{
		if ($what == 'acount') return $this->count();

		if (is_array($this->array[$what])) {
			return new ArOb($this->array[$what]);
		} else {
			return $this->array[$what];
		}
	}

	public function __set($what, $value)
	{
		$this->array[$what] = $value;
	}

	public function __toString()
	{
		return '<pre>'.print_r($this->array, 1).'</pre>';
	}

	public function count() 
	{
		return count($this->array);
	}
}




?>
