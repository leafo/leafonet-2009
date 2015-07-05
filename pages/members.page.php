<?php

require 'include/blog.php';


/**
 * Member page
 */

class MembersPage extends Page
{
	public function onLoad()
	{
		$this->tpl->append('stylesheets', 'board.css');
	}

	/**
	 * List members
	 */
	public function _home()
	{
		/* $members = array(
			"leafo", "rex", "peter","ronald", "rice", "cake",
			"halbret", "mancef", "rofters", "huxtable");	
		 */
		$q = db::query('select * from `members` order by name ASC');
		$this->v__members = array();
		while ($m = $q->fetchAssoc())
			R::$tpl->append('members', $m);


		R::$tpl->render('members.list.tpl');
	}

	public function _register()
	{
		$vop = array(
			'name' => 'required',
			'password' => 'required',
			'repeat_password' => 'match[password]',
			'email' => 'required|email',
			'captcha' => 'equal['.$_SESSION['captcha_code'].']',
		);
		$v = Validator::validate($vop);
		if ($v->success()) {
			// search if use already exists
			if (!User::idFromName($v->post_name)) 
			{
				$u = User::create();
				$u->name = $v->post_name;
				$u->password = User::hash($v->post_password);
				$u->email = $v->post_email;

				trace($u);

				$u->update();

				return;
			} 

			$v->pushError("Username already exists");
		} 
		
		if ($v->hasErrors())
			$this->v__errors = $v->errors();

		// set the captcha
		$_SESSION['captcha_code'] = substr(md5(rand()), 0, 5);

		echo $this->tpl->render('members.register.tpl');
	}

	/**
	 * Show login form
	 */
	public function _login()
	{
		$vop = array(
			'name' => 'required',
			'password' => 'required');
		$v = Validator::validate($vop);
		if ($v->success()) {
			// find the id
			list($id) = db::query("select id from `members` where 
				name = '".$v->db_name."'")->fetchRow();
			if ($id == NULL) {
				$v->pushError('Could not find username');
			} else {
				try {
					$tuser = User::login($id, User::hash($v->post_password));
					$tuser->createCookie();

					// trace($tuser, 'You have logged in');

					// let template update
					$this->v__me = $tuser;

					echo 'You have logged in';
					return;
				} catch (Exception $ex) {
					$v->pushError('Password does not match');
				}

			}
		} 

		if ($v->hasErrors())
			$this->v__errors = $v->errors();

		// render the login form
		echo $this->tpl->render('members.login.tpl');
	}

	/**
	 * Logout the member
	 */
	public function _logout()
	{
		if (r::$me->loggedIn) {
			r::$me->deleteCookie();
			// prevent template from showing login buttons
			$this->tpl->v__me = new Guest();
			echo 'You are logged out!';
		} else 
			trace(r::$me, 'You are not logged in');
	}

	/**
	 * Show a member profile
	 */
	public function _profile() 
	{
		function notfound()
		{
			echo "User not found";
		}

		if (($id = intval(r::$r->id)) == NULL) {
			return notfound();
		}

		// don't need to look self up, already done
		if ($id == r::$me->id) {
			$user = r::$me;
		} else {
			try {
				$user = User::load($id);
			} catch (Exception $ex) {
				return notfound();
			}
		}

		$this->v__member = $user->context();

		// find all the posts for this member
		$this->v__posts = Blog::viewWhere('author_id = '.$user->id);

		$this->tpl->render('members.profile.tpl');
	}

}



?>
