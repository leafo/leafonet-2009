<?php

// todo truncate long urls that are autoparsed
// (or all long urls) so user doesn't seee all the crap

class Preparse
{
	public function parse($text)
	{
		return preg_replace(
			array(	
				// FIX THIS!!! doesn't need to steal newlines
				'/(?<!\[url]|\[url=|\[quote=) *(http|ftp):\/\/([^\s[\]]+)/',
			),
			array( 
				'[url]$1://$2[/url]',
			),
			$text);
	}

}

// a tag => array('tagname', 'arguments', 'content', ... )

class BBCodeParser
{
	protected $buffer; 
	protected $stack; // the tag stack
	protected $document; // the parsed document structure

	// tag syntax:
	// 'tagname' => array('pre', 'post', ... )
	public $tags = array(
		'b' => array('<b>', '</b>'),
		'i' => array('<em>', '</em>'),
		'strike' => array('<del>', '</del>'),
		'quote' => array(
			'<blockquote>', '</blockquote>',
			'arg_left' => '<blockquote><div class="author">%s said</div>',
			'block' => true,
		),

		'color' => array(
			'<span style="color: %s;">', '</span>', 
			'validate_arg' => '/^[a-zA-Z0-9#]+$/',
		),

		'url' => array(
			'<a href="%s">', '</a>',  // %s is the argument
			'default_argument' => '%s', // %s is the contents
			'bottom' => true, // no more tags can be inside this one
			// 'validate' => '/^(?!javascript).*$/', // validate the contents
			'validate' => array(self, 'validate_url'), // a function
		),

		'code' => array(
			'<pre class="code">', '</pre>',
			'bottome' => true,
			'block' => true,
		),

		'list' => array( 
			'<ul style="border: 1px solid red;">', '</ul>',
			'close_hanging' => true, // close all hanging tags when it is closed
			'block' => true,
		),

		'*' => array(
			'<li>', '</li>',
			'parent' => 'list', // can only be created inside parent!
			'self_closing' => true,
		),
	);

	// render tag/text list as html
	private function renderDocument($document, $toBBCode = false)
	{
		$trim = true;

		foreach ($document as &$node)
		{
			if (is_string($node)) {
				if (!$toBBCode && $trim) {
					// remove the first new line	
					if ($node{0} == "\n") $advance = 1;
					else if ($node{0} == "\r" && 
						$node{1} == "\n") $advance = 2;
					else $advance = 0;

					echo substr($node, $advance);
					$trim = false;
				} else {
					echo $node;
				}

			} else if (is_array($node)) { // a tag
				$rendered = false;

				$tag_name = array_shift($node);
				$tag_arg = array_shift($node);
				$tagdef = $this->tags[$tag_name];

				// using default argument?
				if (!$toBBCode && !$tag_arg && $tagdef['default_argument']) {
					ob_start();
					$this->renderDocument($node, $toBBCode);
					$rendered = ob_get_clean();

					$tag_arg = sprintf($tagdef['default_argument'], $rendered);
				}

				if ($toBBCode || !$tagdef)
					echo '['.$tag_name.($tag_arg ? '='.$tag_arg : '').']';
				else
				{
					if ($tag_arg && $tagdef['arg_left'])
						echo sprintf($tagdef['arg_left'], $tag_arg);
					else
						echo sprintf($tagdef[0], $tag_arg);
				}

				if ($rendered) echo $rendered;
				else $this->renderDocument($node, $toBBCode);

				if ($toBBCode || !$tagdef) { 
					if (!$tagdef['self_closing']) echo '[/'.$tag_name.']';
				} else
					echo sprintf($tagdef[1], $tag_arg);

				$trim = $tagdef['block'];
			} 

			$trim = false;

		}		
	}


	// render the entire document
	public function render($bbcode = false)
	{
		ob_start();
		$this->renderDocument($this->document, $bbcode);

		if ($bbcode)
			return ob_get_clean();
		else
			return str_replace(array("\n", "\r\n"), array('<br />', '<br />'), ob_get_clean());
	}

	public function parse($text, $preparse = true)
	{
		$this->document = array();

		if ($preparse) {
			$pre = new Preparse();
			$this->buffer = $pre->parse(htmlentities($text));
		} else
			$this->buffer = htmlentities($text);

		// while we have text
		while (false !== ($stub = $this->consumeUntilTag()))
		{
			if ($stub) $this->putData($stub);
			$this->consumeTag();
		}

		if (trim($this->buffer))
			$this->putData($this->buffer);
		$this->buffer = null;

		// close any tags that are still open
		while (count($this->stack))
			$this->popTag();

		// trace($this->document);

		return true;
	}

	public function load($doc)
	{
		return $this->document = unserialize($doc);
	}

	public function save()
	{
		return serialize($this->document);
	}

