
<ul id="board-tabs">
	<li class="current"><a href="{link "board"}">Board Index</a></li>
	{if $me.can.forum_post}
	<li><a href="{link "board","newtopic",$board.id} ">Post New Topic</a></li>
	{end}
</ul>

<div class="block-border">
<div class="block">


<div style="text-align: right;">
	{$pageselect}
</div>

<table width="100%" cellspacing="0" cellpadding="0" class="board-header">
<tr>
	<td style="text-align: left">Topic</td>
	<td>Last Post</td>
	<td width="100px">Replies</td>
	<td width="60px">Views</td>
</tr>
</table>

{if !$board.topics}<p>There don't appear to be any topics.</p>{end}
{foreach $t in $board.topics}

<div class="{alternate "odd", ""} topic-block">

	<div class="sub" style="float: right">
	<table cellspacing="0" cellpadding="0">
	<tr align="right">
		<td>{if $t.replies}{$t.last_post} by {$t.last_post_author.link}{end}</td>
		<td width="100">{$t.replies}</td>
		<td width="60">{$t.views}</td>
	</tr>
	</table>
	</div>

	<div class="topic-title{if $t.has_new} new{end}">{$t.link} {if $t.subtitle}({$t.subtitle}){end}</div>
	<div class="sub">&nbsp;&nbsp;&nbsp;Written by {$t.author.link} on {$t.date}</div>

</div>

{end}

{if $pageselect}
<br />{$pageselect}
{end}

</div>
<div class="block-footer">

<div style="float:right; text-align: right;">
	<b>Total topics</b>: {$stats.topics}<br />
	<b>Total posts</b>: {$stats.posts}
</div>

<div><b>Members online</b>:</div>
<div>{$online}</div>

</div>
</div>

{if !$me.loggedIn}
	<div class="error"><center><a href="{link "members","login"}">Login</a> or <a href="">register</a> to post</center></div>
{end}


