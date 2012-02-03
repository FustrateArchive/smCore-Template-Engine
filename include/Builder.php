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
	protected $_common_vars = array();

	protected $_data = null;
	protected $_close_data = false;

	protected $_state = null;
	protected $_defer_for = null;
	protected $_defer_tokens = array();

	protected $_has_emitted = false;
	protected $_disable_emit = false;
	protected $_emit_output = array();

	protected $_last_line = 1;
	protected $_last_file = null;
	protected $_last_template = null;

	protected $_listeners = array();

	// It's like a game show
	protected $_blockOrTemplate = array();

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

	/**
	 * Set the variable names that are available in all templates and blocks
	 *
	 * @param array $names Variable names, i.e. array('context', 'post')
	 *
	 * @access public
	 */
	public function setCommonVars(array $names)
	{
		$this->_common_vars = $names;
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
		$this->_startCacheFile($template['cache_file'], $template['class_name'], $template['extend_class_name']);

		$found_non_empty_token = false;

		// Until we find a real token, don't output blank content
		foreach ($template['data']['tokens'] as $token)
		{
			if ($found_non_empty_token || trim($token->data) !== '')
			{
				$found_non_empty_token = true;
				$this->_handleToken($token);
			}
		}

		// Make sure we finish the output functions before going on to deferred stuff.
		$this->_finishOutput();

		// Keep going through until we output everything. Blocks can be nested, which is why we loop.
		while (!empty($this->_defer_tokens))
		{
			$tokens = $this->_defer_tokens;
			$this->_defer_tokens = array();

			foreach ($tokens as $token)
			{
				$this->_handleToken($token);
			}
		}

		$this->_finalize();
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

		if ($extend_class_name === null)
			$extend_class_name = 'smCore\TemplateEngine\Template';

		// Most of this stuff is dummy data to help me test
		$this->emitCode('<?php class ' . $class_name . ' extends ' . $extend_class_name . ' {

	public function output__above(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		');

		$this->_state = 'output-above';
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
	 * Finish the main output functions, if we're still in them. Gives us a clean start for templates and blocks.
	 *
	 * @access protected
	 */
	protected function _finishOutput()
	{
		// If we never hit a tpl:content in the main part of the template, add a dummy bottom here.
		if ($this->_state === 'output-above')
		{
			// Stop the output__above method and start output-below
			$this->emitCode('$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array(\'__tpl_args\', \'__tpl_argstack\', \'__tpl_stack\', \'__tpl_params\', \'__tpl_func\', \'__tpl_error_handler\'))); }');
			$this->emitCode('public function output__below(&$__tpl_params = array()){}');
		}
		else if ($this->_state === 'output-below')
		{
			$this->emitCode('$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array(\'__tpl_args\', \'__tpl_argstack\', \'__tpl_stack\', \'__tpl_params\', \'__tpl_func\', \'__tpl_error_handler\'))); }');
		}

		$this->_state = 'outside';
	}

	/**
	 * Finish up, by emitting the __construct function and closing the class.
	 *
	 * @access protected
	 */
	protected function _finalize()
	{
		// @todo: Emit something here for hooks?

		$this->_finishOutput();

		// We don't emit the __construct function until the end, so that we know what templates and blocks we used.
		$this->emitCode('

	public function __construct()
	{
		parent::__construct();

		// @todo: register templates and block usage
	}
}');

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
		// If we're deferring, only a few things matter
		if ($this->_defer_for !== null)
		{
			// Match up the end tag
			if ($token->type === 'tag-end' && $token->prettyName() === $this->_defer_for['pretty_name'] && $token->attributes['name'] === $this->_defer_for['name'])
			{
				$this->_defer_for = null;
				$this->_handleTag($token);
			}
			else
			{
				// Just defer this.
				$this->_defer_tokens[] = $token;
			}
		}
		else
		{
			// We're not deferring, so let's do stuff
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
				case 'block-tag-start':
					$this->_handleBlockStart($token);
					break;
				case 'block-tag-end':
					$this->_handleBlockEnd($token);
					break;
				case 'template-tag-start':
					$this->_handleTemplateStart($token);
					break;
				case 'template-tag-end':
					$this->_handleTemplateEnd($token);
					break;
				default:
					break;
			}
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
		if ($token->nsuri === Compiler::TPL_NSURI)
		{
			if ($token->name === 'options')
			{
				// We don't output anything for option tags
			}
			else if ($token->name === 'template')
			{
				if ($token->type === 'tag-start')
				{
					// Set requirements for the token we're waiting for
					$this->_defer_for = array(
						'pretty_name' => $token->prettyName(),
						'name' => $token->attributes['name'],
					);
				}
				else
				{
					
				}

				// Put a slightly different token on the stack before we start deferring, for next time around
				$insert = clone $token;
				// Such as 'template-tag-start' or 'block-tag-end'
				$token->type = $token->name . '-' . $token->type;
				$this->_defer_tokens[] = $insert;
			}
			else if ($token->name === 'block')
			{
				if ($token->type === 'tag-start')
				{
					// Set requirements for the token we're waiting for
					$this->_defer_for = array(
						'pretty_name' => $token->prettyName(),
						'name' => $token->attributes['name'],
					);
				}
				else
				{
					// Emit code to fire a listener
					$this->emitCode('$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array(\'__tpl_args\', \'__tpl_argstack\', \'__tpl_stack\', \'__tpl_params\', \'__tpl_func\', \'__tpl_error_handler\')));');
					$this->emitCode('$this->_fireBlockListener(\'' . $token->attributes['name'] . '\', $__tpl_params);');
					$this->emitCode('extract($__tpl_params, EXTR_OVERWRITE);');
				}

				// Put a slightly different token on the stack before we start deferring, for next time around
				$insert = clone $token;
				// Such as 'template-tag-start' or 'block-tag-end'
				$token->type = 'block-' . $token->type;
				$this->_defer_tokens[] = $insert;
			}
			else if ($token->name === 'content')
			{
				if ($this->_state === 'output-above')
				{
					// Stop the output__above method and start output-below
					$this->emitCode('$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array(\'__tpl_args\', \'__tpl_argstack\', \'__tpl_stack\', \'__tpl_params\', \'__tpl_func\', \'__tpl_error_handler\')));');
					$this->emitCode('} public function output__below(&$__tpl_params = array()) {');
					$this->emitCode('extract($__tpl_params, EXTR_SKIP);');

					$this->_state = 'output-below';
				}
				else if ($this->_state === 'template-above')
				{
				}
			}
			else
			{
			}

			$this->_fireEmit($token);

			// If there was no emitted code, it's probably an error.
			if ($this->has_emitted === false && $this->debugging)
				$token->toss('unknown_tpl_element', $token->name);
		}
		else
		{
			$full_name = $token->prettyName();

			if (array_key_exists($full_name, $this->_blockOrTemplate))
			{
				$type = $this->_blockOrTemplate[$full_name];

				if ($type === 'block')
				{
					// It's a block event!
				}
				else
				{
					// It's a template call!
					$this->emitCode('/** TEMPLATE CALL **/');
				}
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

	protected function _emitCodeInternal($code)
	{
		// Don't output any \r's, we use 't' mode, so those are automatic.
		// !!!SLOW Can we remove this str_replace?  Just need to test line numbers matching on mac/linux/windows with several line ending types.
		$this->_fwrite(str_replace("\r", '', $code));
		$this->_last_line += substr_count($code, "\n");
	}

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

		$this->_fwrite($this->getErrorClassName() . '::remap(\'' . addcslashes($file, '\\\'') . '\', ' . (int) $token->line . ');');

		$this->_last_file = $token->file;
		$this->_last_line = $token->line;

		return true;
	}

	// callback(Builder $builder, $type, array $attributes, Token $token)
	public function listenEmit($nsuri, $name, $callback)
	{
		$this->_listeners[$nsuri][$name][] = $callback;
	}

	protected function _fireEmit(Token $token)
	{
		// This actually fires a whole mess of events, but easier to hook into.
		// In this case, it's cached, so it's fairly cheap.
		$this->_fireActualEmit($token->ns, $token->name, $token);
		$this->_fireActualEmit('*', $token->name, $token);
		$this->_fireActualEmit($token->ns, '*', $token);
		$this->_fireActualEmit('*', '*', $token);
	}

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








	/** */
	/** */
	/** Unfinished stuff */
	/** */
	/** */

	public function parsedElement(Token $token, Parser $parser)
	{
		if ($token->nsuri === Template::TPL_NAMESPACE)
		{
			$this->has_emitted = false;

			// Everything else is handled via a hook.
			if ($token->name === 'container')
				$this->handleTagContainer($token);
			elseif ($token->name === 'template')
				$this->handleTagTemplate($token);
			elseif ($token->name === 'content')
				$this->handleTagContent($token);

			$this->_fireEmit($token);

			// If there was no emitted code, it's probably an error.
			if ($this->has_emitted === false && $this->debugging)
				$token->toss('unknown_tpl_element', $token->name);
		}
		else
		{
			$this->handleTagCall($token);

			$this->_fireEmit($token);
		}
	}

	protected function handleTagContainer(Token $token)
	{
		// A container is just a thing to set namespaces, it does nothing.
		// However, we have to omit something or it will think it's unrecognized.
		$this->emitCode('');
	}

	protected function handleTagTemplate(Token $token)
	{
		// Assumption: can't be tag-empty (verified by parser.)
		if ($token->type === 'tag-start')
		{
			$this->_last_template = $this->prebuilder->getTemplateForBuild($token);

			// Template was already built, so don't emit it again.
			if ($this->_last_template['should_emit'] === false)
				$this->disable_emit = true;

			$this->emitTemplateStart($this->_last_template['name'] . '_above', $token);
		}
		elseif ($token->type === 'tag-end')
		{
			// If we haven't output the below, output it now.
			if ($this->_last_template['stage'] == 1)
			{
				$this->emitTemplateEnd(false, $token);
				$this->emitTemplateStart($this->_last_template['name'] . '_below', $token);

				$this->_last_template['stage'] = 2;
			}

			$this->emitTemplateEnd(true, $token);

			// Even if it wasn't disabled before, enable it until the next template.
			$this->disable_emit = false;
			$this->_last_template = null;
		}
	}

	protected function handleTagContent(Token $token)
	{
		// Already hit one, can't have two.
		if ($this->_last_template['stage'] == 2)
			$token->toss('tpl_content_twice');

		// Assumption: must be tag-empty (verified by parser.)
		$this->emitTemplateEnd(false, $token);
		$this->emitTemplateStart($this->_last_template['name'] . '_below', $token);

		// Mark that we've output the above AND below.
		$this->_last_template['stage'] = 2;
	}

	protected function handleTagCall(Token $token)
	{
		$template = $this->prebuilder->getTemplateForCall($token);
		$name = addcslashes($template['name'], '\\\'');

		if (isset($token->attributes[Template::TPL_NAMESPACE . ':inherit']))
			$inherit = preg_split('~[ \t\r\n]+~', $token->attributes[Template::TPL_NAMESPACE . ':inherit']);
		else
			$inherit = array();

		$args_escaped = array();
		$arg_names = array_merge($inherit, $this->common_vars);

		// When calling, we pass along the common vars.
		foreach ($this->common_vars as $var_name)
		{
			$k = '\'' . addcslashes(Expression::makeVarName($var_name), '\\\'') . '\'';
			$args_escaped[] = $k . ' => ' . $this->parseExpression('variable', '{$' . $var_name . '}', $token);
		}

		// Pass any attributes along.
		foreach ($token->attributes as $k => $v)
		{
			// Don't send this one.
			if ($k === Template::TPL_NAMESPACE . ':inherit')
				continue;

			$arg_names[] = Expression::makeVarName($k);

			$k = '\'' . addcslashes(Expression::makeVarName($k), '\\\'') . '\'';

			// The string passed to templates will get double-escaped unless we unescape it here.
			// We don't do this for tpl: things, though, just for calls.
			$v = html_entity_decode($v);

			$args_escaped[] = $k . ' => ' . $this->parseExpression('stringWithVars', $v, $token);
		}

		// This checks the requires parameter to make sure they passed everything necessary.
		$required = array_diff($template['requires'], $arg_names, $inherit);
		// If they used inherit="*", we can't really tell...
		if (!empty($required) && !in_array('*', $inherit))
			$token->toss('template_missing_required', $token->prettyName(), implode(', ', $required));

		if ($token->type == 'tag-start' || $token->type == 'tag-empty')
			$this->emitTagCall($name . '_above', $args_escaped, $inherit, true, $template, $token);
		if ($token->type == 'tag-end' || $token->type == 'tag-empty')
			$this->emitTagCall($name . '_below', $args_escaped, $inherit, false, $template, $token);
	}

	protected function emitTagCall($escaped_name, array $args_escaped, array $args_inherit, $first, $template, Token $token)
	{
		// Do we know for sure that it is defined?  If so, we can skip an if.
		if (!$template['defined'])
			$this->emitCode('if (function_exists(\'' . $escaped_name . '\')) {', $token);

		if ($first)
		{
			$this->emitCode('global $__toxg_argstack; if (!isset($__toxg_argstack)) $__toxg_argstack = array();', $token);

			if (in_array('*', $args_inherit))
				$this->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ') + $__toxg_params;', $token);
			elseif (!empty($args_inherit))
				$this->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ') + array_intersect_key($__toxg_params, array_flip(' . var_export($args_inherit, true) . '));', $token);
			else
				$this->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ');', $token);

			$this->emitCode('$__toxg_argstack[] = &$__toxg_args;', $token);
		}
		else
			$this->emitCode('global $__toxg_argstack; $__toxg_args = array_pop($__toxg_argstack);', $token);

		// Better to use a de-refenced call than call_user_func/_array, because of reference issue.
		$this->emitCode('$__toxg_func = \'' . $escaped_name . '\'; $__toxg_func($__toxg_args);', $token);

		if (!$template['defined'])
			$this->emitCode('}', $token);
	}

	protected function emitTemplateStart($name, Token $token)
	{
		$this->_defined_templates[] = $name;
		$this->emitCode('function template__' . str_replace(':', '_', $name) . '(&$__toxg_params = array()) {');
		$this->emitCode('extract($__toxg_params, EXTR_SKIP);', $token);

		if ($this->debugging)
		{
			$this->emitCode('$__toxg_error_handler = new ' . $this->getErrorClassName() . '();');
			$this->emitDebugPos($token, 'code', true);
		}
	}

	protected function emitTemplateEnd($last, $token)
	{
		// This updates the parameters for the _below function.
		if (!$last)
		{
			$omit = array('\'__toxg_args\'', '\'__toxg_argstack\'', '\'__toxg_stack\'', '\'__toxg_params\'', '\'__toxg_func\'', '\'__toxg_error_handler\'');
			$this->emitCode('$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array(' . implode(', ', $omit) . ')));', $token);
		}

		$this->emitCode('}', $token);
	}

	public function setBlockNames($block_names)
	{
		foreach ($block_names as $name)
			$this->_blockOrTemplate[$name] = 'block';
	}

	public function setTemplateNames($template_names)
	{
		foreach ($template_names as $name)
			$this->_blockOrTemplate[$name] = 'template';
	}

	public function parseExpression($type, $expression, Token $token, $escape = false)
	{
		return Expression::$type($expression, $token, $escape);
	}

	protected function getErrorClassName()
	{
		return 'smCore\TemplateEngine\Errors';
	}

	protected function getUsageClassName()
	{
		return 'smCore\TemplateEngine\Template';
	}
}