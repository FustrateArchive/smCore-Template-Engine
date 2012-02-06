<?php

/**
 * Block References Parser
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Parser;
use smCore\TemplateEngine\Parser, smCore\TemplateEngine\Token, smCore\TemplateEngine\Source;

class Blocks extends Parser
{
	protected $_waiting_for = array();

	/**
	 * Block files don't get content outside of tags.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseContent(Token &$token)
	{
		if ($this->_state === 'outside')
		{
			// Pop it off the stack if it's nothing to worry about, or throw an exception
			if (trim($this->data) === '')
				array_pop($this->_tokens);
			else
				$token->toss('tpl_blocks_content_outside');
		}

		parent::_parseContent($token);
	}

	/**
	 * No references outside the template start and end tags, either.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseRef(Token &$token)
	{
		if ($this->_state === 'outside')
			$token->toss('tpl_blocks_ref_outside');

		parent::_parseRef($token);
	}

// @todo from here down

	/**
	 * No tpl: elements or empty elements on the outside
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token &$token)
	{
		// Non-tpl elements outside of anything else are assumed to be block references.
		if ($this->_state === 'outside')
		{
			if ($token->nsuri === Parser::TPL_NSURI && $token->name !== 'options')
				$token->toss('tpl_blocks_instruction_outside');

			if ($token->type === 'tag-empty')
				$token->toss('tpl_blocks_empty_tag_outside');

			$this->_state = 'block-ref';
			$this->_waiting_for = array($token->nsuri, $token->name);
		}

		parent::_parseTag($token);
	}

	/**
	 * Reset the state, if we encountered the end of the tag we started with.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTagEnd(Token &$token)
	{
		if ($token->matches($this->_waiting_for[0], $this->_waiting_for[1]))
		{
			$this->_waiting_for = null;
			$this->_state = 'outside';
		}

		parent::_parseTagEnd($token);
	}

	/**
	 * Returns the names of all templates defined in this file.
	 *
	 * @return array Template names
	 *
	 * @access public
	 */
	public function getDefinedTemplates()
	{
		return $this->_defined_templates;
	}
}