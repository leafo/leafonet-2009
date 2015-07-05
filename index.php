<?php
/**
 * leafo.net 5
 *
 * @author leafo
 * @version 1.0
 */

session_start(); // don't use this for gamingw, smf does it
// set_error_handler('phpError');
date_default_timezone_set('America/New_York');


require './class_profiling.inc.php';
$prof = new profiling();


/**
 * debug print
 * print_r the variable under question
 */
function trace($var, $caption = null)
{
	echo '<div class="error">';
	if ($caption) echo '<h2>'.$caption.'</h2>';
	
	echo '<pre>'.htmlentities(print_r($var, 1)). '</pre></div>';
}

$debug = false;
$tidyPage = false && ob_start();

require_once './include/util.php';

$path = realpath(dirname(__FILE__)).'/';
$url = '/beta/';
// $url = 'http://leafo.rh.rit.edu/leafo/';

// initialize the registry
$r = r::load(array(
	'default_page' => 'board',

	'base_path' => $path,
	'forum_path' => $path.'forums/',

	'base_url' => $url,
	'forum_url' => $url.'forums/',
));

require './include/database.php';	
require './include/template.php'; 	r::$tpl = $tpl = new Template;


r::$link = new SimpleLink;


require './include/user.php'; 	

// make user accessable in registery and template manager
r::$me = $tpl->v__me = $user;

require './include/validator.php'; 	


function phpError($eno, $error, $file, $line)
{
	// ignore indexing error
	// if ($eno == E_NOTICE) return;
	r::$tpl->v__top_failure .= 
		"<div class='error'>{$eno} Error {$file}[{$line}]".
		$error.'<br /></div>';
}


if (!is_file($r->page_dir.$r->act.'.page.php'))
{
	$r->act = $r->default_page;
} 

require $r->page_dir.$r->act.'.page.php';




$class = $r->act.'Page';
$page = new $class();


// Gencontent and dump it
$tpl->grab('content'); 
{
	$prof->add('start rendering page');
	$page->select();
	$prof->add('end rendering page');


	if ($debug) {
		// dump the template variables for debugging
		// we grab it inside the content so we don't see
		// a copy of the content in the dump
		$tpl->grab('template');
		{
			echo $tpl;
		} 
		$tpl->end();
		
		// dump all the queries used for debugging
		$tpl->grab('queries');
		{
			echo '<pre>';
			foreach (db::get()->query_history as $q)
				echo $q."\n\n";
			echo '</pre>';
		}
		$tpl->end();

		$tpl->grab('registry');
		{
			echo r::dump();
		}
		$tpl->end();

	}
}
$tpl->end();

$tpl->v__debug = $debug;
$tpl->render('index.tpl');

if ($tidyPage)
{
	$page = ob_get_clean();
	$tidy_config = array(
           'indent'         => true,
           'output-xhtml'   => true,
		   'wrap'           => 200);

	$tidy = new tidy;
	$tidy->parseString($page, $tidy_config);
	$tidy->cleanRepair();
	
	echo $tidy;
}

$prof->end();

/*
echo '<div style="background: white">';
echo $prof->get_result();
echo '</div>';

$mem = memory_get_usage(1);
trace(($mem/1000).'kb', 'base');
 */

?>