	// consume the buffer until tag
	// return 
	// the text that was consumed
	// false when out of text
	protected function consumeUntilTag()
	{
		$out = preg_match("/^(.*?\[)[^\]\n]+]/s", $this->buffer, $matches);
		if (!$out) return false; // end of file

		$this->advance(strlen($matches[1]));
		// trace($this->buffer);

		return substr($matches[1], 0, -1);
	}

	// consume a tag and parse what it is
	protected function consumeTag()
	{
		$out = preg_match('/^(.*?)]/', $this->buffer, $matches);
		if (!$out) throw new Exception("BBCode: Can't consume tag");

		$this->advance(strlen($matches[0]));

		$current = $this->currentTag();
		$current_def = $current ? $this->tags[$current] : array();

		$tag = trim($matches[1]);

		if ($tag{0} == '/') {
			$closing = true;
			$tag = explode('=',substr($tag, 1));
		} else {
			$tag = explode('=',$tag);
		}

		$tagdef = $this->tags[$tag[0]];

		// handle the closing tag (and non existant closing)
		if ($closing) 
		{
			if ($current == $tag[0])
		   	{
				$this->popTag();
			} else if ($tagdef['close_hanging'] && $this->inside($tag[0]))
			{
				while ($this->popTag() != $tag[0]) {
					// ~
				}

			} else {  // just ignore it 
				$this->putData('['.$matches[0]);	
			}


			return;
		} 

		// handle a self closing tag (list)
		if ($tagdef['self_closing'] && $current == $tag[0] )
		{
			$this->popTag();
		}

		// check for any conditions where we don't want to render new tag
		if (!($tagdef = $this->tags[trim($tag[0])]) // tag doesn't exist
			|| ($tagdef['validate_arg'] &&  // args aren't valid
				!preg_match($tagdef['validate_arg'], $tag[1]))
			|| $current_def['bottom']  // we are in a bottom level tag
		)
		{
			$this->putData('['.$matches[0]);	
			return;
		}

		$this->pushTag(trim($tag[0]), $tag[1]);
	}

	// push a new tag on the tag stack
	protected function pushTag($name, $argument) 
	{
		$this->stack[] = array($name, $argument);
	}

	// close the tag on the head of the stack and put it in the document
	// returns the name of the tag that was popped
	protected function popTag() 
	{
		if (!count($this->stack)) return;
		$tag = array_pop($this->stack);
		
		// need to validate the args
		if (is_callable($this->tags[$tag[0]]['validate'])) {
			ob_start();
			$partial =  array_slice($tag, 2);
			$this->renderDocument($partial);
			$out = trim(ob_get_clean());

			// try to validate tag if there is validate function
			if (!call_user_func_array($this->tags[$tag[0]]['validate'],
				array(&$tag, $out)))
			{
				// dump the tag
				// trace($out.' failed');
				$this->putData('['.$tag[0].($tag[1] ? '='.$tag[1] : '').']');
				while (!empty($partial))
					$this->putData(array_shift($partial));
				$this->putData('[/'.$tag[0].']');
				return $tag[0];

			}
		}

		$this->putData($tag);

		return $tag[0];
	}

	// returns true on success
	protected function validate_url(&$tag, $out)
	{
		// either has an argument or it doesn't
		$url = trim($tag[1] ? $tag[1] : $out);
		if ('javascript:' == substr($url, 0, 11))
			return false;

		// add a http to argument if there isn't one
		if ($tag[1]) {
			if (!preg_match('/^(\w+:\/\/)/', $tag[1]))
				$tag[1] = 'http://'.$tag[1];
		} else {
			// no argument, append  http to body
			if (is_string($tag[2]) &&	
				!preg_match('/^(\w+:\/\/)/', $tag[2])) 
			{
				$tag[2] = 'http://'.$tag[2];
			}
		}


		return true;
	}

	// put data on the current tag, or root document
	protected function putData($what)
	{
		if (count($this->stack)) {
			$current =& $this->stack[count($this->stack)-1];

			// don't create new node if we are putting more text on text
			// 2 is the 'stride' of a tag's meta data
			if (is_string($what) && is_string(end($current)) && count($current) > 2)
				$current[count($current) -1] .= $what;
			else 
				$this->stack[count($this->stack) -1][] = $what; 

		} else {
			// apend to the end of a string block
			if (is_string($what) && is_string(end($this->document)))
				$this->document[count($this->document) -1] .= $what;
			else 
				$this->document[] = $what; 
		}
	}

	// advance the buffer n characters
	protected function advance($n)
	{
		$this->buffer = substr($this->buffer, $n);
	}

	protected function currentTag()
	{
		return count($this->stack) ? 
			$this->stack[count($this->stack)-1][0] : null;
	}

	// are we current inside $tag
	protected function inside($tag)
	{
		foreach ($this->stack as $s)
			if ($s[0] == $tag) return true;
		return false;
	}
}

?>