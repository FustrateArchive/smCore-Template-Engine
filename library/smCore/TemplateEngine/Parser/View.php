<?php

/**
 * View Parser
 *
 * This class contains parsing rules specific to view-type files.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Parser;
use smCore\TemplateEngine\Parser, smCore\TemplateEngine\Token, smCore\TemplateEngine\Source;

class View extends Parser
{
	/**
	 * We're going to disallow some tags, like <tpl:template> and <tpl:content />
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _parseTag(Token $token)
	{
		if ($token->matches(Parser::TPL_NSURI, 'template'))
			$token->toss('Templates cannot be defined inside views - they require their own file loaded via ->loadTemplates().');

		if ($token->matches(Parser::TPL_NSURI, 'content'))
			$token->toss('Views cannot contain <tpl:content />. If you are trying to load a layer, use ->loadLayer().');

		parent::_parseTag($token);
	}
}