
<form action="{linkme}" method="post" name="login">
  <input type="hidden" name="form_submit" value="1" />

<div class="block-border" style="width: 200px;"><div class="block">
<h2>Login</h2>

	{if $errors}
	<div class="error"><strong>Error</strong><br />
	{foreach $e in $errors}
		{$e}<br />
	{end}
	</div>
	{end}
	
	<label for="user">Username</label>
	<input class="text" type="text" name="name" id="user" value="{$post.name}" />
	
	<label for="pass">Password</label>
	<input class="text" type="password" name="password" id="pass" value="{$post.password}" />

</div>

<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Login" />
</div>

</div>
<br />

</form>

