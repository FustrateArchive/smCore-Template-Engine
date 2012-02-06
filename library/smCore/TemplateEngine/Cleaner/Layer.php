<?php

/**
 * Layer Cleaner
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

class Layer extends Cleaner
{
	public function clean(array &$tokens, $source_filename)
	{
		// If there are no tokens, there's obviously no <tpl:content />.
		if (empty($tokens))
			throw new \Exception('Layers must have a <tpl:content /> tag. Did you mean to use this file as a view instead?');

		// Insert a layer start tag, right at the beginning
		$insert = new Token(array(
			'data' => '<tpl:layer side="above">',
			'type' => 'tag-start',
			'file' => '(auto)',
			'line' => -1,
		), true);
		$insert->attributes['__type'] = 'above';

		$new_tokens = array($insert);

		// Keep track of the macro references and blocks we might find tpl:content in.
		$inside_blocks = array();
		$found_content_tag = false;

		foreach ($tokens as $token)
		{
			if ($token->matches(Parser::TPL_NSURI, 'block', 'tag-start'))
			{
				$token->attributes['__type'] = 'above';

				$inside_blocks[] = $token;
				$new_tokens[] = $token;
			}
			else if ($token->matches(Parser::TPL_NSURI, 'block', 'tag-end'))
			{
				$block_token = array_pop($inside_blocks);

				// Pop the block off the stack, and give this token its "type" attribute.
				$token->attributes['__type'] = $block_token->attributes['__type'];

				$new_tokens[] = $token;

				// If it was an "above", insert an empty matching "below"
				if ($token->attributes['__type'] === 'above')
				{
					$insert = new Token(array(
						'data' => $block_token->data,
						'type' => 'tag-start',
						'file' => $token->file,
						'line' => $token->line,
					));
					$insert->attributes = array(
						'name' => $block_token->attributes['name'],
						'__type' => 'below',
					);

					$new_tokens[] = $insert;

					$insert = new Token(array(
						'data' => '</tpl:block>',
						'type' => 'tag-end',
						'file' => $token->file,
						'line' => $token->line,
					));
					$insert->attributes = array(
						'name' => $block_token->attributes['name'],
						'__type' => 'below',
					);

					$new_tokens[] = $insert;
				}
			}
			else if ($token->matches(Parser::TPL_NSURI, 'content', 'tag-empty'))
			{
				$found_content_tag = true;

				if (!empty($inside_blocks))
				{
					foreach ($inside_blocks as $block_token)
					{
						$insert = new Token(array(
							'data' => '</tpl:block>',
							'type' => 'tag-end',
							'file' => $token->file,
							'line' => $token->line,
						), true);
						$insert->attributes = $block_token->attributes;

						$new_tokens[] = $insert;
					}
				}

				// A semi-clone of the top token
				$insert = new Token(array(
					'data' => '</tpl:layer>',
					'type' => 'tag-end',
					'file' => $token->file,
					'line' => $token->line,
				), true);
				$insert->attributes['__type'] = 'above';

				$new_tokens[] = $insert;

				// And another weird hybrid clone
				$insert = new Token(array(
					'data' => '<tpl:layer side="below">',
					'type' => 'tag-start',
					'file' => $token->file,
					'line' => $token->line,
				), true);
				$insert->attributes['__type'] = 'below';

				$new_tokens[] = $insert;

				if (!empty($inside_blocks))
				{
					$reversed = array_reverse($inside_blocks);
					$inside_blocks = array();

					// Start the blocks up again
					foreach ($reversed as $block_token)
					{
						$insert = new Token(array(
							'data' => $block_token->data,
							'type' => 'tag-start',
							'file' => $token->file,
							'line' => $token->line,
						), true);
						$insert->attributes = array(
							'name' => $block_token->attributes['name'],
							'__type' => 'below',
						);

						$new_tokens[] = $insert;
						$inside_blocks[] = $insert;
					}
				}
			}
			else
			{
				// Just push it forward
				$new_tokens[] = $token;
			}
		}

		if (!$found_content_tag)
			throw new \Exception('Layers must have a <tpl:content /> tag. Did you mean to use this file as a view instead?');

		$tokens = $new_tokens;

		// Insert a layer start tag, right at the beginning
		$insert = new Token(array(
			'data' => '</tpl:layer>',
			'type' => 'tag-end',
			'file' => '(auto)',
			'line' => -1,
		), true);
		$insert->attributes['__type'] = 'below';

		$tokens[] = $insert;
	}
}