
<form action="{linkme}" method="post" name="blog.insert">
	<input type="hidden" name="form_submit" value="1" />

<div class="block-border"><div class="block">
<h2>Edit '{$article.link}'</h2>

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}

	<label for="title">Title</label>
	<input size="64" class="text" type="text" name="title" id="title" value="{$post.title}" />

	<label for="search_title">Search Title</label>
	<input size="64" class="text" type="text" name="search_title" id="title" value="{$post.search_title}" /> (optional)

	<br /><br />

	<label for="body">Body</label>
	<textarea class="text" name="body" id="body">{$post.body}</textarea>
</div>

<div class="block-footer">
	<button class="button important" type="button" style="float: right" onClick="if (confirm('Are you sure you want to delete this post?')) window.location = '{link "blog","delete",$article.id }'">Delete Post</button>
	<input class="button" type="submit" name="submit" value="Save" />
</div>

</div>

</form>

