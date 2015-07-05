
<ul id="board-tabs">
	<li><a href="{link "board"}">Board Index</a></li>
	<li><a href="{link "board","newtopic",1}">Post New Topic</a></li>
	<li><a href="{link "board","topic", $topic.id}">Topic '{$topic.title}'</a></li>
	<li class="current"><a href="{linkme} ">Post Reply</a></li>
</ul>

<div class="block-border">

<form action="{linkme}" method="post" name="board.newreply">
<input type="hidden" name="form_submit" value="1" />

<div class="block">

<h2>Post A Reply</h2>

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}

	<label for="body">Body</label>
	<textarea class="text" name="body" id="body">{$post.body}</textarea>


</div>

<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Post Reply" />
</div>

</form>

</div>
