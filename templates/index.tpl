<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="{$template_url}style.css" media="screen" />
	{foreach $s in $stylesheets}
		<link rel="stylesheet" type="text/css" href="{$template_url}{$s}" media="screen" />
	{end}
	<title>{if $title}{$title} - {end}new leafo.net</title>

  <script type="text/javascript">
{literal}
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-136625-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
{end}
</script>

  </head>
  <body>
  	{$top}
	<div id="small-links"><div id="small-inner">
		<div id="small-right">
		{if $me.loggedIn}	
			Logged in as <a href="{link "members","profile",$me.id}">{$me.name}</a> |
			<a href="{link "members","logout"}">Logout</a>	
		{else}
			<a href="{link "members","login"}">Login</a> |
			<a href="{link "members","register"}">Regsiter</a>
		{end}
		</div>
		<a href="{link "board"}">Message Board</a> 
		{if $me.can.view_members}|  <a href="{link "members"}">Members</a> {end}
	</div> </div>
	<h1 id="logo"><a href="{link}">Leafo.net</a></h1>


	<div id="wrapper">
		{$content}
	</div>

	<div id="footer">leafo.net &copy; 2009 &middot; version 5 &#xB6;</div>

	{if $debug}
	<div id="debug">
		<h1>Template</h1>
		{$template}
		<h1>Queries</h1>
		{$queries}
		<h1>Registry</h1>
		{$registry}
	</div>
	{end}


  </body>
</html>

