

<div class="floating" style="width: 488px;">
	<div class="block" style="float: right; width: 200px;">
		<h2>Archive</h2>
		<p>
		{foreach $m in $months}
			<a href="{link "blog", "list", $m.search}">{$m.ym} ({$m.num})</a><br />
		{end}
			<br /><a href="{link "blog", "list"}">View All</a><br />
		</p>
		<hr />
		<h2>About</h2>
		<p>Write some text here and then make it a little
		bit longer so there is something to read.</p>
		<hr />
		<h2>Links</h2>
		<p>
			<a href="http://leafo.net/projects/">Projects</a> <br />
			<a href="#">Second</a> <br />
			<a href="#">Third</a> <br />
		</p>
	</div>
</div>

<!-- start blog entries -->
{foreach $article in $entries}
<div class="blog-entry">
	<div class="column">

	<div class="body" style="margin-right: 220px; position: relative;z-index: 1000;">

		<div class="header">
			{if $me.group_id == 1}
				<div class="blog-tools">
					<a href="{link "blog","edit",$article.id}">Edit</a>
				</div>
			{end}

			<h2 class="title">{$article.link}</h2> 
			<div class="sub"><span class="blog-date">{$article.formatted_date}</span><br /> 
				by {$article.author.link}, filed under ...</div>
		</div>

		<div class="content">
			{$article.body}
		</div>

		<div class="footer"><div class="inner">
			<div class="text"> tags: {implode ", ", $article.tags}</div>
		</div></div>

	</div>


	</div>
</div>


{end}
