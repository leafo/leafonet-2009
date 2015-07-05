
<div class='block-border'><div class='block'>
<h2>Members</h2>

<!--
<div class="error">This is an error?</div>
<div class="highlight">This is an error?</div>

<br />
-->

{foreach $m in $members}
	<div class="{alternate "odd", ""}" style="padding: 5px 3px 5px 3px;">
	<a href="{link "members", "profile", $m.id}">{$m.name}</a>
	</div>
{end}
</div></div>

