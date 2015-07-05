{if $side_tags}
<div style="float: right; width: 200px;">
	<h2>All Tags</h2>
	<p>
	{foreach $t in $side_tags}
		<a href="{link "blog", "list", $t.search}">{$t.tag} ({$t.num})</a><br />
	{end}
	</p>
</div>

<div style="width:714px;">
{end}




<div class='block-border'><div class='block'>
<h2>{$title}</h2>

<br />

{foreach $a in $articles}
	<div class="{alternate "odd", ""}" style="padding: 5px 3px 5px 3px;">
		{$a.link} by {$a.author.link} on {$a.formatted_date}
	</div>
{end}
</div></div>



{if $side_tags}
	</div>
{end}


