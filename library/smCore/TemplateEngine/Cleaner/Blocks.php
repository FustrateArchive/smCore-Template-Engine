<?php

namespace smCore\TemplateEngine\Cleaner;
use smCore\TemplateEngine\Cleaner, smCore\TemplateEngine\Parser, smCore\TemplateEngine\Token;

class Blocks extends Cleaner
{
	public function clean(array &$tokens, $source_filename)
	{
		// Don't need to do anything if there aren't any tokens.
		if (empty($tokens))
			return;

		/**
		 * @todo: Allow for template calls inside blocks of the same name. It shouldn't
		 * be allowed anyways, but people are going to try it anyways. Right now it will
		 * get caught up in the end tag of the template part, thinking we're ending the
		 * block reference instead of just the template.
		 */

		$new_tokens = array();
		$current_block = array();
		$waiting_for = null;

		foreach ($tokens as $token)
		{
			if (empty($current_block))
			{
				// This must be a start tag - the parser should have made sure of it
				$waiting_for = $token;

				// Assume it's a replacement until we know otherwise
				$insert = new Token(array(
					'data' => '<tpl:block-ref>',
					'type' => 'tag-start',
					'file' => $token->file,
					'line' => $token->line,
				), true);
				$insert->attributes = array(
					'__name' => $token->prettyName(),
					'__type' => 'replace',
				);

				$current_block = array($insert);
			}
			else if ($token->matches($waiting_for->nsuri, $waiting_for->name, 'tag-end'))
			{
				// Don't bother if it was a "below" with nothing in it (auto-started below)
				if (count($current_block) > 1 || $current_block[0]->attributes['__type'] !== 'below')
				{
					// Finish off the block
					$current_block[] = new Token(array(
						'data' => '</tpl:block-ref>',
						'type' => 'tag-end',
						'file' => '(auto)',
						'line' => -1,
					), true);

					$new_tokens = array_merge($new_tokens, $current_block);
				}

				$current_block = array();
			}
			else if ($token->matches(Parser::TPL_NSURI, 'parent'))
			{
				// If this comes right after the start, we're a "below" reference
				if (count($current_block) === 1)
					$current_block[0]->attributes['__type'] = 'below';
				else
				{
					// Otherwise it was above. We have more to do!
					$current_block[0]->attributes['__type'] = 'above';

					$current_block[] = new Token(array(
						'data' => '</tpl:block-ref>',
						'type' => 'tag-end',
						'file' => '(auto)',
						'line' => -1,
					), true);

					$new_tokens = array_merge($new_tokens, $current_block);

					$insert = new Token(array(
						'data' => '<tpl:block-ref>',
						'type' => 'tag-start',
						'file' => $token->file,
						'line' => $token->line,
					), true);
					$insert->attributes = array(
						'__name' => $token->prettyName(),
						'__type' => 'below',
					);

					$current_block = array($insert);
				}
			}
			else
			{
				$current_block[] = $token;
			}
		}

		$tokens = $new_tokens;
	}
}