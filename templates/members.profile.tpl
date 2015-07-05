
<div class='block-border'><div class='block'>
<h2>Profile For '{$member.link}'</h2>

{$member.name} has written {$member.topics} topics and {$member.posts} posts.<br />
Member for {timestring $member.member_for}. (registered on {$member.join_date})<br />
Last active on {$member.last_active}. 
{if !$member.is_online}(since {timestring $member.since_active})
{else}<b>Online now</b>.{end} <br />

<br />

{if 0}
<h2>Blog Posts</h2>
{foreach $p in $posts}
	<div class="{alternate "odd", ""}" style="padding: 5px 3px 5px 3px;">
	{$p.link} on {$p.formatted_date}
	</div>
{end}
{end}

</div>
</div>
