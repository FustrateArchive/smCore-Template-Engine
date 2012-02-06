<?php

/**
 * Parser
 *
 * The generic source parser
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

abstract class Parser
{
	const TPL_NSURI = 'org.smcore.tpl';

	protected $_source = null;
	protected $_tokens = array();

	protected $_tree = array();
	protected $_state = 'outside';
	protected $_escape = 'inherit';

	// @todo: actually obey this
	protected $_whitespace = true;

	public function __construct($source)
	{
		if ($source instanceof Source)
		{
			$this->_source = $source;
			$this->_source->initialize();
		}
		else
			$this->_source = new Source\File($source);
	}

	/**
	 * Set the namespaces we'll recognize in the source
	 *
	 * @param array $namespaces
	 *
	 * @access public
	 */
	public static function setNamespaces(array $namespaces)
	{
		Source::setNamespaces($namespaces);
	}

	/**
	 * Parse the sources (the main source and any additional tokens)
	 *
	 * @access public
	 */
	public function parse()
	{
		while ($token = $this->_source->readToken())
		{
			$this->_tokens[] = $token;

			switch ($token->type)
			{
				case 'content':
					$this->_parseContent($token);
					break;
	
				case 'var-ref':
				case 'lang-ref':
				case 'output-ref':
					$this->_parseRef($token);
					break;

				case 'comment-start':
				case 'comment-end':
				case 'comment':
					$this->_parseComment($token);
					break;
	
				case 'tag-start':
				case 'tag-empty':
					$this->_parseTag($token);
					break;
	
				case 'tag-end':
					$this->_parseTagEnd($token);
					break;
			}
		}

		$this->_verifyClosed();
	}

	/**
	 * Make sure the tags were nested correctly.
	 *
	 * @access protected
	 */
	protected function _verifyClosed()
	{
		if (!empty($this->_tree))
		{
			$token = array_pop($this->_tree);
			throw new Exception('parsing_element_incomplete', $token->prettyName(), $token->file, $token->line);
		}
	}

	/**
	 * Parse a content token (such as plain text or HTML)
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseContent(Token &$token)
	{
	}

	/**
	 * Parse a reference token, such as a language or variable reference.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseRef(Token &$token)
	{
		if ($token->type == 'output-ref')
			$token->data = substr($token->data, 1, strlen($token->data) - 2);

		// Make the tag look like a normal tag.
		$token->type = 'tag-empty';
		$token->name = 'output';
		$token->ns = 'tpl';
		$token->nsuri = Parser::TPL_NSURI;
		$token->attributes['value'] = $token->data;
		$token->attributes['escape'] = $this->_escape !== 'inherit' ? $this->_escape === 'true' : 'true';

		$this->_parseTag($token);
	}

	/**
	 * We do nothing with comments. Don't output a single thing.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseComment(Token &$token)
	{
		// Pop it off the stack, so we don't have to worry about it later
		array_pop($this->_tokens);
	}

	/**
	 * Pushes tags onto the tree and handles a few special cases.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token &$token)
	{
		// For a couple of these, we do special stuff.
		if ($token->nsuri == Parser::TPL_NSURI)
		{
			// We only have a couple of built in constructs.
			if ($token->name === 'content')
				$this->_handleTagContent($token);
			else if ($token->name === 'parent')
				$this->_handleTagParent($token);
			else if ($token->name === 'options')
				$this->_handleTagOptions($token);
			else if ($token->name === 'output')
				$this->_handleTagOutput($token);
		}

		if ($token->type === 'tag-start')
			array_push($this->_tree, $token);
	}

	/**
	 * Makes sure we have tags to close and close the right tags.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTagEnd(Token &$token)
	{
		if (empty($this->_tree))
			$token->toss('parsing_tag_already_closed', $token->prettyName());

		$close_token = array_pop($this->_tree);

		// Darn, it's not the same one.
		if ($close_token->nsuri != $token->nsuri || $close_token->name !== $token->name)
			$this->_wrongTagEnd($token, $close_token);

		// This makes it easier, since they're on the same element after all.
		$token->attributes = $close_token->attributes;
	}

	/**
	 * Throw an exception when we encounter an ending tag that doesn't match what's open
	 *
	 * @param smCore\TemplateEngine\Token $token
	 * @param smCore\TemplateEngine\Token $expected
	 *
	 * @access 
	 */
	protected function _wrongTagEnd(Token $token, Token $expected)
	{
		// Special case this error since it's sorta common.
		if ($expected->nsuri === Parser::TPL_NSURI && $expected->name === 'else')
			$expected->toss('generic_tpl_must_be_empty', $expected->prettyName());
		else
			$token->toss('parsing_tag_end_unmatched', $token->prettyName(), $expected->prettyName(), $expected->file, $expected->line);
	}

	/**
	 * Set an option for parsing the rest of the file.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagOptions(Token &$token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_options_must_be_empty');

		if (isset($token->attributes['escape']))
		{
			if ($token->attributes['escape'] === 'true' || $token->attributes['escape'] === 'false' || $token->attributes['escape'] === 'inherit')
				$this->_escape = $token->attributes['escape'];
			else
				$token->toss('tpl_options_invalid_escape');
		}

		if (isset($token->attributes['whitespace']))
		{
			if ($token->attributes['whitespace'] === 'true' || $token->attributes['whitespace'] === 'false')
				$this->_whitespace = $token->attributes['whitespace'] === 'true';
			else
				$token->toss('tpl_options_invalid_whitespace');
		}

		// Pop it off the stack, so we don't have to worry about it later
		array_pop($this->_tokens);
	}

	/**
	 * <tpl:content /> tokens split layers and macros. Make sure they're empty.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagContent(Token &$token)
	{
		// Doesn't make sense for these to have content, so warn.
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_content_must_be_empty');

		// tpl:content can only be a child of tpl:macro or tpl:block elements
		if (!empty($this->_tree))
		{
			foreach ($this->_tree as $tag)
			{
				if ($tag->nsuri === Parser::TPL_NSURI && $tag->name !== 'macro' && $tag->name !== 'block')
					$token->toss('tpl_content_misplaced');
			}
		}
	}

	/**
	 * <tpl:parent /> tokens tell us what a block reference does. They can't be inside ANY tpl: tokens!
	 *
	 * @todo: allow <tpl:block> tags around <tpl:parent /> tags, but that's it
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagParent(Token $token)
	{
		// Doesn't make sense for these to have content, so warn.
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_parent_must_be_empty');

		if (!empty($this->_tree))
		{
			foreach ($this->_tree as $tag)
			{
				if ($tag->nsuri === Parser::TPL_NSURI)
					$token->toss('tpl_parent_misplaced');
			}
		}
	}

	/**
	 * Handle a <tpl:output value="" /> token. Make sure it has what it needs.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagOutput(Token $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_output_must_be_empty');

		if (!isset($token->attributes['value']))
			$token->toss('generic_tpl_missing_required', 'value', $token->prettyName(), 'value');

		// Default the escape parameter just like {$x} does.
		if (!isset($token->attributes['escape']))
			$token->attributes['escape'] = $this->_escape !== 'inherit' ? $this->_escape === 'true' : 'true';
	}

	/**
	 * Get the tokens this parser found
	 *
	 * @return array An array of the tokens found by this parser.
	 *
	 * @access public
	 */
	public function getTokens()
	{
		return $this->_tokens;
	}
}