<?php

/**
 * Macro Template Cleaner
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

class Macros extends Cleaner
{
	public function clean(array &$tokens, $source_filename)
	{
		if (empty($tokens))
			return;

		$new_tokens = array();

		// Keep track of the macro calls and blocks we might find tpl:content in.
		$macro_token = null;
		$inside_blocks = array();

		foreach ($tokens as $token)
		{
			if ($token->matches(Parser::TPL_NSURI, 'macro', 'tag-start'))
			{
				$token->attributes['__type'] = 'above';

				$macro_token = $token;
				$new_tokens[] = $token;
			}
			else if ($token->matches(Parser::TPL_NSURI, 'macro', 'tag-end'))
			{
				// Exit the macro, and give this end tag its "type" attribute.
				$token->attributes['__type'] = $macro_token->attributes['__type'];

				$new_tokens[] = $token;

				if ($token->attributes['__type'] === 'above')
				{
					$insert = new Token(array(
						'data' => $macro_token->data,
						'type' => 'tag-start',
						'file' => $token->file,
						'line' => $token->line,
					));
					$insert->attributes = array(
						'name' => $macro_token->attributes['name'],
						'__type' => 'below',
					);

					$new_tokens[] = $insert;

					$insert = new Token(array(
						'data' => '</tpl:macro>',
						'type' => 'tag-end',
						'file' => $token->file,
						'line' => $token->line,
					));
					$insert->attributes = array(
						'name' => $macro_token->attributes['name'],
						'__type' => 'below',
					);

					$new_tokens[] = $insert;
				}

				$macro_token = null;
			}
			else if ($token->matches(Parser::TPL_NSURI, 'block', 'tag-start'))
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
				if (!empty($inside_blocks))
				{
					foreach ($inside_blocks as $block_token)
					{
						$end_token = new Token(array(
							'data' => '</tpl:block>',
							'type' => 'tag-end',
							'file' => $token->file,
							'line' => $token->line,
						), true);
						$end_token->attributes = $block_token->attributes;

						$new_tokens[] = $end_token;
					}
				}

				// A semi-clone of the top token
				$end_token = new Token(array(
					'data' => '</tpl:macro>',
					'type' => 'tag-end',
					'file' => $token->file,
					'line' => $token->line,
				), true);
				$end_token->attributes = $macro_token->attributes;

				$new_tokens[] = $end_token;

				// And another weird hybrid clone
				$start_token = new Token(array(
					'data' => $macro_token->data,
					'type' => 'tag-start',
					'file' => $token->file,
					'line' => $token->line,
				), true);
				$start_token->attributes = array(
					'name' => $macro_token->attributes['name'],
					'__type' => 'below',
				);

				$new_tokens[] = $start_token;

				// Now pretend we're in this new tag!
				$macro_token = $start_token;

				if (!empty($inside_blocks))
				{
					$reversed = array_reverse($inside_blocks);
					$inside_blocks = array();

					// Start the blocks up again
					foreach ($reversed as $block_token)
					{
						$start_token = new Token(array(
							'data' => $block_token->data,
							'type' => 'tag-start',
							'file' => $token->file,
							'line' => $token->line,
						), true);
						$start_token->attributes = array(
							'name' => $block_token->attributes['name'],
							'__type' => 'below',
						);

						$new_tokens[] = $start_token;
						$inside_blocks[] = $start_token;
					}
				}
			}
			else
			{
				// Just push it forward
				$new_tokens[] = $token;
			}
		}

		$tokens = $new_tokens;
	}
}