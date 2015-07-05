<?php

require 'include/bbs.php';

class BoardPage extends Page
{
	public function onLoad() {
		$this->tpl->append('stylesheets', 'board.css');

		// if (!r::$me->loggedIn)
		// 	r::$link->go('members','login');
	}

	public function _home()
	{
		// just some test code
		/*
		Board::install();
		Board::createBoard("General", "test board");
		 */
		/*
		Board::postTopic(1, r::$me, array(
			'title' => 'The Test Topic',
			'body' => 'This is my message to everyone',
		));
		 */

		// for now we are just going to get the first board


		/*
		Board::postReply(1, r::$me, 
			array('body' => 'This is my reply to your pitiful message'));

		 */

		// User::setP();

		list($this->v__board, $totalpages) = Board::viewBoard(1, r::$r->page);
		$this->v__pageselect = $totalpages == 1 ? '' : 
			Util::renderPage(r::$r->page, $totalpages, array('board'));
		$this->v__online = User::getOnline();


		// BoardStats::install();

		$this->v__stats = 
			db::query('select count(*) posts from '.Board::$table_posts)->fetchAssoc() +
			db::query('select count(*) topics from '.Board::$table_topics)->fetchAssoc();

		$this->tpl->render('board.home.tpl');
	}


	public function _topic()
	{
		list($topic, $totalpages) = 
			Board::viewTopic(intval(r::$r->id), r::$me, r::$r->page);
		if (!$topic) return print 'Invalid location';

		// show status messages if the article has been changed
		if ($_SESSION['new_topic']) {
			unset($_SESSION['new_topic']);
			$this->v__status = 'Your topic has been posted.';
		}

		// show status messages if the article has been changed
		if ($_SESSION['new_reply']) {
			unset($_SESSION['new_reply']);
			$this->v__status = 'Your reply has been posted';
		}


		$this->v__topic = $topic;
		$this->v__pageselect = $totalpages == 1 ? '' :
			Util::renderPage(r::$r->page, $totalpages, 
				array('board','topic',$topic['id']));

		// trace(Board::viewTopic($id));
		$this->tpl->set('title', htmlspecialchars($topic['title']));
		$this->tpl->render('board.topic.tpl');
	}

	public function _newtopic()
	{
		return print "disabled";
		if (!r::$me->can('forum_post'))
			return print 'You must be logged in to post a topic';

		if(!($board = Board::getBoard(intval(r::$r->id))))
			return print 'Invalid location';


		$v = Validator::validate(array(
			'title' => 'required',
			'body' => 'required',
		));
		if ($v->success()) {
			$post = array(
				'title' => $v->post_title,
				'body' =>$v->post_body,
			);

			$tid = Board::postTopic($board, r::$me, $post);

			$_SESSION['new_topic'] = 1;
			r::$link->go('board', 'topic',  $tid);

		} else if($v->hasErrors()) {
			$this->v__errors = $v->errors();
		}


		$this->v__post = $v->post(); // give the post data 
		$this->tpl->render('board.newtopic.tpl');

	}

	public function _newreply()
	{
		return print "disabled";
		if (!r::$me->can('forum_post'))
			return print 'You must be logged in to post a topic';

		if(!($topic = Board::getTopic(intval(r::$r->id))))
			return print 'Invalid location';

		$this->v__topic = $topic;

		$v = Validator::validate(array(
			'body' => 'required',
		));
		if ($v->success()) {
			$rid = Board::postReply($topic, r::$me, array('body' => $v->post_body));	
			
			$_SESSION['new_reply'] = 1;
			r::$link->go('board', 'topic', $topic['id'], -1);

		} else if ($v->hasErrors()) {
			$this->v__errors = $v->errors();
		}

		$this->v__post = $v->post();
		$this->tpl->render('board.newreply.tpl');
	}

	public function _edit()
	{
		if (!($post = Board::getReply(intval(r::$r->id)))) return;

		// see if we can edit
		if (!r::$me->can('forum_edit') && !($post['author_id'] == r::$me->id &&
			r::$me->can('forum_edit_own'))) return print 'Invalid location';

		$need = array('body' => 'required' ,);

		if ($post['topic_post']) { // it is a topic
			$need['title'] = 'required';
			$this->tpl->v__is_topic = true;
		}
		
		$v = Validator::validate($need);
		if ($v->success()) {
			
			Board::editPost($post['id'], array(
				'title' => $v->post_title,
				'body' => $v->post_body,
				'topic_id' => $post['topic_id'],
				'topic_post' => $post['topic_post'],
			));

			// TODO:  send you to the right post...	
			r::$link->go('board', 'topic', $post['topic_id']);
			return;
		} else if ($v->hasErrors()) {
			$this->tpl->v__post = $v->post();
			$this->tpl->v__errors = $v->errors();
		} else {
			$this->tpl->v__post = $post;
		}


		$this->tpl->v__reply = $post;
		$this->tpl->render('board.edit.tpl');
	}

	public function _delete()
	{
		if (!($post = Board::getReply(intval(r::$r->id)))) return;

		// see if we can delete
		if (!r::$me->can('forum_delete') && !($post['author_id'] == r::$me->id &&
			r::$me->can('forum_delete_own'))) return print 'Invalid location';

		if ($post['topic_post'])
			Board::deleteTopic($post['topic_id']);
		else 
			Board::deletePost($post['id']);

		// go to the board
		// ...
	}
}


?>
