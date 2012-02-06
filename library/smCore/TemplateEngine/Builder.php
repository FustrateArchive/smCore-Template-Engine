<?php

/**
 * Builder
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Builder
{
	protected $_debugging = false;

	protected $_data = null;
	protected $_close_data = false;

	protected $_has_emitted = false;
	protected $_disable_emit = false;
	protected $_emit_output = array();

	protected $_last_line = 1;
	protected $_last_file = null;
	protected $_last_template = null;

	protected $_listeners = array();

	protected static $_defined_templates = array();
	protected $_block_refs = array();

	public function __construct()
	{
	}

	/**
	 * Make sure PHP closes open resources when exiting
	 *
	 * @access public
	 */
	public function __destruct()
	{
		if (is_resource($this->_data))
			fclose($this->_data);

		$this->_data = null;
	}

	/**
	 * Tell the Builder to output debug code into the compiled classes or not
	 *
	 * @param boolean $debugging  
	 *
	 * @access public
	 */
	public function setDebugging($debugging)
	{
		$this->_debugging = (boolean) $debugging;
	}

	public static function setDefinedTemplates(array $defined_templates)
	{
		self::$_defined_templates = $defined_templates;
	}

	/**
	 * Start writing the compiled class file, then handle the tokens, then close the class file
	 *
	 * @param array $template Template data (cache file, class name, tokens, etc.) from TemplateList
	 *
	 * @access public
	 */
	public function build($template)
	{
		try
		{
			$this->_startCacheFile($template['cache_file'], $template['class_name'], $template['extend_class_name']);

			foreach ($template['tokens'] as $token)
				$this->_handleToken($token);

			$this->_finalize($template);
		}
		// Anything goes wrong, we kill the cache file.
		catch (\Exception $e)
		{
			// @todo: Do we need to both close the pointer AND unlink it? Is unlinking enough?
			$this->_closeCacheFile();
			@unlink($template['cache_file']);

			throw $e;
		}
	}

	/**
	 * Start the file and class
	 *
	 * @param string $cache_file Where to save this compiled class file
	 * @param string $class_name The name of the class to output
	 * @param string $extend_class_name Another class to extend, other than smCore\TemplateEngine\Template
	 *
	 * @access protected
	 */
	protected function _startCacheFile($cache_file, $class_name, $extend_class_name = null)
	{
		if (is_resource($cache_file))
			$this->_data = $cache_file;
		else
		{
			$this->_data = @fopen($cache_file, 'wt');

			if (!$this->_data)
				throw new \Exception('builder_cannot_open');

			$this->_close_data = true;
		}

		// Most of this stuff is dummy data to help me test
		$this->emitCode('<?php class ' . $class_name . ' extends ' . $extend_class_name . ' {');
	}

	/**
	 * Close the cache file resource if it's open
	 *
	 * @access public
	 */
	public function _closeCacheFile()
	{
		// Release the file so it isn't left open until the request end.
		if ($this->_data !== null && $this->_close_data)
			@fclose($this->_data);
	}

	/**
	 * Finish up, by emitting the __construct function and closing the class.
	 *
	 * @access protected
	 */
	protected function _finalize($template)
	{
		// We don't emit the __construct function until the end, so that we know what templates and blocks we used.
		$this->emitCode('public function __construct() {parent::__construct();');

		if (!empty($template['defined_templates']))
			$this->emitCode('$this->_registerTemplates(array(\'' . implode('\', \'', array_keys($template['defined_templates'])) . '\'));');

		if (!empty($this->_block_refs))
		{
			$this->emitCode('$this->_addBlockListeners(array(');

			foreach ($this->_block_refs as $ref)
				$this->emitCode('array(\'' . $ref['name'] . '\', \'' . $ref['type'] . '\'),');

			$this->emitCode('));');

			$this->_block_refs = array();
		}

		$this->emitCode('}}');

		$this->_closeCacheFile();

		if ($this->_last_template !== null)
			throw new Exception('builder_unclosed_template');
	}

	/**
	 * Write a string to the cache resource
	 *
	 * @param string $string The string to write to the cache resource
	 *
	 * @access protected
	 */
	protected function _fwrite($string)
	{
		if ($string === '')
			return;

		if (@fwrite($this->_data, $string) === false)
			throw new \Exception('builder_cannot_write');
	}

	/**
	 * Handles each token in order, deferring when necessary.
	 *
	 * @param smCore\TemplateEngine\Token
	 *
	 * @access protected
	 */
	protected function _handleToken(Token $token)
	{
		switch($token->type)
		{
			case 'tag-empty':
			case 'tag-start':
			case 'tag-end':
				$this->_handleTag($token);
				break;
			case 'content':
				$this->_handleContent($token);
				break;
			default:
				break;
		}
	}

	/**
	 * Do something with a start, empty, or end tag token.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleTag(Token $token)
	{
		if ($token->nsuri === Parser::TPL_NSURI)
		{
			if ($token->name === 'template')
			{
				if ($token->type === 'tag-start')
				{
					$this->emitCode('function template_' . str_replace(':', '_', $token->attributes['name']) . '_' . $token->attributes['__type'] . '($context) { $me = $this;');

					if ($this->_debugging)
					{
						$this->emitCode('new smCore\TemplateEngine\Errors();');
						$this->emitDebugPos($token, 'code', true);
					}
				}
				else
				{
					$this->emitCode('}');
				}
			}
			else if ($token->name === 'view')
			{
				if ($token->type === 'tag-start')
				{
					$this->emitCode('function view($context) { $tpl = $this;');
				}
				else
				{
					$this->emitCode('}');
				}
			}
			else if ($token->name === 'layer')
			{
				if ($token->type === 'tag-start')
				{
					$this->emitCode('function layer_' . $token->attributes['__type'] . '($context) { $tpl = $this;');
				}
				else
				{
					$this->emitCode('}');
				}
			}
			else if ($token->name === 'block')
			{
				if ($token->type === 'tag-start')
				{
					$this->emitCode('$this->_fireBlockEvent(\'' . $token->attributes['name'] . '\', \'' . $token->attributes['__type'] . '\', function() use (&$context, $tpl) {');
				}
				else
				{
					$this->emitCode('}, $context);');
				}
			}
			else if ($token->name === 'block-ref')
			{
				if ($token->type === 'tag-start')
				{
					$this->emitCode('function block_' . str_replace(':', '_', $token->attributes['__name']) . '_' . $token->attributes['__type'] . '($context) { $tpl = $this;');

					$this->_block_refs[] = array(
						'name' => $token->attributes['__name'],
						'type' => $token->attributes['__type'],
					);
				}
				else
				{
					$this->emitCode('}');
				}
			}
			else
			{
				$this->_fireEmit($token);
			}

			// If there was no emitted code, it's probably an error.
			if ($this->has_emitted === false && $this->_debugging)
				$token->toss('unknown_tpl_element', $token->name);
		}
		else
		{
			$full_name = $token->prettyName();

			if (array_key_exists($full_name, self::$_defined_templates))
			{
				$template_token = self::$_defined_templates[$full_name];

				$args_escaped = array();
				$arg_names = array();

				// Pass any attributes along.
				foreach ($token->attributes as $k => $v)
				{
					$arg_names[] = Expression::makeVarName($k);

					// The string passed to templates ($v) will get double-escaped unless we unescape it here.
					// We don't do this for tpl: things, though, just for calls.
					$args_escaped[] = '\'' . addcslashes(Expression::makeVarName($k), '\\\'') . '\' => ' . $this->parseExpression('stringWithVars', html_entity_decode($v), $token);
				}

				if (!empty($template_token->attributes['requires']))
				{
					$requires = array_filter(array_map('trim', preg_split('~[\s,]+~', $template_token->attributes['requires'])));
					$missing = array_diff($requires, $arg_names);

					if (!empty($missing))
						$token->toss('template_missing_required', $full_name, implode(', ', $missing), $template_token->file, $template_token->line);
				}

				if ($token->type === 'tag-start' || $token->type === 'tag-empty')
					$this->emitCode('$tpl->callTemplate(\'' . $full_name . '\', \'above\', array_merge($context, array(' . implode(', ', $args_escaped) . ')));');

				if ($token->type === 'tag-end' || $token->type === 'tag-empty')
					$this->emitCode('$tpl->callTemplate(\'' . $full_name . '\', \'below\', array_merge($context, array(' . implode(', ', $args_escaped) . ')));');
			}
			else
			{
				$this->emitOutputString($token->data);
			}
		}
	}

	/**
	 * Output content straight to the compiled file.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _handleContent(Token $token)
	{
		$this->emitOutputString($token->data, $token);
	}

	/**
	 * Send pure code to the compiled file. Flushes the output beforehand.
	 *
	 * @param string $code The raw code to output unchanged
	 * @param smCore\TemplateEngine\Token $token
	 * 
	 * @access public
	 */
	public function emitCode($code, Token $token = null)
	{
		$this->_has_emitted = true;

		if ($this->_disable_emit)
			return;

		$this->_flushOutputCode();

		if ($this->_debugging && $token !== null)
			$this->_emitDebugPos($token);

		$this->_emitCodeInternal($code);
	}

	/**
	 * Add a string value to the output array, which is assembled later on.
	 *
	 * @param string $data
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access public
	 */
	public function emitOutputString($data, Token $token = null)
	{
		$this->_has_emitted = true;

		if ($this->_disable_emit)
			return;

		$this->_emit_output[] = array(
			'type' => 'string',
			'data' => $data,
			'token' => $token,
		);
	}

	/**
	 * Add a non-string value to the output array, which is assembled later on.
	 *
	 * @param string $expr The expression we're adding
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access public
	 */
	public function emitOutputParam($expr, Token $token = null)
	{
		$this->_has_emitted = true;

		if ($this->_disable_emit)
			return;

		$this->_emit_output[] = array(
			'type' => 'param',
			'data' => $expr,
			'token' => $token,
		);
	}

	/**
	 * If there are strings/params in the output array, emit them and get back to a state where we can emit code.
	 *
	 * @access protected
	 */
	protected function _flushOutputCode()
	{
		if (empty($this->_emit_output))
			return;

		// We're going to enter and exit strings.
		$in_string = false;
		$first = true;

		foreach ($this->_emit_output as $node)
		{
			if ($node['type'] === 'string')
			{
				// If we're not inside a string already, start one with debug info.
				if (!$in_string)
				{
					if ($node['token'] !== null && $this->_emitDebugPos($node['token']))
						$first = true;

					$this->_emitCodeInternal(($first ? 'echo ' : ', ') . '\'');
					$in_string = true;
				}

				$this->_emitCodeInternal(addcslashes($node['data'], "'\\"));
			}
			else if ($node['type'] === 'param')
			{
				if ($in_string)
				{
					$this->_emitCodeInternal('\'');
					$in_string = false;
				}

				// Just in case the position has changed for some reason (overlay, etc.)
				if ($node['token'] !== null && $this->_emitDebugPos($node['token'], 'echo'))
					$first = true;

				$this->_emitCodeInternal(($first ? 'echo ' : ', ') . $node['data']);
			}

			$first = false;
		}

		if ($in_string)
			$this->_emitCodeInternal('\'');

		$this->_emitCodeInternal(';');

		$this->_emit_output = array();
	}

	/**
	 * Emit code without regard for disabled emits or debugging code.
	 *
	 * @param string $code The raw code to write to the cache file.
	 *
	 * @access protected
	 */
	protected function _emitCodeInternal($code)
	{
		// Don't output any \r's, we use 't' mode, so those are automatic.
		// @todo: SLOW! Can we remove this str_replace? Just need to test line numbers matching on mac/linux/windows with several line ending types.
		$this->_fwrite(str_replace("\r", '', $code));
		$this->_last_line += substr_count($code, "\n");
	}

	/**
	 * Change the line that errors might come from to align with those of the original file.
	 *
	 * @param smCore\TemplateEngine\Token $token The token we're emitting a line change for
	 * @param string $type Where we came from, 'string' or 'code'
	 * @param boolean $force True to keep this on the same line, false to put it on a new line.
	 *
	 * @access protected
	 */
	protected function _emitDebugPos(Token $token, $type = 'code', $force = false)
	{
		// Okay, maybe we don't need to bulk up the template.  Let's see how we can get out of updating the pos.

		// If the file is the same, we have a chance.
		if ($token->file === $this->_last_file && !$force)
		{
			// If the line is the same as it should be, we're good.
			if ($token->line == $this->_last_line)
				return false;
			else if ($token->line > $this->_last_line)
			{
				// If we just need a higher line number, then just print some newlines (cheaper for PHP to cache.)
				$this->_emitCodeInternal(str_repeat("\n", $token->line - $this->_last_line));
				return false;
			}

			// Okay, this means the line number was lower (template?) so let's go.
		}

		// In case this is actually a database "filename" or something, don't wipe it out.
		$file = $token->file;

		if (realpath($file) != false)
			$file = realpath($file);

		if ($type === 'echo')
			$this->emitCode(';');

		// This triggers the error system to remap the caller's file/line with the specified.
		if (!$force)
			$this->_fwrite("\n");

		$this->_fwrite('smCore\TemplateEngine\Errors::remap(\'' . addcslashes($file, '\\\'') . '\', ' . (int) $token->line . ');');

		$this->_last_file = $token->file;
		$this->_last_line = $token->line;

		return true;
	}

	/**
	 * Add a callback to try when we see an unrecognized tag.
	 *
	 * Callbacks should have the following signature: (Builder $builder, $type, array $attributes, Token $token)
	 *
	 * @param string $nsuri The namespace URI of the tag to listen for
	 * @param string $name The tag name to listen for
	 * @param callback $callback
	 *
	 * @access public
	 */
	public function listenEmit($nsuri, $name, $callback)
	{
		$this->_listeners[$nsuri][$name][] = $callback;
	}

	/**
	 * Fire each type of emit listener in order, from most specific to most general.
	 *
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _fireEmit(Token $token)
	{
		// This actually fires a whole mess of events, but easier to hook into.
		// In this case, it's cached, so it's fairly cheap.
		$this->_fireActualEmit($token->ns, $token->name, $token);
		$this->_fireActualEmit('*', $token->name, $token);
		$this->_fireActualEmit($token->ns, '*', $token);
		$this->_fireActualEmit('*', '*', $token);
	}

	/**
	 * Does the heavy lifting of the emits.
	 *
	 * @param string $nsuri
	 * @param string $name
	 * @param smCore\TemplateEngine\Token $token
	 *
	 * @access protected
	 */
	protected function _fireActualEmit($nsuri, $name, Token $token)
	{
		// If there are no listeners, nothing to do.
		if (empty($this->_listeners[$nsuri]) || empty($this->_listeners[$nsuri][$name]))
			return;

		$listeners = $this->_listeners[$nsuri][$name];

		foreach ($listeners as $callback)
		{
			// We don't use call_user_func because we want to allow by reference passing.
			if (is_string($callback))
				$result = $callback($this, $token->type, $token->attributes, $token);
			elseif (!is_string($callback[0]))
				$result = $callback[0]->$callback[1]($this, $token->type, $token->attributes, $token);
			else
				$result = call_user_func($callback, $this, $token->type, $token->attributes, $token);

			// We keep going until we hit one that returned false
			if ($result === false)
				break;
		}
	}

	/**
	 * Parse an expression of a certain type.
	 *
	 * @param string $type The type of expression to treat this as
	 * @param string $expression The expression data
	 * @param smCore\TemplateEngine\Token $token The token this expression came from
	 * @param boolean $escape Should this expression be escaped by default?
	 * @return string The code created from this expression
	 *
	 * @access public
	 */
	public function parseExpression($type, $expression, Token $token, $escape = false)
	{
		return Expression::$type($expression, $token, $escape);
	}
}