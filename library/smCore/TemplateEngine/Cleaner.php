<?php

/**
 * Cleaner Abstract
 *
 * Puts the tokens in the right order and adds some faux tokens along the way.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

abstract class Cleaner
{
	public function __construct()
	{
	}

	abstract public function clean(array &$tokens, $source_filename);
}