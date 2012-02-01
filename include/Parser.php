<?php

/**
 * Parser
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Parser
{
	// Sources can be Tokens, but the primary has to be a Source
	protected $_primary = null;
	protected $_sources = array();

	protected $_listeners = array();
	protected $_templates = array();
	protected $_blocks = array();
	protected $_tokens = array();

	protected $_tree = array();
	protected $_inside_cdata = false;
	protected $_state = 'outside';
	protected $_doctype = 'xhtml';
	protected $_escape = 'inherit';

	public function __construct($source)
	{
		if ($source instanceof Source)
		{
			$this->_primary = $source;
			$this->_primary->initialize();
		}
		else
			$this->_primary = new SourceFile($source);
	}

	/**
	 * Set the namespaces we'll recognize in the source
	 *
	 * @param array $namespaces
	 *
	 * @access public
	 */
	public function setNamespaces(array $namespaces)
	{
		$this->_primary->setNamespaces($namespaces);
	}

	/**
	 * Registers a callback to be called when a certain event is fired.
	 *
	 * @param string $type The type of event to listen for.
	 * @param callback $callback The callback to call if an event of this type is fired.
	 *
	 * @access public
	 */
	public function listen($type, $callback)
	{
		$this->_listeners[$type][] = $callback;
	}

	/**
	 * Insert a source into the queue to be parsed.
	 *
	 * @param mixed $source A Source or Token to add to the queue.
	 * @param boolean $defer Tells the parser to not parse this just yet.
	 *
	 * @access public
	 */
	public function insertSource($source, $defer = false)
	{
		if (!($source instanceof Source) && !($source instanceof Token))
			throw new Exception('parsing_invalid_source_type');

		array_unshift($this->_sources, $source);

		// To defer means we wait, it goes up the chain.
		if ($defer)
			return;

		if ($source instanceof Source)
		{
			while (!$source->isDataEOF())
				$this->_parseNextSource();
		}
		// Just need to process the token once.
		else
			$this->_parseNextSource();
	}

	/**
	 * Parse the sources (the main source and any additional tokens)
	 *
	 * @access public
	 */
	public function parse()
	{
		$this->insertSource($this->_primary, true);

		while (!empty($this->_sources))
			$this->_parseNextSource();

		$this->_verifyClosed();
	}

	/**
	 * Fire an event to the listeners, just in case they have something to say at this point.
	 *
	 * @param string $type Name of the event to fire
	 * @param smCore\TemplateEngine\Token $token The token on which this event will be fired
	 *
	 * @access protected
	 */
	protected function _fire($type, Token $token)
	{
		if (empty($this->_listeners[$type]))
			return;

		foreach ($this->_listeners[$type] as $callback)
		{
			$result = call_user_func($callback, $token, $this);

			if ($result === false)
				break;
		}
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
	 * Parse the next source in the sources array. If it's not the primary, it's a Token.
	 *
	 * @access protected
	 */
	protected function _parseNextSource()
	{
		if (empty($this->_sources))
			throw new Exception('parsing_internal_error');

		$source = $this->_sources[0];

		// If it was actually an Token, pull it out right away.
		if ($source instanceof Token)
			$token = array_shift($this->_sources);
		else
			$token = $source->readToken();

		// Gah, we hit the end of the stream... next source.
		if ($token === false)
		{
			array_shift($this->_sources);
			return;
		}

		$this->_parseNextToken($token);
	}

	/**
	 * Find special tokens and do thing with them.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseNextToken(Token $token)
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

			case 'cdata-start':
				if ($this->_doctype === 'xhtml')
					$this->_parseCDATA($token, true);
				else
					$this->_parseContent($token);
				break;

			case 'cdata-end':
				if ($this->_doctype === 'xhtml')
					$this->_parseCDATA($token, false);
				else
					$this->_parseContent($token);
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

	/**
	 * Parse a content token (such as plain text or HTML)
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseContent(Token $token)
	{
		/**
		 * In HTML mode, we need to check to go in and out of CDATA.
		 * Note: we depend on the tokenizer giving us each HTML element in a separate token.
		 * Otherwise, we'd have to check for <script> and </script>.
		 */
		if ($this->_doctype === 'html')
		{
			// @todo: Avoid preg?
			if ($this->_inside_cdata === false)
			{
				if (preg_match('~\<(script|style|textarea|title)[\t\r\n \>/]~', $token->data, $match) != 0)
					$this->_inside_cdata = $match[1];
			}
			else if ($this->_inside_cdata !== false)
			{
				if (preg_match('~\</(' . preg_quote($this->_inside_cdata, '~') . ')[\t\r\n \>/]~', $token->data, $match) != 0)
					$this->_inside_cdata = false;
			}
		}

		$this->_fire('parsedContent', $token);
	}

	/**
	 * Parse a reference token, such as a language or variable reference.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseRef(Token $token)
	{
		if ($token->type == 'output-ref')
			$token->data = substr($token->data, 1, strlen($token->data) - 2);

		// Make the tag look like a normal tag.
		$token->type = 'tag-empty';
		$token->name = 'output';
		$token->ns = 'tpl';
		$token->nsuri = Compiler::TPL_NSURI;
		$token->attributes['value'] = $token->data;
		$token->attributes['escape'] = $this->_inside_cdata ? 'false' : ($this->_escape !== 'inherit' ? $this->_escape === 'true' : 'true');

		$this->_parseTag($token);
	}

	/**
	 * CDATA tells the parser to either escape references or not to, when in XHTML mode.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 * @param boolean $open True if opening CDATA, false if closing
	 *
	 * @access protected
	 */
	protected function _parseCDATA(Token $token, $open)
	{
		$this->_inside_cdata = $open;

		// Pass it through as if content (still want it outputted.)
		$this->_fire('parsedContent', $token);
	}

	/**
	 * We do nothing with comments. Don't output a single thing.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseComment(Token $token)
	{
	}

	/**
	 * Pushes tags onto the tree and handles a few special cases.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token $token)
	{
		// For a couple of these, we do special stuff.
		if ($token->nsuri == Compiler::TPL_NSURI)
		{
			// We only have a couple of built in constructs.
			if ($token->name === 'block')
				$this->_handleTagBlock($token);
			else if ($token->name === 'content')
				$this->_handleTagContent($token);
			else if ($token->name === 'options')
				$this->_handleTagOptions($token);
			else if ($token->name === 'output')
				$this->_handleTagOutput($token);
			else if ($token->name === 'template')
				$this->_handleTagTemplate($token);
		}

		if ($token->type === 'tag-start')
			array_push($this->_tree, $token);

		$this->_fire('parsedElement', $token);
	}

	/**
	 * Makes sure we have tags to close and close the right tags.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTagEnd(Token $token)
	{
		if (empty($this->_tree))
			$token->toss('parsing_tag_already_closed', $token->prettyName());

		$close_token = array_pop($this->_tree);

		// Darn, it's not the same one.
		if ($close_token->nsuri != $token->nsuri || $close_token->name !== $token->name)
			$this->_wrongTagEnd($token, $close_token);

		// This makes it easier, since they're on the same element after all.
		$token->attributes = $close_token->attributes;

		// We might be exiting a template. These can't be nested.
		if ($token->nsuri == Compiler::TPL_NSURI)
		{
			if ($token->name === 'template')
				$this->_handleTagTemplateEnd($token);
			else if ($token->name === 'block')
				$this->_handleTagBlockEnd($token);
		}

		$this->_fire('parsedElement', $token);
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
		if ($expected->nsuri === Compiler::TPL_NSURI && $expected->name === 'else')
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
	protected function _handleTagOptions(Token $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_options_must_be_empty');

		if (isset($token->attributes['doctype']))
		{
			if ($token->attributes['doctype'] === 'html' || $token->attributes['doctype'] === 'xhtml')
				$this->_doctype = $token->attributes['doctype'];
			else
				$token->toss('tpl_options_invalid_doctype');
		}

		if (isset($token->attributes['escape']))
		{
			if ($token->attributes['escape'] === 'true' || $token->attributes['escape'] === 'false' || $token->attributes['escape'] === 'inherit')
				$this->_escape = $token->attributes['escape'];
			else
				$token->toss('tpl_options_invalid_escape');
		}
	}

	/**
	 * Handle a <tpl:template> token, make sure it has everything we need.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagTemplate(Token $token)
	{
		if ($this->_state !== 'outside')
			$token->toss('tpl_template_must_be_outside');

		if ($token->type === 'tag-empty')
			$token->toss('tpl_template_must_be_not_empty');

		if (empty($token->attributes['name']))
			$token->toss('tpl_template_missing_name');

		if (strpos($token->attributes['name'], ':') === false)
			$token->toss('tpl_template_name_without_ns', $token->attributes['name']);

		// Figure out the namespace and validate it.
		list ($ns, $name) = explode(':', $token->attributes['name'], 2);

		if (empty($ns) || empty($name))
			$token->toss('generic_tpl_no_ns_or_name');

		$nsuri = $token->getNamespace($ns);

		if ($nsuri === false)
			$token->toss('tpl_template_name_unknown_ns', $ns);

		if (strlen($name) === 0)
			$token->toss('tpl_template_name_empty_name', $token->attributes['name']);

		// Templates can't be redefined in the same file
		if (isset($this->_templates[$token->attributes['name']]))
			$token->toss('tpl_template_duplicate_name', $ns . ':' . $name);

		// Templates can't take the name of an existing block
		if (isset($this->_blocks[$token->attributes['name']]))
			$token->toss('tpl_templates_duplicate_block_name', $ns . ':' . $name);

		$this->_templates[$token->attributes['name']] = true;

		$this->_state = 'template';
	}

	/**
	 * Handle a </tpl:template> token, so it doesn't think we're nesting templates.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagTemplateEnd(Token $token)
	{
		$this->_state = 'outside';
	}

	/**
	 * <tpl:content /> tokens split template files and templates. Make sure they're empty.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagContent(Token $token)
	{
		// Doesn't make sense for these to have content, so warn.
		if ($token->type !== 'tag-empty')
			$token->toss('tpl_content_must_be_empty');
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
			$token->attributes['escape'] = $this->_inside_cdata ? 'false' : ($this->_escape !== 'inherit' ? $this->_escape === 'true' : 'true');
	}

	/**
	 * Handle the start of a block definition.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagBlock(Token $token)
	{
		if (empty($token->attributes['name']))
			$token->toss('tpl_block_missing_name');

		if (strpos($token->attributes['name'], ':') === false)
			$token->toss('tpl_block_name_without_ns', $token->attributes['name']);

		// Figure out the namespace and validate it.
		list ($ns, $name) = explode(':', $token->attributes['name'], 2);

		if (empty($ns) || empty($name))
			$token->toss('generic_tpl_no_ns_or_name');

		$nsuri = $token->getNamespace($ns);

		if ($nsuri === false)
			$token->toss('tpl_block_name_unknown_ns', $ns);

		if (strlen($name) === 0)
			$token->toss('tpl_block_name_empty_name', $token->attributes['name']);

		// Blocks can't be redefined in the same file
		if (isset($this->_blocks[$token->attributes['name']]))
			$token->toss('tpl_block_duplicate_name', $ns . ':' . $name);

		// Blocks can't take the name of an existing template
		if (isset($this->_templates[$token->attributes['name']]))
			$token->toss('tpl_block_duplicate_template_name', $ns . ':' . $name);

		$this->_blocks[$token->attributes['name']] = true;
	}

	/**
	 * Handle the end of a block definition.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTagBlockEnd(Token $token)
	{
	}

	/**
	 * Get the tokens this parser found
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	public function getTokens()
	{
		return $this->_tokens;
	}

	/**
	 * Returns the names of the templates that were defined in the source we parsed.
	 *
	 * @return array Template names, i.e. "site:box"
	 *
	 * @access public
	 */
	public function getTemplatesDefined()
	{
		return array_keys($this->_templates);
	}

	/**
	 * Returns the names of the blocks that were defined in the source we parsed.
	 *
	 * @return array Block names, i.e. "site:head"
	 *
	 * @access public
	 */
	public function getBlocksDefined()
	{
		return array_keys($this->_blocks);
	}
}