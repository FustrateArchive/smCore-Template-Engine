<?php

/**
 * Elements
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

abstract class Elements
{
	public function __construct()
	{
	}

	abstract public function setBuildListeners($template);

	protected function _requireEmpty(Token $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('generic_tpl_must_be_empty', $token->prettyName());
	}

	protected function _requireNotEmpty(Token $token)
	{
		if ($token->type === 'tag-empty')
			$token->toss('generic_tpl_must_be_not_empty', $token->prettyName());
	}

	protected function _requireAttributes(array $reqs, array $attributes, Token $token)
	{
		if ($token->type === 'tag-end')
			return;

		foreach ($reqs as $req)
		{
			if (!isset($attributes[$req]))
				$token->toss('generic_tpl_missing_required', $req, $token->prettyName(), implode(', ', $reqs));
		}
	}

	protected function _defaultAttribute($name, $default, array $attributes, $type, Builder $builder, Token $token)
	{
		if (empty($attributes[$name]))
			return $default;

		return $builder->parseExpression($type, $attributes[$name], $token);
	}
}