

<ul id="board-tabs">
	<li><a href="{link "board"}">Board Index</a></li>
	<li class="current"><a href="{linkme} ">Post New Topic</a></li>
</ul>

<div class="block-border">

<form action="{linkme}" method="post" name="board.newtopic">
<input type="hidden" name="form_submit" value="1" />

<div class="block">

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}


	<label for="title">Title</label>
	<input size="64" class="text" type="text" name="title" id="title" value="{$post.title}" />

	<label for="body">Body</label>
	<textarea class="text" name="body" id="body">{$post.body}</textarea>


</div>

<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Post New Topic" />
</div>

</form>

</div>
