<?php

/**
 * Validate external data
 *
 * @author leafo.net
 * @version 0.1
 * @package validator
 * @subpackage classes
 */
class Validator 
{
	private $post;
	private $errors = array();
	private $status;
	public static $SUCCESS = 1;
	public static $FAILED = 2;
	public static $NO_SUBMIT = 3;

	/**
	 * validate a bunch of post vars, 
	 * return the instance of the validator
	 */
	static public function validate($vars, $trigger = 'form_submit')
	{
		return new Validator($vars, $trigger);
	}

	/**
	 * create new validator and validate the content right away
	 */
	private function __construct($vars, $trigger) 
	{
		
		// Fetch all the post data and set the initial state
		if (get_magic_quotes_gpc()) {
			foreach($_POST as $key=>$value) 
				$this->post[$key] = stripslashes($value);
		} else $this->post = $_POST;

		$this->status = Validator::$NO_SUBMIT;

		// nothing was submitted
		if (!isset($this->post[$trigger])) {
			$this->status = Validator::$NO_SUBMIT;
			return $this;
		}

		$failed = false;
		foreach ($vars as $what=>$how) 
		{
			if (!array_key_exists($what, $this->post))
				continue; // ignore non existant key

			$failed = !$this->validateParam($what, $how) || $failed;
		}

		if ($failed) 
			$this->status = Validator::$FAILED;
		else 
			$this->status = Validator::$SUCCESS;
	}

	/**
	 * get post vars
	 *
	 * prefixing the variable with post gets the raw post data
	 * prefixing the variable with db gets escaped database insertable value
	 */
	public function __get($name)
	{
		if (substr($name, 0, 5) === 'post_') {
			return $this->post[substr($name, 5)];
		}

		if (substr($name, 0, 3) === 'db_') {
			return db::escape($this->post[substr($name, 3)]);
		}
	}

	/**
	 * True if the form validated
	 */
	public function success()
	{
		return $this->status == Validator::$SUCCESS;
	}
	
	/**
	 * check of the validator has any errors from validation
	 */
	public function hasErrors()
	{
		return !empty($this->errors);
	}

	/**
	 * Validate a post property
	 */
	protected function validateParam($what, $how)
	{
		$valid = true;
		$funcs = explode('|', $how);
		foreach ($funcs as $f) 
		{
			if (!preg_match('/([^\[]+)(\[(.+)\])?/', $f, $matches)) {
				echo "failed match";
				continue; // not valid syntax
			}

			$f = 'v_'.$matches[1];
			$param = isset($matches[3]) ? $matches[3] : false;

			if (is_callable(array($this, $f)))
			{
				if ($param)
					$failed = !$this->$f($this->post[$what], $param);
				else
					$failed = !$this->$f($this->post[$what]);
			} 
			else 
			{
				echo 'unknown function: '.$f.'<br />';
			}

			// Test failed
			if 	($failed) 
			{
				// name of the field
				$name = str_replace('_', ' ', $what);

				$this->errors[] = 
					ucfirst(sprintf($this->messages[$matches[1]],
						$name, $param));

				// fall out on first failure
			}

			$valid = $valid && !$failed;

			// break out on first failure
			if (!$valid) break;
		}

		return $valid;
	}

	/** get the errors object **/
	public function errors()
	{
		return $this->errors;
	}

	/** get the post data **/
	public function post()
	{
		if (!is_array($this->post)) return array();

		foreach ($this->post as $k => $v)
			$np[$k] = htmlentities($v);

		return $np;
	}

	/**
	 * Put a new error on the end of the list
	 */
	public function pushError($msg) 
	{
		$this->errors[] = $msg;
		$this->status = Validator::$FAILED;
	}


	/**
	 * validation functions start with v_ and return 
	 * true when they sucessfully validate a string
	 *
	 * what		the value of the field being tested
	 * [param]	the value of the parameter 
	 */

	// error messages for respective match functions
	public $messages = array(
		'required' => '%s is required',
		'numeric' => '%s must be numeric',
		'minimum' => '%s must be at least %s characters',
		'maximum' => '%s can be at most %s characters',
		'email' => 'valid email address is required',

		'match' => '%s does not match',
		'equal' => '%s does not match',
		);

	protected function v_required($what)
	{	
		// return !empty($what);
		return trim($what) != '';
	}

	protected function v_numeric($what)
	{
		return is_numeric($what);
	}

	protected function v_minimum($what, $param)
	{
		return strlen($what) >= $param;
	}

	protected function v_maximum($what, $param)
	{
		return strlen($what) <= $param;
	}

	protected function v_email($what)
	{
		return preg_match('/[\w\d._\-]+@[\w\d._\-]+\.[\w]+/', $what);
	}

	/**
	 * match an field to another field,
	 * useful for repeat password field
	 */
	protected function v_match($what, $param)
	{
		return $what === $this->post[$param];
	}

	protected function v_equal($what, $param)
	{
		return $what === $param;
	}

}




?>
