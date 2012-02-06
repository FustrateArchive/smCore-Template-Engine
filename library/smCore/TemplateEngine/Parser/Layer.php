<?php

/**
 * Layer Parser
 *
 * This class contains parsing rules specific to layer-type files.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Parser;
use smCore\TemplateEngine\Parser, smCore\TemplateEngine\Token, smCore\TemplateEngine\Source;

class Layer extends Parser
{
	/**
	 * We're going to disallow <tpl:template> tags
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token $token)
	{
		if ($token->matches(Parser::TPL_NSURI, 'template'))
			$token->toss('Templates cannot be defined inside layers - they require their own file loaded via ->loadTemplates().');

		parent::_parseTag($token);
	}
}