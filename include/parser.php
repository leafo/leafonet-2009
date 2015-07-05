<?php

class WikiParser
{
	public static $NO_STATE = 0;
	public static $LIST_STATE = 1;
	public static $PRE_STATE = 2;

	private $state = 0;
	private $currentTag = null;	
	private $buffer = array();

	private function render_header($h)
	{
		$count = strlen($h[1]);
		if ($count == 1) return $h[0];
		// $count--; // enable h1
		return '<h'.$count.'>'.trim($h[2]).'</h'.$count.'>';
	}

	private function render_decoration($d)
	{
		$styles = array(
			2 => 'b',
			3 => 'i',
		);

		$tag = $styles[strlen($d[1])];
		if (!$tag) $tag = 'b';

		return '<'.$tag.'>'.trim($d[2]).'</'.$tag.'>';
	}

	private function render_paragraph($p)
	{
		return '<p>'.$p[0].'</p>';
	}

	private function render_link($l)
	{
		if ($l[2])
			$title = $l[2];
		else 
			$title = $l[1];

		return '<a href="http://'.$l[1].'">'.trim($title).'</a>';
	}

	private function render_internal_link($l)
	{
		$args = explode('|', $l[1]);

		$url = $args[1] ? $args[1] : $args[0];

		return '<a href="'.r::$link->to('spage', $url).'">'.$args[0].'</a>';
		//return '<a href="?page='.$url.'">'.$args[0].'</a>';
		
	}

	private function render_tag($t)
	{
		$tag = strtolower($t[1]);
		$args = $t[3];
		switch ($tag) {
		case 'youtube':
			return '<object width="425" height="344"><param name="movie" value="http://www.youtube.com/v/'.$args.'&hl=en&fs=1"></param><param name="allowFullScreen" value="true"></param><embed src="http://www.youtube.com/v/'.$args.'&hl=en&fs=1" type="application/x-shockwave-flash" allowfullscreen="true" width="425" height="344"></embed></object>';
			break;
		case 'image':
			$args = explode('|', $args);

			if ($args[1]) // got a float property
				$style = 'style="float: '.$args[1].'" ';

			if ($args[2]) // got width 
				$style .= 'width="'.$args[2].'" '; 

			return '<img alt="" '.$style.'src="'.$args[0].'" />';

			break;
		case 'br':
			return '<br />';
		default:
			return $t[0];
		}
	}

	private function render_block_tag($r)
	{
		// if it is a registered tag, then consume it
		switch (strtolower($r[1])) {
		case "html": // don't parse any syntax
			$pre = '';
			$post = '';
		case "code":
			$pre = '<pre class="code">';
			$post = '</pre>';
			break;	
		default:
			return $r[0];
		}

		// consume to the end of the tag
		ob_start();
		while (!is_null($line = $this->nextLine())) {
			if (trim($line) == '</'.$r[1].'>') break; // end of tag

			echo $line."\n";
		}

		return $pre.ob_get_clean().$post;
	}

	function render_strikethrough($t)
	{
		return '<del>'.$t[2].'</del>';
	}


	/** 
	 * parses the line, and echos the formatted version
	 */
	private function parseLine($line)
	{
		// paragraphs will capture lines that start with decorations
		$callbacks = array(
			'paragraph' => '^[A-Za-z0-9\'\"].*$',
			'decoration' => '(\'\'+)(.+?)\1',
			'strikethrough' => '(\~\~)(.+?)\1',
			'header' => '^(=+)(.+)\1$',
			'tag' => '\[\[([A-Za-z]+)(\:([^\]]*))?\]\]',
			'link' => '\[http:\/\/([^\s\]]+)([^\]]+)?\]',
			'internal_link' => '\[\[([\w|\#_ ]+)\]\]',
			'block_tag' => '^\<([A-Za-z]+)\>[ ]*$',
		);

		
		// $line = trim($line);
		if ($line === '') {
			$this->setState(WikiParser::$NO_STATE);
			return;
		}

		foreach ($callbacks as $c=>$p) {
			$parsed = true;
			$line = preg_replace_callback(
				'/'.$p.'/',
				array($this, 'render_'.$c), rtrim($line));
		}

		// see if the state changes
		if ($line{0} == '*') {
			// are we working with a list
			$this->setState(WikiParser::$LIST_STATE);
			$line = '<li>'.substr($line, 1).'</li>';
		} elseif ($line{0} == ' ') {
			// are we working with a pre block
			$this->setState(WikiParser::$PRE_STATE);
		} else {
			$this->setState(WikiParser::$NO_STATE);
		}

		$line = trim($line);
		if ($line == '') return;

		echo $line."\n";
	}

	// return a line from the head of the buffer
	private function nextLine()
	{
		return array_shift($this->buffer);
	}

	private function setState($s)
	{
		if ($s == $this->state) return;

		// exit state events
		if ($this->state == WikiParser::$LIST_STATE) 
			echo "</ul>\n";
		else if ($this->state == WikiParser::$PRE_STATE)
			echo "</pre>\n";

		// enter state conditions
		if ($s == WikiParser::$LIST_STATE)
			echo "<ul>\n";
		else if ($s == WikiParser::$PRE_STATE)
			echo "<pre class='code'>\n";


		$this->state = $s;
	}

	public function parse($data)
	{
		$this->state = 0;
		$this->currentTag = null;	

		$this->buffer = explode("\n", $data);

		ob_start();

		while (!is_null($line = $this->nextLine())) {
			$this->parseLine($line);
		}

		$this->setState(WikiParser::$NO_STATE); // close any open tags

		return ob_get_clean();
	}

}


?>