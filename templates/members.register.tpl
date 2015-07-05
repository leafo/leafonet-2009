
<form action="{linkme}" method="post" name="register">
  <input type="hidden" name="form_submit" value="1" />

<div style="float: right; width: 400px; display: none;">
	<h2>Terms</h2>
	<p>Don't be dumb idiot</p>
</div>


<div class="block-border" style="width: 200px;"><div class="block">
<h2>Register</h2>

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

	<label for="passr">Repeat Password</label>
	<input class="text" type="password" name="repeat_password" id="passr" value="{$post.password}" />

	<label for="email">Email Address</label>
	<input class="text" type="text" name="email" id="email" value="{$post.password}" />

	<br />
	<br />
	<label for="captcha">Captcha</label>
	<p><img src="{$base_url}captcha.php" /></p>

	<input class="text" type="text" name="captcha" id="captcha" value="{$post.password}" />
	
</div>

<div class="block-footer">
	<input class="button" type="submit" name="submit" value="Register" />
</div>

</div>



<br />

</form>
