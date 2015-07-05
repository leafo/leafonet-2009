<ul id="board-tabs">
	<li><a href="{link "board"}">Board Index</a></li>
	<li><a href="{link "board","topic", $reply.topic_id}">Topic '{$reply.topic_title}'</a></li>
	<li class="current"><a href="{linkme} ">Edit {if $is_topic}Topic{else}Post{end}</a></li>
</ul>

<div class="block-border">

<form action="{linkme}" method="post" name="board.newreply">
<input type="hidden" name="form_submit" value="1" />

<div class="block">

<h2>Edit {if $is_topic}Topic{else}Post{end}</h2>

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}

	{if $is_topic}
	<label for="title">Title</label>
	<input size="64" class="text" type="text" name="title" id="title" value="{$post.title}" />
	{end}

	<label for="body">Body</label>
	<textarea class="text" name="body" id="body">{$post.body}</textarea>


</div>

<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Save" />
</div>

</form>

</div>
