<?php

namespace ToxG;

class StandardElements
{
	protected $template_push_level = 0;

	protected function __construct()
	{
	}

	public static function useIn($template)
	{
		// In case any state is needed.
		$inst = new self();

		$tags = array(
			'call',
			'cycle',
			'default',
			'element',
			'else',
			'flush',
			'for',
			'foreach',
			'if',
			'output',
			'set',
			'template-pop',
			'template-push',
		);

		foreach ($tags as $tag)
			$template->listenEmitBasic($tag, array($inst, 'tpl_' . str_replace('-', '_', $tag)));
	}

	public function tpl_call(Builder $builder, $type, array $attributes, Token $token)
	{
		if ($token->type != 'tag-empty')
			$token->toss('invalid tag type');

		$this->requireAttributes(array('name'), $attributes, $token);

		list ($ns, $name) = explode(':', $attributes['name']);

		if (empty($ns) || empty($name))
			$token->toss('generic_tpl_no_ns_or_name');

		$ns = Expression::stringWithVars($ns, $token);
		$name = Expression::stringWithVars($name, $token);
		$base = 'Expression::makeTemplateName(Theme::getNamespace(' . $ns . '), ' . $name . ')';
		$func_above = $base . ' . \'_above\'';
		$func_below = $base . ' . \'_below\'';

		// Reset the attributes, after figuring out which ones aren't "name"
		$args = array_diff_key($attributes, array('name' => null));
		$attributes = array();

		// Pass any attributes along.
		if (!empty($args))
			foreach ($args as $k => $v)
			{
				$k = '\'' . addcslashes(Expression::makeVarName($k), '\\\'') . '\'';

				// The string passed to templates will get double-escaped unless we unescape it here.
				// We don't do this for tpl: things, though, just for calls.
				$attributes[] = $k . ' => ' . Expression::stringWithVars(html_entity_decode($v), $token);
			}

		$this->tpl_call_emitFunc($func_above, $builder, $attributes, true, $token);
		$this->tpl_call_emitFunc($func_below, $builder, $attributes, false, $token);
	}

	protected function tpl_call_emitFunc($func_name, $builder, $attributes, $first, $token)
	{
		// Do we know for sure that it is defined?  If so, we can skip an if.
		$builder->emitCode('if (function_exists('. $func_name . ')) {', $token);

		$builder->emitCode('global $__toxg_argstack; if (!isset($__toxg_argstack)) $__toxg_argstack = array();', $token);

		if ($first)
			$builder->emitCode('$__toxg_args = array(' . implode(', ', $attributes) . '); $__toxg_argstack[] = &$__toxg_args;', $token);
		else
			$builder->emitCode('global $__toxg_argstack; $__toxg_args = array_pop($__toxg_argstack);', $token);

		// Better to use a de-refenced call than call_user_func/_array, because of reference issue.
		$builder->emitCode('$__toxg_func = ' . $func_name . '; $__toxg_func($__toxg_args);', $token);

		$builder->emitCode('}', $token);	
	}

	// Very useful for alternating backgrounds
	public function tpl_cycle(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireAttributes(array('values', 'name'), $attributes, $token);

		// Not sure if we really even need to pre-build these variables anymore
		$name = Expression::makeVarName($attributes['name']);

		if (empty($name))
			$token->toss('untranslated', 'Invalid cycle name.');

		$cycle_array = '$__toxg_cycle_' . $name;
		$cycle_counter = '$__toxg_cycle_counter_' . $name;

		$values = explode(',', $attributes['values']);

		if (empty($values))
			$token->toss('untranslated', 'Cannot cycle through an empty array.');

		foreach ($values as $k => $val)
			$values[$k] = Expression::stringWithVars($val, $token);

		// Generate the code
		$builder->emitCode('if (!isset(' . $cycle_array . ')) {' . $cycle_array . ' = array(' . implode(',', $values) . '); ' . $cycle_counter . ' = 0;} else {' . $cycle_counter . '++; } if (' . $cycle_counter . ' > count(' . $cycle_array . ') - 1) {' . $cycle_counter . ' = 0;} echo ' . $cycle_array . '[' . $cycle_counter . '];', $token);
	}

	public function tpl_default(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('var'), $attributes, $token);

		$value = $builder->parseExpression('variable', $attributes['var'], $token);
		if (isset($attributes['default']))
			$default = $builder->parseExpression('stringWithVars', $attributes['default'], $token);
		else
			$default = '\'\'';

		// !!! Better way to detect lang use?
		if (substr($value, 0, 1) !== '$')
			$builder->emitCode('if (' . $value . ') echo htmlspecialchars(' . $value . ', ENT_COMPAT, "UTF-8");', $token);
		else
			$builder->emitCode('if (!empty(' . $value . ')) echo htmlspecialchars(' . $value . ', ENT_COMPAT, "UTF-8");', $token);

