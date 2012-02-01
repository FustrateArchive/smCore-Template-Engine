<?php

/**
 * Source
 *
 * Represents the source code to a template or overlay.  Provides lexing facilities.
 *
 * The use of this class is to parse source into tokens, which are chunks of categorized
 * source code.  Since namespaces also change per source, it manages those as well.
 * Tokens are represented be an array, which has this basic format:
 *
 *   - file: The file in which the token appeared (for errors.)
 *   - line: The line the token /started/ on (for errors.)
 *   - type: The type of token (see below.)
 *   - data: The contents of the token (source code.)
 *
 * The standard token types are:
 *
 *   - var-ref:       A reference to a variable. ({$x})
 *   - lang-ref:      A reference to a language string. ({#x})
 *   - tag-start:     A start tag. ({tpl:if} or <tpl:if>)
 *   - tag-empty:     An empty tag. (<tpl:if />)
 *   - tag-end:       An end tag. (</tpl:if>)
 *   - cdata-start:   Start of CDATA. (<![CDATA[)
 *   - cdata-end:     End of CDATA. (]]>)
 *   - comment-start: The start of a comment. (<!---)
 *   - comment-end:   The end of a comment. (--->)
 *   - comment:       The contents of a comment.
 *   - content:       Any other HTML.
 *
 * The basic use of this class will look like:
 *
 * $source = new Source($data, 'filename.tpl');
 * while ($token = $source->readToken())
 *     do_something($token);
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Source
{
	// @todo: allow longer?
	const MAX_TAG_LENGTH = 2048;
	const BUFFER_APPEND_LENGTH = 4096;

	protected $_data = null;
	protected $_data_pos = 0;
	protected $_data_buffer = '';

	protected $_file = null;
	protected $_line = 1;

	protected $_namespaces = array();
	protected $_wait_comment = false;
	protected $_next_token_tabs = 0;

	public function __construct($data, $file, $line = 1)
	{
		if ($data === false)
			throw new \Exception('parsing_cannot_read');

		if (!is_resource($data) && !is_string($data) && !is_array($data))
			throw new \Exception('parsing_not_supported: ' . gettype($data));

		$this->_data = $data;
		$this->_file = $file;
		$this->_line = $line;

		// For simplicity, we treat a string source as the buffer most of the time.
		if (is_string($this->_data))
			$this->_data_buffer = &$this->_data;
	}

	public function __destruct()
	{
	}

	public function initialize()
	{
		// Don't do anything if we're already at the beginning.
		if ($this->_data_pos > 0)
		{
			$this->_data_pos = 0;

			// For resources, we have to seek.
			if (is_resource($this->_data))
			{
				if (!@rewind($this->_data))
					throw new \Exception('parsing_cannot_seek');

				$this->_data_buffer = '';
			}

			$this->_line = 1;
		}
	}

	public function setNamespaces(array $namespaces)
	{
		$this->_namespaces = $namespaces;
	}

	public function __toString()
	{
		return __CLASS__ . ' ' . $this->_file;
	}

	public function addNamespace($name, $nsuri = null)
	{
		$this->_namespaces[$name] = is_null($nsuri) ? 'urn:ns:' . $name : $nsuri;
	}

	public function getNamespace($name)
	{
		if (isset($this->_namespaces[$name]))
			return $this->_namespaces[$name];
		else
			return false;
	}

	public function readToken()
	{
		if ($this->isDataEOF())
		{
			if ($this->_wait_comment !== false)
				throw new \Exception('syntax_comment_unterminated');
			return false;
		}

		// We may have a stream, an array of already-parsed tokens, or a string.
		if (is_resource($this->_data))
			return $this->_readStreamToken();
		elseif (is_array($this->_data))
			return $this->_readArrayToken();
		else
			return $this->_readStringToken();
	}

	public function isDataEOF()
	{
		// For arrays, we use data_pos as an index.  Check only it.
		if (is_array($this->_data))
			return $this->_data_pos >= count($this->_data);

		// For streams and strings, we have a buffer.  If it's not used up yet, we're not at the end.
		if ($this->_data_pos < mb_strlen($this->_data_buffer))
			return false;

		if (is_resource($this->_data))
		{
			if (!feof($this->_data))
				return false;
		}
		return true;
	}

	protected function _readStreamToken()
	{
		// Extend the buffer when it gets too small.  We won't allow an element longer than 2048 bytes.
		if (mb_strlen($this->_data_buffer) - $this->_data_pos < self::MAX_TAG_LENGTH)
		{
			$this->_data_buffer = mb_substr($this->_data_buffer, $this->_data_pos) . fread($this->_data, self::BUFFER_APPEND_LENGTH);
			$this->_data_pos = 0;
		}

		if (mb_strlen($this->_data_buffer) == 0)
			return false;

		return $this->_readStringToken();
	}

	protected function _readArrayToken()
	{
		if ($this->_data_pos >= count($this->_data))
			return false;

		return $this->_data[$this->_data_pos++];
	}

	protected function _readStringToken()
	{
		if ($this->_wait_comment !== false)
			return $this->_readComment();

		switch ($this->_data_buffer[$this->_data_pos])
		{
		case '<':
			return $this->_readTagToken();

		case '{':
			return $this->_readCurlyToken();

		case ']':
			if ($this->_firstPosOf(']]>') === $this->_data_pos)
				return $this->_makeToken('cdata-end', mb_strlen(']]>'));

			// Intentional fall-through, anything else after ] is fine.

		default:
			return $this->_readContent();
		}
	}

	protected function _readComment()
	{
		if ($this->_firstPosOf('--->') === $this->_data_pos)
		{
			$this->_wait_comment = false;
			return $this->_makeToken('comment-end', mb_strlen('--->'));
		}

		// Find the next interesting character.
		$next_pos = $this->_firstPosOf('--->');
		if ($next_pos === false)
			$next_pos = mb_strlen($this->_data_buffer);

		return $this->_makeToken('comment', $next_pos - $this->_data_pos);
	}

	protected function _readContent($offset = 0)
	{
		// Find the next interesting character.
		$next_pos = $this->_firstPosOf(array('<', '{', ']]>'), $offset);
		if ($next_pos === false)
			$next_pos = mb_strlen($this->_data_buffer);

		return $this->_makeToken('content', $next_pos - $this->_data_pos);
	}

	protected function _readTagToken()
	{
		// CDATA sections toggle escaping.
		if ($this->_firstPosOf('<![CDATA[') === $this->_data_pos)
			return $this->_makeToken('cdata-start', mb_strlen('<![CDATA['));

		// Comments can comment out commands, which won't get processed.
		if ($this->_firstPosOf('<!---') === $this->_data_pos)
		{
			// This tells us to do nothing until a --->.
			$this->_wait_comment = $this->_line;
			return $this->_makeToken('comment-start', mb_strlen('<!---'));
		}

		// Must be namespaced or not interesting, so bail early if obviously not.
		$ns_mark = $this->_firstPosOf(':', 1);
		if ($ns_mark !== false)
		{
			$ns = mb_substr($this->_data_buffer, $this->_data_pos + 1, $ns_mark - $this->_data_pos - 1);

			// Oops, don't look at the / at the front...
			if ($ns[0] === '/')
				$ns = mb_substr($ns, 1);

			if (!self::validNCName($ns))
				$ns = false;
		}
		else
			$ns = false;

		// Okay, then, the namespace was found invalid so just treat it as content.
		if ($ns === false)
			return $this->_readContent(1);

		return $this->_readGenericTag('tag', '<', '>', 1 + mb_strlen($ns) + 1);
	}

	protected function _readCurlyToken()
	{
		// Make sure it's something interesting and we're not wasting our time...
		if (mb_strlen($this->_data_buffer) <= $this->_data_pos + 1)
			return $this->_readContent(1);

		$next_c = $this->_data_buffer[$this->_data_pos + 1];

		// We support {$var}, {#lang}, and {tpl:stuff /}.
		if ($next_c === '$')
			$type = 'var-ref';
		elseif ($next_c === '#')
			$type = 'lang-ref';
		else
		{
			// Could still be a var-ref in form CLASS::constant or CLASS::value.
			$type = 'tag';

			$ns_mark = $this->_firstPosOf(':', 1);
			if ($ns_mark !== false)
			{
				$ns = mb_substr($this->_data_buffer, $this->_data_pos + 1, $ns_mark - $this->_data_pos - 1);

				// Oops, don't look at the / at the front...
				if ($ns[0] === '/')
					$ns = mb_substr($ns, 1);

				if (!self::validNCName($ns))
					$ns = false;
				elseif ($this->_data_buffer[$ns_mark + 1] === ':')
					$type = 'var-ref';
				// What we're checking here is that we don't have this: {key:'value'}...
				// Or in other words, after the : we need an alphanumeric char or similar.
				elseif (!self::validNCName($this->_data_buffer[$ns_mark + 1]))
					$ns = false;
			}
			else
				$ns = false;

			if ($ns === false)
			{
				$check = trim($this->_data_buffer[$this->_data_pos + 1]);
				if (!empty($check))
					$type = 'output-ref';
			}

			// Otherwise this may be CSS/JS/something we don't want to munge.
			if ($ns === false && $type === 'tag')
				return $this->_readContent(1);
		}

		// Now it's time to parse a tag, lang, or var.
		return $this->_readGenericTag($type, '{', '}', 1);
	}

	protected function _readGenericTag($type, $nest_c, $end_c, $offset)
	{
		// Now it's time to parse a tag.  Start after any namespace/</etc. we already found.
		$end_pos = $this->_data_pos + $offset;
		$finality = mb_strlen($this->_data_buffer);
		$nesting = 0;

		while ($end_pos < $finality)
		{
			// The only way to end a tag is >/}, but we respect quotes too.
			$end_bracket = strpos($this->_data_buffer, $end_c, $end_pos);
			$nest_bracket = strpos($this->_data_buffer, $nest_c, $end_pos);
			$quote = strpos($this->_data_buffer, '"', $end_pos);

			// Nesting looks like this: {#x:{#y:1}}
			if ($nest_bracket !== false && $end_bracket !== false)
			{
				// The { has to be before the }, and no quotes in the way.
				if ($nest_bracket < $end_bracket && ($quote === false || $nest_bracket < $quote))
				{
					$end_pos = $nest_bracket + 1;
					$nesting++;
				}
			}

			// If the > is before the ", we're done.
			if ($end_bracket !== false && ($quote === false || $end_bracket < $quote))
			{
				$end_pos = $end_bracket + 1;

				// We were nested, so just dump out a level.
				if ($nesting > 0)
				{
					$nesting--;
					continue;
				}
				else
					break;
			}

			if ($quote !== false)
			{
				$quote = strpos($this->_data_buffer, '"', $quote + 1);
				if ($quote === false)
					throw new \Exception('syntax_tag_buffer_unmatched_quotes');

				$end_pos = $quote + 1;
			}
			else
				throw new \Exception('syntax_invalid_tag');
		}

		if ($type === 'tag')
		{
			// Last char is > or }, so an empty tag would have a / before that.
			if ($this->_data_buffer[$end_pos - 2] === '/')
				$type = 'tag-empty';
			// And... obviously, if the second char is a /, it's an end tag.
			elseif ($this->_data_buffer[$this->_data_pos + 1] === '/')
				$type = 'tag-end';
			else
				$type = 'tag-start';
		}

		return $this->_makeToken($type, $end_pos - $this->_data_pos);
	}

	protected function _makeToken($type, $chars)
	{
		$data = mb_substr($this->_data_buffer, $this->_data_pos, $chars);
		$this->_data_pos += $chars;

		$tok = $this->_makeTokenObject(array(
			'file' => $this->_file,
			'line' => $this->_line,
			'type' => $type,
			'data' => $data,
			'tabs' => $this->_next_token_tabs,
		));

		// If it wasn't actually a valid tag, let's go back and eat less after all.
		if ($tok->type != $type && $tok->type == 'content' && $chars > 1)
		{
			// Backpeddle....
			$this->_data_pos -= $chars;
			return $this->_makeToken('content', 1);
		}

		// Count the tabs at the end, because we're magic like that
		$this->_next_token_tabs = mb_strlen($data) - mb_strlen(rtrim($data, "\t"));

		// This token was now, next token will move forward as much as this token did.
		$this->_line += mb_substr_count($data, "\n");
		return $tok;
	}

	protected function _makeTokenObject($info)
	{
		return new Token($info, $this);
	}

	protected function _firstPosOf($find, $offset = 0)
	{
		$least = false;

		// Just look for each and take the lowest.
		$find = (array) $find;
		foreach ($find as $arg)
		{
			$found = strpos($this->_data_buffer, $arg, $this->_data_pos + $offset);
			if ($found !== false && ($least === false || $found < $least))
				$least = $found;
		}

		return $least;
	}

	public static function validNCName($ns)
	{
		// See XML spec for the source of this list - http://www.w3.org/TR/REC-xml/.
		// !!! All non-Latin Unicode code points allowed.  This is wrong, but manual UTF-8 makes it a pain.
		$first_char = "A..Z_a..z\x80..\xFF";
		$rest_chars = $first_char . '-.0..9';

		// Instead of a regex, we're using trim-syntax.  It trims out the valid chars above...
		// If there are any other chars left, then it wasn't valid.
		if (trim($ns, $rest_chars) !== '')
			return false;
		if (mb_strlen($ns) == 0 || trim($ns[0], $first_char) !== '')
			return false;

		return true;
	}
}