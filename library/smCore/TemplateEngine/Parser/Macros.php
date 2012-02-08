<?php

/**
 * Macro Template Parser
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Parser;
use smCore\TemplateEngine\Parser, smCore\TemplateEngine\Token, smCore\TemplateEngine\Source;

class Macros extends Parser
{
	protected $_defined_macros = array();

	/**
	 * Macro files don't get anything outside of the <tpl:macro> tags.
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
				$token->toss('tpl_macro_content_outside');
		}

		parent::_parseContent($token);
	}

	/**
	 * No references outside the macro start and end tags, either.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseRef(Token &$token)
	{
		if ($this->_state === 'outside')
			$token->toss('tpl_macro_ref_outside');

		parent::_parseRef($token);
	}

	/**
	 * The only tags that can be outside of anything else in here are tpl:macro and tpl:options.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token $token)
	{
		if ($token->matches(Parser::TPL_NSURI, 'macro'))
		{
			if ($this->_state !== 'outside')
				$token->toss('tpl_macro_must_be_outside');

			if ($token->type === 'tag-empty')
				$token->toss('tpl_macro_must_be_not_empty');

			if (empty($token->attributes['name']))
				$token->toss('tpl_macro_missing_name');

			if (strpos($token->attributes['name'], ':') === false)
				$token->toss('tpl_macro_name_without_ns', $token->attributes['name']);

			// Figure out the namespace and validate it.
			list ($ns, $name) = explode(':', $token->attributes['name'], 2);

			if (empty($ns) || empty($name))
				$token->toss('generic_tpl_no_ns_or_name');

			$nsuri = Source::getNamespace($ns);

			if ($nsuri === false)
				$token->toss('tpl_macro_name_unknown_ns', $ns);

			if (strlen($name) === 0)
				$token->toss('tpl_macro_name_empty_name', $token->attributes['name']);

			// Templates can't be redefined in the same file
			if (isset($this->_defined_macros[$token->attributes['name']]))
				$token->toss('tpl_macro_duplicate_name', $ns . ':' . $name);

			$this->_defined_macros[$token->attributes['name']] = $token;

			$this->_state = 'macro';
		}
		else if ($this->_state === 'outside' && !$token->matches(Parser::TPL_NSURI, 'options'))
			$token->toss('tpl_macro_tag_outside');

		parent::_parseTag($token);
	}

	/**
	 * Reset the state, if we encountered the end of a macro here.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTagEnd(Token $token)
	{
		if ($token->matches(Parser::TPL_NSURI, 'macro'))
			$this->_state = 'outside';

		parent::_parseTagEnd($token);
	}

	/**
	 * Returns the names of all macros defined in this file.
	 *
	 * @return array Template names
	 *
	 * @access public
	 */
	public function getDefinedMacros()
	{
		return $this->_defined_macros;
	}
}