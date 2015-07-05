<table class="pageselect" cellpadding="0" cellspacing="0"><tr>
{foreach $i in $pages}
	<td class="{$i.class}">{if $i.link}<a href="{$i.link}">{end}{$i.value}{if $i.link}</a>{end}</td>
{end}
<tr></table>
