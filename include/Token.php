<?php

/**
 * Token
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Token
{
	public $data = null;
	public $type = 'tag-start';
	public $file = null;
	public $line = 0;

	public $ns = '';
	public $nsuri = '';
	public $name = '';
	public $attributes = array();

	protected $_data_pos = 0;
	protected $_data_len = 0;
	protected $_source = null;

	public function __construct(array $token, Source $source)
	{
		$this->_source = $source;

		$this->data = $token['data'];
		$this->_data_len = mb_strlen($this->data);
		$this->type = $token['type'];
		$this->file = $token['file'];
		$this->line = $token['line'];

		$this->_parseData();
	}

	/**
	 * If this is a tag token, we'll want to parse it
	 *
	 * @access protected
	 */
	protected function _parseData()
	{
		if ($this->type === 'tag-start' || $this->type === 'tag-empty')
			$this->_parseStart();
		else if ($this->type === 'tag-end')
			$this->_parseEnd();
	}

	/**
	 * Parse a start or empty tag token
	 *
	 * @access protected
	 */
	protected function _parseStart()
	{
		$this->_data_pos = 1;
		list ($this->ns, $this->name) = $this->_parseName();

		// Parse the attributes which don't have any name specified, mainly for shortcuts
		$this->_parseSingleAttribute($this->_data_pos);

		while ($this->_parseAttribute())
			continue;

		$this->_setNamespace();

		// A start tag will be 1 from end, empty tag 2 from end (/>)...
		$end_offset = $this->type == 'tag-start' ? 1 : 2;

		if ($this->_data_pos < strlen($this->data) - $end_offset)
			$this->toss('syntax_invalid_tag');
	}

	/**
	 * NFI what this does. I haven't gotten it to go past the third code line.
	 *
	 * @param int $pos 
	 * @return boolean
	 *
	 * @access protected
	 */
	protected function _parseSingleAttribute($pos = 0)
	{
		$start_quote = $this->_firstPosOf('"', $this->_data_pos - $pos);

		if ($start_quote === false || $this->data[$start_quote - 1] == '=')
			return false;

		$start_part = substr($this->data, 0, $start_quote);
		$end_part = substr($this->data, $start_quote);

		$this->data = $start_part . 'default=' . $end_part;

		return true;
	}

	/**
	 * Parse an end tag token
	 *
	 * @access protected
	 */
	protected function _parseEnd()
	{
		// Skip </
		$this->_data_pos = 2;
		list ($this->ns, $this->name) = $this->_parseName();

		$this->_setNamespace();

		if ($this->_data_pos < strlen($this->data) - 1)
			$this->toss('syntax_invalid_tag_end');
	}

	/**
	 * Set the namespace URI of this token based on its namespace. Also check for content masquerading as a tag.
	 *
	 * @access protected
	 */
	protected function _setNamespace()
	{
		if ($this->ns !== '')
			$this->nsuri = $this->_source->getNamespace($this->ns);

		// If we don't have a namespace, this is XHTML.
		if ($this->nsuri === false)
			$this->type = 'content';
	}

	/**
	 * Parse the namespace and name of this token or attribute, i.e. array("site", "box") or array("", "name")
	 *
	 * @return array 
	 *
	 * @access protected
	 */
	protected function _parseName()
	{
		// None of these are valid name chars, but they all end the name.
		$after_name = $this->_firstPosOf(array(' ', "\t", "\r", "\n", '=', '/', '>', '}'));

		if ($after_name === false)
			$this->toss('syntax_name_unterminated');

		$ns_mark = $this->_firstPosOf(':');

		if ($ns_mark !== false && $ns_mark < $after_name)
		{
			$ns = $this->_eatUntil($ns_mark);

			// Skip the : after the namespace.
			$this->_data_pos++;

			if (!Source::validNCName($ns))
				$this->toss('syntax_name_ns_invalid');
		}
		else
			$ns = '';

		$name = $this->_eatUntil($after_name);

		if (!Source::validNCName($name))
			$this->toss('syntax_name_invalid');

		$this->_eatWhite();

		return array($ns, $name);
	}

	/**
	 * Try to parse an attribute at the current position in the token
	 *
	 * @return boolean Whether or not we could parse an attribute here
	 *
	 * @access protected
	 */
	protected function _parseAttribute()
	{
		$after_name = $this->_firstPosOf('=');

		if ($after_name === false)
			return false;

		list ($ns, $name) = $this->_parseName();

		// If it doesn't have a value, it's a boolean attribute
		if ($this->data[$this->_data_pos] !== '=')
		{
			$this->_saveAttribute($ns, $name, true);
		}
		else
		{
			$this->_data_pos++;

			$quote_type = $this->data[$this->_data_pos];

			if ($this->data[$this->_data_pos] !== '\'' && $this->data[$this->_data_pos] !== '"')
				$this->toss('syntax_attr_value_not_quoted');

			$this->_data_pos++;

			// Look for the same quote mark at the end of the value.
			$end_quote = $this->_firstPosOf($quote_type);

			if ($end_quote === false)
				$this->toss('syntax_attr_value_unterminated');

			// Grab the value, and then skip the end quote.
			$this->_saveAttribute($ns, $name, $this->_eatUntil($end_quote));
			$this->_data_pos++;
		}

		$this->_eatWhite();

		return true;
	}

	/**
	 * Set a namespace via a token... gotta change this.
	 *
	 * @todo: DON'T DO THIS - maybe use tpl:options instead, or don't do it at all
	 *
	 * @param string $ns
	 * @param string $name
	 * @param string $value
	 *
	 * @access protected
	 */
	protected function _saveAttribute($ns, $name, $value)
	{
		// !!! This sets it for the rest of the document, which is wrong, but it's usually fine.
		if ($ns === 'xmlns')
			$this->_source->addNamespace($name, $value);
		else if ($ns === '')
			$this->attributes[$name] = $value;
		else
		{
			// Namespaced attributes get the full URI for now.  We could do an object if it becomes necessary.
			$nsuri = $this->getNamespace($ns);
			$this->attributes[$nsuri . ':' . $name] = $value;
		}
	}

	/**
	 * Advance the pointer until we find something that's not a space, tab, CR, or LF
	 *
	 * @access protected
	 */
	protected function _eatWhite()
	{
		while ($this->_data_pos < $this->_data_len)
		{
			$c = ord($this->data[$this->_data_pos]);

			// Okay, found whitespace (space, tab, CR, LF)
			if ($c != 32 && $c != 9 && $c != 10 && $c != 13)
				break;

			$this->_data_pos++;
		}
	}

	/**
	 * Advance the pointer to a certain position, and return the data advanced past
	 *
	 * @param int $pos The data position to advance to
	 * @return string The data that was advanced past
	 *
	 * @access protected
	 */
	protected function _eatUntil($pos)
	{
		$data = mb_substr($this->data, $this->_data_pos, $pos - $this->_data_pos);
		$this->_data_pos = $pos;

		return $data;
	}

	/**
	 * Find the first position of an array of strings
	 *
	 * @param array $find An array of strings to search for
	 * @param int $offset The location in the data to start searching
	 * @return mixed Integer location of the first found string, false if nothing was found.
	 *
	 * @access protected
	 */
	protected function _firstPosOf($find, $offset = 0)
	{
		$least = false;

		// Just look for each and take the lowest.
		$find = (array) $find;

		foreach ($find as $arg)
		{
			$found = strpos($this->data, $arg, $this->_data_pos + $offset);

			if ($found !== false && ($least === false || $found < $least))
				$least = $found;
		}

		return $least;
	}

	/**
	 * Find the nsuri for a namespace
	 *
	 * @param string $name The namespace to look for
	 * @return mixed A string nsuri if it was found, otherwise false
	 *
	 * @access public
	 */
	public function getNamespace($name)
	{
		return $this->_source->getNamespace($name);
	}

	/**
	 * Return a "pretty" name for this token, such as "site:box"
	 *
	 * @return string The pretty token name
	 *
	 * @access public
	 */
	public function prettyName()
	{
		return $this->ns . ':' . $this->name;
	}

	/**
	 * Toss an exception from this token
	 *
	 * @todo restore full exceptions
	 *
	 * @param string $key
	 *
	 * @access public
	 */
	public function toss($key)
	{
		// For error messages, we always really want after the newline, anyway.
		if (!empty($this->data) && $this->data[0] === "\n")
			$this->line += strspn($this->data, "\n");

		$params = func_get_args();
		$params = array_slice($params, 1);

		throw new \Exception($key);
	}
}