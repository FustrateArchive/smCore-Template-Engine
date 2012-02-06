<?php

/**
 * View Cleaner
 *
 * Cleans up tokens, so that the builder can just zip through them.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Cleaner;
use smCore\TemplateEngine\Cleaner, smCore\TemplateEngine\Token, smCore\TemplateEngine\Parser;

class View extends Cleaner
{
	public function clean(array &$tokens, $source_filename)
	{
		// Just tell the builder that we're doing both sides of any defined blocks at once.
		if (!empty($tokens))
		{
			foreach ($tokens as $k => $token)
			{
				if ($token->matches(Parser::TPL_NSURI, 'block'))
					$tokens[$k]->attributes['__type'] = 'both';
			}
		}

		// Insert a token to start the view...
		array_unshift($tokens, new Token(array(
			'data' => '<tpl:view>',
			'type' => 'tag-start',
			'file' => '(auto)',
			'line' => -1,
		), true));

		// ...and one to end it.
		$tokens[] = new Token(array(
			'data' => '</tpl:view>',
			'type' => 'tag-end',
			'file' => '(auto)',
			'line' => -1,
		), true);
	}
}