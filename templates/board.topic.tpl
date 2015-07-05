
{if $status}
<div style="text-align: center"><span class="error">{$status}</span></div>
{end}

<ul id="board-tabs">
	<li><a href="{link "board"}">Board Index</a></li>
	{if $me.can.fourm_post}<li><a href="{link "board","newtopic",1} ">Post New Topic</a></li>{end}
	<li class="current"><a href="{linkme}">Topic '{$topic.title}'</a></li>
	{if $me.can.forum_post}<li><a href="{link "board", "newreply", $topic.id} ">Post Reply</a></li>{end}
</ul>

<div class="block-border">
<div class="block">


{if $pageselect}<div style="float: right">{$pageselect}</div>{end}

<div class="topic-header">{$topic.link} by <a href="">leafo</a>
	[{$topic.views} views] 
	[{$topic.replies} replies]</div>


{foreach $post in $topic.posts}
	<div class="post-block {alternate "odd", ""}">


	{if $oldpost}
		{$post.author.link} said on {$post.date} 
		{if $post.can_edit || $post.can_delete}
		<span class="sub"> 
			{if $post.can_edit}[<a href="{link "board","edit",$post.id}">edit</a>]{end}
			{if $post.can_delete}[<a href="{link "board","delete",$post.id}">delete{if $post.is_topic} topic{end}</a>]{end}
		</span>
		{end}

		<div class="post-body">{$post.body}</div>
	{else}
		
		<table width="100%" cellspacing="0" cellpadding="0"><tr valign="top">
			<td width="160">
				<div class="post-name">{$post.author.link}</div>	
				<div>{$post.author.group_name}</div>
				<div>Posts: {$post.author.posts}</div>	
				<div>Joined: {$post.author.join_date}</div>	
			</td>
			<td style="border-left: 1px dashed #AFAF85;padding-left: 8px;">
				<div class="post-header sub">
					{$post.date}
					{if $post.can_edit}[<a href="{link "board","edit",$post.id}">edit</a>]{end}
					{if $post.can_delete}[<a href="{link "board","delete",$post.id}">delete{if $post.is_topic} topic{end}</a>]{end}
				</div>
				<div class="post-body">{$post.body}</div>
			</td>
		</tr></table> 


	{end}


	</div>
{end}

{if $pageselect}<div class="topic-footer">{$pageselect}</div>{end}

</div>
</div>


<div class="block-border">

<form action="{link "board","newreply",$topic.id}" method="post" name="board.newreply">
<input type="hidden" name="form_submit" value="1" />

<div class="block">

<h2>Post A Reply</h2>

{if !$me.can.forum_post}
	{if !$me.loggedIn}	
		<p>You must be <a href="{link "members","login"}">logged in</a> to post on the board.</p>
	{else}
		<p>You don't have permission to post here</p>
	{end}
{else}

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}

	<label for="body">Body</label>
	<textarea class="text" name="body" id="body"></textarea>


{end}
</div>

{if $me.loggedIn}
<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Post Reply" />
</div>
{end}

</form>

</div>
