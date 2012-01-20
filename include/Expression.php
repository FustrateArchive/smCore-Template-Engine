<?php

namespace smCore\TemplateEngine;

class Expression
{
	protected $data = null;
	protected $data_len = 0;
	protected $data_pos = 0;
	protected $token = null;

	// Our built var/lang/etc. expression string
	protected $expr = '';

	// If it's not raw, we'll htmlspecialchars it
	protected $escape = false;
	protected $filters = array();

	protected static $lang_function = 'lang';
	protected static $filter_function = 'smCore\TemplateEngine\Filters::filter';

	public static function setLangFunction($func)
	{
		self::$lang_function = $func;
	}

	public static function setFilterFunction($func)
	{
		self::$filter_function = $func;
	}

	public function __construct($data, Token $token, $escape = false)
	{
		// Filters can have whitespace before them, so remove it. Makes reading easier when they're not there.
		$this->data = preg_replace('~\s+%~', '%', $data);
		$this->data_len = strlen($this->data);
		$this->token = $token;
		$this->escape = $escape !== false;
	}

	public function parseInterpolated()
	{
		// An empty string, let's short-circuit this common case.
		if ($this->data_len === 0)
			$this->expr .= '\'\'';

		while ($this->data_pos < $this->data_len)
		{
			if (!empty($this->expr))
				$this->expr .= ' . ';

			switch ($this->data[$this->data_pos])
			{
				case '{':
					$this->expr .= $this->readReference();
					break;

				default:
					$this->expr .= $this->readStringInterpolated();
			}
		}

		$this->validate();

		return $this->getCode();
	}

	public function parseVariable($allow_lang = true)
	{
		$this->eatWhite();

		if ($this->data_len === 0 || $this->data[$this->data_pos] !== '{')
			$this->toss('expression_expected_var');

		$this->expr .= $this->readReference($allow_lang);

		$this->eatWhite();

		if ($this->data_pos < $this->data_len)
			$this->toss('expression_expected_var_only');

		$this->validate();

		return $this->getCode();
	}

	public function parseNormal($accept_raw = false)
	{
		// An empty string, let's short-circuit this common case.
		if ($this->data_len === 0)
			$this->toss('expression_empty');

		while ($this->data_pos < $this->data_len)
		{
			switch ($this->data[$this->data_pos])
			{
				case '{':
					$this->expr .= $this->readReference();
					break;

				default:
					// !!! Maybe do more here?
					$this->expr .= $this->readRaw();
			}
		}

		$this->validate();

		if (!$this->escape && $accept_raw)
			return array($this->getCode(), true);
		else
			return $this->getCode();
	}

	public function validate()
	{
		// We'll get a "[] can't be used for reading" fatal error.
		if (preg_match('~\[\s+\]$~', $this->expr))
			$this->toss('expression_empty_brackets');

		// A dead code sandbox prevents this from causing any trouble.
		$attempt = @eval('if(0){return (' . $this->expr . ');}');

		if ($attempt === false)
			$this->toss('expression_validation_error');
	}

	protected function getCode()
	{
		// The raw filter is a special case.
		if (array_key_exists('raw', $this->filters))
		{
			$this->escape = false;
			unset($this->filters['raw']);
		}

		// Add the filters, if there are any
		if (!empty($this->filters))
		{
			$filters = array();

			foreach ($this->filters as $name => $params)
				$filters[] = '\'' . $name . '\' => array(' . implode(',', $params) . ')';

			$this->expr = self::$filter_function . '(' . $this->expr . ', array(' . implode(',', $filters) . '))';
		}

		if ($this->escape)
			return 'htmlspecialchars(' . $this->expr . ', ENT_COMPAT, "UTF-8")';
		else
			return $this->expr;
	}

	protected function readStringInterpolated()
	{
		$pos = $this->firstPosOf('{');

		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen, unless we were called wrong.
		if ($pos === $this->data_pos)
			$this->toss('expression_unknown_error');

		return $this->readString($pos);
	}

	protected function readReference($allow_lang = true)
	{
		// Expect to be on a {.
		$this->data_pos++;

		$pos = $this->firstPosOf('}');

		if ($pos === false)
			$this->toss('expression_braces_unmatched');

		$data = '';

		$c = $this->data[$this->data_pos];

		if ($c === '$')
		{
			$data = $this->readVarRef();

			if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
			{
				if ($this->data[$this->data_pos] === ']')
					$this->toss('expression_brackets_unmatched');
				else
					$this->toss('expression_unknown_error');
			}
		}
		else if ($c === '#')
		{
			if ($allow_lang)
			{
				$data = $this->readLangRef();

				if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
					$this->toss('expression_unknown_error');
			}
			else
				$this->toss('expression_expected_ref_nolang');
		}
		else
		{
			// This could be a static.  If it is, we have a :: later on.
			$next = $this->firstPosOf('::');

			if ($next !== false && $next < $pos)
			{
				$data = $this->eatUntil($next) . $this->eatUntil($next + 2) . $this->readVarRef();

				if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
				{
					if ($this->data[$this->data_pos] === ']')
						$this->toss('expression_brackets_unmatched');
					else
						$this->toss('expression_unknown_error');
				}
			}
			else
			{
				if ($allow_lang)
					$this->toss('expression_expected_ref');
				else
					$this->toss('expression_expected_ref_nolang');
			}
		}

		// Skip over the }.
		$this->data_pos++;

		return $data;
	}