		// Don't bother with an else if it's not needed.
		if ($default != '\'\'')
			$builder->emitCode('else echo htmlspecialchars(' . $default . ', ENT_COMPAT, "UTF-8");', $token);
	}

	public function tpl_element(Builder $builder, $type, array $attributes, Token $token)
	{
		// We don't use requireAttributes() because we are using the ns.
		if (empty($attributes[Template::TPL_NAMESPACE . ':name']))
			$token->toss('generic_tpl_empty_attr', 'tpl:name', $token->prettyName());
		$name = $builder->parseExpression('stringWithVars', $attributes[Template::TPL_NAMESPACE . ':name'], $token);

		if (isset($attributes[Template::TPL_NAMESPACE . ':inherit']))
			$inherit = preg_split('~[ \t\r\n]+~', $attributes[Template::TPL_NAMESPACE . ':inherit']);
		else
			$inherit = array();

		if ($token->type === 'tag-empty' || $token->type === 'tag-start')
		{
			$args_escaped = array();
			foreach ($attributes as $k => $v)
			{
				if ($k === Template::TPL_NAMESPACE . ':inherit' || $k === Template::TPL_NAMESPACE . ':name')
					continue;

				$k = '\'' . addcslashes(Expression::makeVarName($k), '\\\'') . '\'';
				$args_escaped[] = $k . ' => ' . $builder->parseExpression('stringWithVars', $v, $token);
			}

			if (in_array('*', $inherit))
				$builder->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ') + $__toxg_params;', $token);
			elseif (!empty($inherit)) 
				$builder->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ') + array_intersect_key($__toxg_params, array_flip(' . var_export($inherit, true) . '));', $token);
			else
				$builder->emitCode('$__toxg_args = array(' . implode(', ', $args_escaped) . ');', $token);

			$builder->emitOutputString('<', $token);
			$builder->emitOutputParam($name, $token);
			$builder->emitCode('foreach ($__toxg_args as $__toxg_k => $__toxg_v) echo \' \', htmlspecialchars($__toxg_k, ENT_COMPAT, "UTF-8"), \'="\', htmlspecialchars($__toxg_v, ENT_COMPAT, "UTF-8"), \'"\';', $token);

			if ($token->type === 'tag-empty')
				$builder->emitOutputString(' />', $token);
			else
				$builder->emitOutputString('>', $token);
		}
		elseif ($token->type === 'tag-end')
		{
			$builder->emitOutputString('</', $token);
			$builder->emitOutputParam($name, $token);
			$builder->emitOutputString('>', $token);
		}
	}

	public function tpl_else(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);

		if (!empty($attributes['default']))
			$attributes['test'] = $token->attributes['default'];

		if (isset($attributes['test']))
		{
			$expr = $builder->parseExpression('boolean', $attributes['test'], $token);
			$builder->emitCode('} elseif (' . $expr . ') {', $token);
		}
		else
			$builder->emitCode('} else {', $token);
	}

	public function tpl_flush(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);

		$builder->emitCode('ob_flush(); flush();', $token);
	}

	public function tpl_for(Builder $builder, $type, array $attributes, Token $token)
	{
		$init = '';
		$while = '';
		$modify = '';

		if (!empty($attributes['init']))
			$init = Expression::normal($attributes['init'], $token);

		if (!empty($attributes['while']))
			$while = Expression::boolean($attributes['while'], $token);

		if (!empty($attributes['modify']))
			$modify = Expression::normal($attributes['modify'], $token);

		// If there's no parens or $'s in it, it can't be for-able.
		if (empty($init) && empty($while) && empty($modify))
			$token->toss('tpl_for_no_params');

		if ($type === 'tag-empty')
			$builder->emitCode('for (' . $init . '; ' . $while . '; ' . $modify . ') {}', $token);
		else if ($type === 'tag-start')
			$builder->emitCode('for (' . $init . '; ' . $while . '; ' . $modify . ') {', $token);
		else
			$builder->emitCode('}', $token);
	}

	public function tpl_foreach(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireNotEmpty($token);
		$this->requireAttributes(array('from', 'as'), $attributes, $token);

		$counter = null;

		if (!empty($attributes['counter']))
			$counter = $builder->parseExpression('variableNotLang', $attributes['counter'], $token);

		if ($type === 'tag-start')
		{
			$from = $builder->parseExpression('normal', $attributes['from'], $token);

			// Are they trying to use a key?
			if (strpos($attributes['as'], '=>') !== false)
			{
				list ($as_k, $as_v) = explode('=>', $attributes['as'], 2);
				$as = $builder->parseExpression('variableNotLang', $as_k, $token);
				$as .= ' => ' . $builder->parseExpression('variableNotLang', $as_v, $token);
			}
			else
				$as = $builder->parseExpression('variableNotLang', $attributes['as'], $token);

			// If there's no parens or $'s in it, it can't be foreachable.
			if (strpos($from, '$') === false && strpos($from, '(') === false)
				$token->toss('tpl_foreach_invalid_from');

			$builder->emitCode(($counter !== null ? ($counter . ' = 0;') : '') . 'if (!empty(' . $from . ')) foreach (' . $from . ' as ' . $as . ') {' . ($counter !== null ? ($counter . '++;') : ''), $token);
		}
		else
		{
			$builder->emitCode('}', $token);
		}
	}

	public function tpl_if(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireNotEmpty($token);
		if (!empty($attributes['default']))
		{
			$attributes['test'] = $token->attributes['default'];
			$token->attributes['test'] = $token->attributes['default'];
		}
		$this->requireAttributes(array('test'), $attributes, $token);

		if ($type === 'tag-start')
		{
			$expr = $builder->parseExpression('boolean', $attributes['test'], $token);
			$builder->emitCode('if (' . $expr . ') {', $token);
		}
		else
		{
			$builder->emitCode('}', $token);
		}
	}

	public function tpl_output(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('value'), $attributes, $token);

		// @todo: Make this a parseExpression('boolean', ...) to allow for real booleans
		$escape = empty($attributes['escape']) || $attributes['escape'] !== 'false';

		$expr = $builder->parseExpression('normal', $attributes['value'], $token, $escape);

		$builder->emitOutputParam('(' . $expr . ')', $token);
	}

	public function tpl_set(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireAttributes(array('var'), $attributes, $token);

		if (empty($attributes['append']))
			$attributes['append'] = false;

		$var = $builder->parseExpression('variableNotLang', $attributes['var'], $token);

		// If we already have a value, there's no reason for a start and end. Don't confuse the parser!
		if (!empty($attributes['value']) && $type != 'tag-empty')
			$token->toss('tpl_set_invalid_meta');

		if ($type == 'tag-start')
			$builder->emitCode('ob_start();');
		elseif ($type == 'tag-end')
			$builder->emitCode($var . ' ' . ($attributes['append'] ? '.' : '') . '= ob_get_contents(); ob_end_clean();');
		else
		{
			$this->requireAttributes(array('value'), $attributes, $token);

			$value = $builder->parseExpression('normal', $attributes['value'], $token);
			$this->requireAttributes(array('value'), $attributes, $token);
			$builder->emitCode($var . ' ' . ($attributes['append'] ? '.' : '') . '= ' . $value . ';', $token);
		}
	}

	public function tpl_template_push(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);

		$args = array();
		$save = array();
		foreach ($attributes as $k => $v)
		{
			$k = '\'' . addcslashes(Expression::makeVarName($k), '\\\'') . '\'';
			$save[] = $k;
			$args[] = $k . ' => ' . $builder->parseExpression('stringWithVars', $v, $token);
		}

		// First, save the existing variables (if any.)
		$builder->emitCode('global $__toxg_stack; if (!isset($__toxg_stack)) $__toxg_stack = array();', $token);
		$builder->emitCode('array_push($__toxg_stack, compact(' . implode(', ', $save) . '));', $token);

		// Next, overwrite them with the args.
		$builder->emitCode('extract(array(' . implode(', ', $args) . '), EXTR_OVERWRITE);', $token);

		// Just to match things up.
		$this->template_push_level++;
	}

	public function tpl_template_pop(Builder $builder, $type, array $attributes, Token $token)
	{
		$this->requireEmpty($token);
		if ($this->template_push_level <= 0)
			$token->toss('tpl_template_pop_without_push');

		// Just restore the previously saved variables, actually.
		$builder->emitCode('global $__toxg_stack; extract(array_pop($__toxg_stack), EXTR_OVERWRITE);', $token);

		// Just to match things up.
		$this->template_push_level--;
	}

	protected function requireEmpty(Token $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('generic_tpl_must_be_empty', $token->prettyName());
	}

	protected function requireNotEmpty(Token $token)
	{
		if ($token->type === 'tag-empty')
			$token->toss('generic_tpl_must_be_not_empty', $token->prettyName());
	}

	protected function requireAttributes(array $reqs, array $attributes, Token $token)
	{
		if ($token->type === 'tag-end')
			return;

		foreach ($reqs as $req)
		{
			if (!isset($attributes[$req]))
				$token->toss('generic_tpl_missing_required', $req, $token->prettyName(), implode(', ', $reqs));
		}
	}
}