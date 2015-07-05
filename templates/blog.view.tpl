

{if $status}
<div style="text-align: center"><span class="error">{$status}</span></div>
{end}


<div class="blog-entry">
	<div class="body column">
		<div class="header">
			{if $me.group_id == 1}
				<div class="blog-tools">
					<a href="{link "blog","edit",$article.id}">Edit</a>
				</div>
			{end}

			<h2 class="title">{$article.link}</h2> 
				<div class="sub"><span class="blog-date">{$article.formatted_date}</span><br /> 
				by {$article.author.link}, filed under {implode ", ", $article.tags}  </div>
			<!-- <div class="sub">by {$article.author.link} {if $comment}| <a href="">6 comments</a>{end}</div>-->
		</div>

		<div class="content">
		{$article.body}
		</div>

		<div class="footer"><div class="inner">
			<div class="text"> tags: {implode ", ", $article.tags}</div>
		</div></div>
	</div>
</div>

<div class="blog-comments">

<form action="{linkme}#post-comment" method="post" name="blog.comment">
	<input type="hidden" name="form_submit" value="1" />

<div class="block-border"><div class="block">

<a name="comments"></a>
<h2>Comments</h2>

	{if $comments.acount}

	<ol class="list">
	{foreach $c in $comments}
		<li class="{alternate "odd","even"}">
			<div class="sub"><span class="author">{$c.author.link}</span> said on {$c.date}:</div>
			<div class="body">{$c.body}</div>
		</li>
	{end}
	</ol>
	{else}
	<p>There are no comments yet.</p>
	{end}

	{if $comments_disabled}
		<div class="disabled">Comments are disabled for this post</div>
	{else}
		<a name="post-comment"></a>
		<br />
		<h2>Post Comment</h2>

		{if $errors}
		<div class="error"><strong>Error</strong><br />
		{foreach $e in $errors}
			{$e}<br />
		{end}
		</div>
		{end}


		{if $me.loggedIn}
			<p>Posting as {$me.name}</p>
		{else}

	<label for="name">Name</label>
	<input size="40" class="text" type="name" name="name" id="name" value="{$post.name}" />
	
	<label for="email">Email</label>
	<input size="40" class="text" type="email" name="email" id="email" value="{$post.email}" />

	<label for="website">Website</label>
	<input size="40" class="text" type="website" name="website" id="website" value="{$post.website}" /> (optional)
		{end}

		<textarea style="width: 75%" class="text small" name="body" id="body">{$post.body}</textarea>
	{end}

</div>

{if !$comments_disabled}
<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Post" />
</div>
{end}

</div>

</form>

</div>








