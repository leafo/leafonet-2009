<?php
/**
 * session manager
 *
 */

class Session {
	private $cookieName = "leafox";

	private $changed = false;
	private $session = array();

	/**
	 * attempt to read the session or create a blank one
	 */
	public function __construct()
	{
		if (array_key_exists($this->cookieName, $_COOKIE)) {
			$this->session = @unserialize($_COOKIE[$this->cookieName]);

			if (!$this->session) {
				$this->session = array(); // default session
				$changed = true;
			}
		}
	}

	/**
	 * save the session back into cookie now that we are done
	 */
	public function __destruct()
	{
		// save the session to cookie
		if ($this->changed)
		{

		}

	}

	/**
	 * delete the session
	 */
	public function delete()
	{

	}

}

?>