	protected function readVarRef()
	{
		/*	It looks like this: {$xyz.abc[$mno][nilla].$rpg %filter %filter:param}
			Which means:
				x.y.z = x [ y ] [ z ]
				x[y.z] = x [ y [ z ] ] 
				x[y][z] = x [ y ] [ z ]
				x[y[z]] = x [ y [ z ] ]
		
			When we hit a ., the next item is surrounded by brackets.
			When we hit a [, the next item has a [ before it.
			When we hit a ], there is no item, but just a ].
			When we hit a %, we're looking at a filter.
		*/

		$built = '';

		$brackets = 0;

		while ($this->data_pos < $this->data_len)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '->', '}', '%', ':'), 1);

			if ($next === false)
				$next = $this->data_len;

			$c = $this->data[$this->data_pos++];

			if ($c === '$')
			{
				$name = $this->eatUntil($next);

				if ($name === '')
					$this->toss('expression_var_name_empty');

				$built .= '$' . self::makeVarName($name);
			}
			else if ($c === '.')
			{
				$built .= '[';
				$built .= $this->readVarPart($next, true);
				$built .= ']';
			}
			else if ($c === '[')
			{
				$built .= '[';
				$this->eatWhite();
				$built .= $this->readVarPart($next, false);
				$this->eatWhite();

				$brackets++;
			}
			else if ($c === ']')
			{
				// Ah, hit the end, jump out. Must be a nested one.
				if ($brackets <= 0)
				{
					$this->data_pos--;
					break;
				}

				$built .= ']';

				$brackets--;
			}
			else if ($c === '-')
			{
				// When we hit a ->, we increase the data pointer, then find the property.
				$built .= '->';
				$this->data_pos++;
				$built .= $this->eatUntil($next);
			}
			else if ($c === '}')
			{
				// All done - but don't skip it, our caller doesn't expect that.
				$this->data_pos--;
				break;
			}
			else if ($c === '%')
			{
				$name = $this->eatUntil($next);

				if (empty($name))
					$this->toss('expression_filter_no_name');

				$this->filters[$name] = array();
			}
			else if ($c === ':')
			{
				if (empty($this->filters))
					$this->toss('expression_unexpected_semicolon');

				// We're going to be greedy, now that we're pretty much starting a whole new expression.
				$this->filters[end(array_keys($this->filters))][] = $this->readVarPart($next, false, true);
			}
			else
			{
				// A constant, like a class constant: {Class::CONST}.
				// We want to grab the "C", so we take a step back and eat.
				$this->data_pos--;
				$built .= $this->eatUntil($next);
			}
		}

		if ($brackets != 0)
			$this->toss('expression_brackets_unmatched');

		return $built;
	}

	protected function readLangRef()
	{
		/*	It looks like this: {#xyz.abc[$mno][nilla].$rpg %filter %filter:param}
			Which means:
				x.y.z = x [ y ] [ z ]
				x[y.z] = x [ y [ z ] ] 
				x[y][z] = x [ y ] [ z ]
				x[y[z]] = x [ y [ z ] ]
		
			When we hit a ., the next item is surrounded by brackets.
			When we hit a [, the next item has a [ before it.
			When we hit a ], there is no item, but just a ].
			When we hit a %, we're looking at a filter.
		*/

		$key = array();
		$params = array();

		$brackets = 0;

		while ($this->data_pos < $this->data_len)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '}', ':', '%'), 1);

			if ($next === false)
				$next = $this->data_len;

			$c = $this->data[$this->data_pos++];

			if ($c === '#')
			{
				$name = $this->eatUntil($next);

				if ($name === '')
					$this->toss('expression_lang_name_empty');

				$key[] = '\'' . $name . '\'';
			}
			else if ($c === '.')
			{
				$key[] = $this->readVarPart($next, false);
			}
			else if ($c === '[')
			{
				$key[] = $this->readVarPart($next, false);

				$brackets++;
			}
			else if ($c === ']')
			{
				// Ah, hit the end, jump out.  Must be a nested one.
				if ($brackets <= 0)
				{
					$this->data_pos--;
					break;
				}

				$brackets--;
			}
			else if ($c === '}')
			{
				// All done - but don't skip it, our caller doesn't expect that.
				$this->data_pos--;
				break;
			}
			else if ($c === ':')
			{
				// We're going to be greedy, now that we're pretty much starting a whole new expression.
				$value = $this->readVarPart($next, false, true);

				// Sometimes we'll get an array back, so let's flatten it.
				if (is_array($value))
					$value = implode('', $value);

				if (!empty($this->filters))
					$this->filters[end(array_keys($this->filters))][] = $value;
				else
					$params[] = $value;
			}
			else if ($c === '%')
			{
				$name = $this->eatUntil($next);

				if (empty($name))
					$this->toss('filter_no_name');

				$this->filters[$name] = array();
			}
		}

		if ($brackets != 0)
			$this->toss('expression_brackets_unmatched');

		// Assemble and return
		$expr = self::$lang_function . '(array(' . implode(',', $key) . ')';

		if (!empty($params))
			$expr .= ', array(' . implode(',', $params) . ')';

		return $expr . ')';
	}

	protected function readVarPart($end, $require = false, $greedy = false)
	{
		// If we're being greedy, don't stop at indexes.
		if ($greedy)
			$end = $this->firstPosOf(array(':', '%', '}'), 1);

		$c = $this->data[$this->data_pos];

		// If a curly bracket isn't provided,
		if ($c === '$' || $c === '#')
		{
			$expr = substr($this->data, $this->data_pos, $end - $this->data_pos);
			$this->data_pos += strlen($expr);

			return self::variable('{' . $expr . '}', $this->token);

		}
		else if ($c === '{')
		{
			// Create a whole new expression, and make sure we grab everything.
			return self::variable($this->readInnerReference(), $this->token);
		}
		else
		{
			if ($require && $this->data_pos == $end)
				$this->toss('expression_incomplete');

			return $this->readString($end);
		}
	}

	protected function readInnerReference()
	{
		$start = $this->data_pos;
		$brackets = 0;

		while ($this->data_pos < $this->data_len)
		{
			if ($this->data[$this->data_pos] === '}')
				$brackets--;
			else if ($this->data[$this->data_pos] === '{')
				$brackets++;

			$this->data_pos++;

			if ($brackets === 0)
				break;
		}	

		if ($brackets === 0)
			return substr($this->data, $start, $this->data_pos - $start);

		$this->toss('inner_token_unmatched_braces');
	}

	protected function readString($end)
	{
		$value = $this->eatUntil($end);

		// Did we split inside a string literal? Try to find the rest
		if (!empty($value) && ($value[0] === '"' || $value[0] === '\'') && ($value[0] !== substr($value, -1) || strlen($value) === 1))
		{
			$next = $this->firstPosOf(array($value[0]));
			$value = substr($value, 1) . $this->eatUntil($next);

			// Skip over the ending quotation mark.
			$this->data_pos++;
		}

		return '\'' . addcslashes($value, '\\\'') . '\'';
	}

	protected function readRaw()
	{
		$pos = $this->firstPosOf('{');
		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen, unless we were called wrong?
		if ($pos === $this->data_pos)
			$this->toss('expression_unknown_error');

		return $this->eatUntil($pos);
	}

	protected function toss($error)
	{
		$this->token->toss('expression_invalid_meta', $this->data, Exception::format($error, array()));
	}

	protected function eatWhite()
	{
		while ($this->data_pos < $this->data_len)
		{
			$c = ord($this->data[$this->data_pos]);

			// Okay, found whitespace (space, tab, CR, LF, etc.)
			if ($c != 32 && $c != 9 && $c != 10 && $c != 13)
				break;

			$this->data_pos++;
		}
	}

	protected function eatUntil($pos)
	{
		$data = substr($this->data, $this->data_pos, $pos - $this->data_pos);
		$this->data_pos = $pos;

		return $data;
	}

	protected function firstPosOf($find, $offset = 0)
	{
		$least = false;

		// Just look for each and take the lowest.
		$find = (array) $find;
		foreach ($find as $arg)
		{
			$found = strpos($this->data, $arg, $this->data_pos + $offset);
			if ($found !== false && ($least === false || $found < $least))
				$least = $found;
		}

		return $least;
	}

	public static function variable($string, Token $token)
	{
		$expr = new self($string, $token);
		return $expr->parseVariable();
	}

	public static function variableNotLang($string, Token $token)
	{
		$expr = new self($string, $token);
		return $expr->parseVariable(false);
	}

	public static function stringWithVars($string, Token $token)
	{
		$expr = new self($string, $token);
		return $expr->parseInterpolated();
	}

	public static function normal($string, Token $token, $escape = false)
	{
		return self::boolean($string, $token, $escape);
	}

	public static function boolean($string, Token $token, $escape = false)
	{
		$expr = new self($string, $token, $escape);
		return $expr->parseNormal($escape);
	}

	public static function makeVarName($name)
	{
		return preg_replace('~[^a-zA-Z0-9_]~', '_', $name);
	}

	public static function makeTemplateName($nsuri, $name)
	{
		return 'tpl_' . md5($nsuri) . '_' . self::makeVarName($name);
	}

	/**
	 * I wasn't completely sure where to put this
	 * Recursively htmlspecialchar's a string or array
	 *
	 * @static
	 * @access public
	 * @param mixed $value The value to htmlspecialchar
	 * @return mixed
	 */
	public static function htmlspecialchars($value)
	{
		if (is_array($value))
			foreach ($value as $k => $v)
				$value[$k] = self::htmlspecialchars($v);
		else
			$value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');

		return $value;
	}
}