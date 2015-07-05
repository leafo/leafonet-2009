<?php

/**
 * Single pass template compiler
 */
class Compiler 
{
    private $start_delim	= '{';
    private $end_delim		= '}';		

    private $line;
    private $buffer;
    private $compiled;

    public function __construct($fname)
    {
        $this->line = 1;
        $this->buffer = file_get_contents($fname);
        $this->compiled = '';
    }


	function compile() 
	{
        return $this->compile_block();
    }

    /**
     * Consume one block
     * Everything in the block is compiled, then returned
     * A block ends on either the end tag (which is left unparsed at head of buffer)
     * or when the buffer is empty
     */
    private function compile_block($end_tags = array('end'))
    {
        $end = $EOF;
        $compiled = $this->consume_toNextTag();
        while ($this->buffer != '') {
            // echo '<pre style="background-color: #FFEFEF;">'.$this->buffer.'</pre>';
            // Variable access
            if ($this->buffer{0} == '$') {
                $compiled .= $this->compile_variable($this->consume_variable());
            } else { // some tag
                $next = $this->peek_keyword();
                if ($end_tags && in_array($next, $end_tags)) {
                    break; // Hit end
                }

                $tag = $this->consume_keyword();
                switch ($tag) {
                case 'if':
					$compiled .= $this->compile_if($this->consume_if());
                    break;
				case 'foreach':
					$compiled .= $this->compile_foreach($this->consume_foreach());
					break;
				case 'capture':
					$compiled .= $this->compile_capture($this->consume_capture()); 
					break;
				case 'literal':
					$compiled .= $this->consume_literal_tag();
					break;
				case 'append':
					$compiled .= $this->compile_append($this->consume_append());
					break;
				case 'set':
					$compiled .= $this->compile_set($this->consume_append());
					break;

                default: // unknown tag, must be a funcall
                    $compiled .= $this->compile_funcall($tag, $this->consume_args());
                }
            }
            $this->consume_end();
            $compiled .= $this->consume_toNextTag();
        }

        return $compiled;
    } 

    /**
     * Advance the buffer n characters
     */
    private function advance($n) 
    {
        $this->buffer = substr($this->buffer, $n);
    }

    private function code($str)
    {
         return '<?php '.$str.' ?>';
    }

    //
    // Compile methods take in data about what part of the template language they
    // are compiling, then produce the php code for it and write it to the compiled
    // buffer
    //

    /**
     * print a variable out
     */
    private function compile_variable($var) 
    {
        return $this->code('echo '.$var);
    }


    private function compile_funcall($func, $args)
    {
        return $this->code('$this->callFunction("'.$func.'", array('.
            implode(',', $args).'))');
    }

	private function compile_if($st) 
	{
		return $this->code('if ('.$st['cond'].') {').$st['true'].
			($st['false'] ? (is_array($st['false']) ? 
				$this->code('} else').$this->compile_if($st['false']) :
				$this->code('} else {').$st['false'].$this->code('}')) : $this->code('}'));
    }

	private function compile_foreach($st) 
	{
		return $this->code('if ('.$st['from'].' instanceof ArOb) foreach('.$st['from'].'->array as '.
			($st['key'] ? $st['key'].'=>'.$st['to'] : $st['to'] ).') {').
			$st['block'].$this->code('}');
		
	}

	private function compile_capture($cap) 
	{
		// cap has a var name and the string it has captured
		return $this->code('ob_start();').$cap['block'].
			$this->code($cap['to'].' = ob_get_clean();');
	}

	private function compile_append($cap)
	{
		// return $this->code($cap['to'].' = "'.$cap['value'].'"');
		return $this->code($cap['to'].'->array[] = "'.$cap['value'].'"');
	}

	private function compile_set($cap)
	{
		return $this->code($cap['to'].' = "'.$cap['value'].'"');
	}

    //
    // Consume methods read from front of buffer to see if it is of a specific format
    // If the data is not, an exception is thrown
    // If it is, then the relevant extracted information is returned
    //

    /**
     * Consume all plain text up to next tag
     */
    private function consume_toNextTag()
    {
        if (!preg_match('/[^'.preg_quote($this->start_delim).']*/',$this->buffer, $matches))
            throw new Exception('Failed to consume.. ? on line '.$this->line.'.'); 

        $this->line += substr_count($matches[0], "\n");
        $this->advance(strlen($matches[0])+1);
        return $matches[0];
    }

    /**
     * Consume the end delimiter
     */
    private function consume_end()
    {
        if (!preg_match('/^'.preg_quote($this->end_delim).'/', $this->buffer, $matches)) {
            $found = substr($this->buffer, 0, 5);
            throw new Exception('Failed to find end delmiter, got: '.htmlentities($found));
        }

        $this->advance(strlen($matches[0]));
        return $this->end_delim;
    }

    /**
     * Consume a literal set of characters and any following whitespace
     */
    private function consume_literal($str)
    {
        if (!preg_match('/^('.preg_quote($str).')\s*/', $this->buffer, $matches))
            throw new Exception('Failed to parse string ('.$str.') on line '.$this->line.'.');

        $this->advance(strlen($matches[0]));
        return $matches[1];
    }

    /** 
     * Consume a number from head of buffer 
     */
	private function consume_number() 
	{
        if (!preg_match('/^(-?[0-9]+(\.[0-9]+)?)\s*/',$this->buffer,$matches)) 
            throw new Exception('Failed to consume number');
        $this->advance(strlen($matches[0]));
        return $matches[1];
    }

    /**
     * Consume a double quoted string
     */
    private function consume_string()
    {
        if (!preg_match('/^"([^"]*)"\s*/', $this->buffer, $matches))
            throw new Exception('Failed to consume string');

        $this->advance(strlen($matches[0]));
        return $matches[1];
    }

    /**
     * Consume next keyword from buffer and return it
     * A keyword is a block of text starting with a character or underscore
     * with no spaces in it
     */
    private function consume_keyword()
    {
        if (!preg_match('/^([A-Za-z_][\w_]*)\s*/', $this->buffer, $matches))
            throw new Exception('Failed to parse keyword on line '.$this->line.'.');

        $this->advance(strlen($matches[0]));
        return $matches[1];
    }

    /**
     * peek into the buffer to see next keyword
     */
	private function peek_keyword() 
	{
        if (!preg_match('/^([\w_][\w\d_]*)\s*/', $this->buffer, $matches)) 
            throw new Exception('Failed to peek keyword, found: '.$this->buffer{0}.'.'); 

        return $matches[1];
    }

	private function consume_variable() 
	{
		$this->consume_literal('$');
		$out = '$this->v__'.$this->consume_keyword();
		// try to see if there is any more of the variable
		while (true) {
			try {
				$this->consume_literal('.');
			} catch (Exception $ex) {
				break; // no more variable to consume
			}
			// a string
			try {
				$out .= '->'.$this->consume_keyword();
				continue;
			} catch (Exception $ex) {}

			// a number
			try {
				$out .= '->__get('.$this->consume_number().')';
			} catch (Exception $ex) {
				// fatal error
				throw new Exception(new Exception('failed parse variable'));
			}
		}

		return $out;
	}

	/**
	 * Consume a single argument
	 * An argument is either:
	 *  - a string
	 *  - a number
	 *  - a variable
	 */
	private function consume_argument() 
	{
		// try a number 
		try {
			return $pre.$this->consume_number();
		} catch (Exception $ex) {}

		// try a variable
		try {
			// see if the variable is being negated
			$pre = '';
			try {
				$this->consume_literal('!');
				$pre = '!';
			} catch (Exception $ex) {}
			return $pre.$this->consume_variable();
		} catch (Exception $ex) {}

		// try a string
		try {
			return '"'.$this->consume_string().'"';
		} catch (Exception $ex) {}

		throw new Exception('Failed to consume argument');
	}

	/**
	 * Consume an argument list, and return the arguemnts in array form
	 */
	private function consume_args() 
	{
		$args = array();
		while (1) {
			//echo '<pre style="background-color: #EFFFEF;">'.$this->buffer.'</pre>';
			try {
				$args[] = $this->consume_argument();
			} catch (Exception $ex) {
				if (count($args) == 0) break;
				throw new Exception('Failed to consume argument list');
			}

			try {
				$this->consume_literal(',');
			} catch (Exception $ex) {
				break; // end of arguments
			}
		}

		return $args;
	}

	/**
	 * Consume an if statement
	 */
	private function consume_if()
	{
		$statement = array();
		// Figure out the condition:
		$statement['cond'] = $this->consume_argument();
		try { // see if there is a condition
			if (!preg_match('/^(==|!=|<|>|>=|<=|\|\|)\s*/', $this->buffer, $matches))
				throw new Exception('Failed to consume string');

			$this->advance(strlen($matches[0]));
			$statement['cond'] .= ' '.$matches[1].' '.$this->consume_argument();

		} catch (Exception $ex) { 
		}
		$this->consume_end();

		$statement['true'] = $this->compile_block(array('end', 'else', 'elseif'));

		$key = $this->consume_keyword();
		switch ($key) {
		case 'end':
			$false = '';
			break;
		case 'else':
			$this->consume_end();
			$statement['false'] = $this->compile_block(array('end'));
			$this->consume_literal('end');
			break;
		case 'elseif':
			$statement['false'] = $this->consume_if();
			break;
		default:
			throw new Exception('Invalid conditional function: '.$key);
		}

		return $statement;
	}

	private function consume_foreach() 
	{
		$st = array();
		
		// get the destination variables
		// if we get the wrong type of argument php will throw error
		$args = $this->consume_args();
		if (count($args) == 1) {
			$st['to'] = $args[0];
		} else if (count($args) == 2) {
			$st['to'] = $args[1];
			$st['key'] = $args[0];
		} else {
			throw new Exception('incorrect number of arguments for foreach');
		}

		$this->consume_literal('in');
		$st['from'] = $this->consume_variable();
		$this->consume_end();

		$st['block'] = $this->compile_block(array('end'));

		$this->consume_literal('end');
		return $st;
	}

	private function consume_capture()
	{
		$cap = array();
		$cap['to'] = $this->consume_variable();
		$this->consume_end();

		$cap['block'] = $this->compile_block(array('end'));

		$this->consume_literal('end');
		return $cap;
	}

	private function consume_literal_tag()
	{
		$this->consume_end();

		// grab until end, non greedy
		if (!preg_match('/^(.*)'.preg_quote('{end').'/Us', $this->buffer, $matches)) 
			throw new Exception('Failed to match literal block');

		$this->advance(strlen($matches[0]));
		return $matches[1];
	}

	private function consume_append()
	{
		$cap = array();
		$cap['to'] = $this->consume_variable();
		$cap['value'] = $this->consume_string();
		return $cap;
	}
}

?>
