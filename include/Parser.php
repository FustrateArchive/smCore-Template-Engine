<?php

/**
 * Parser
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Parser
{
	protected $_source = null;

	public function __construct($source)
	{
		if ($source instanceof Source)
		{
			$this->_source = $source;
			$this->_source->initialize();
		}
		else
			$this->_source = new SourceFile($source);
	}

	public function setNamespaces(array $uris)
	{
		$this->_source->setNamespaces($uris);
	}

	public function listen($type, $callback)
	{
		$this->_listeners[$type][] = $callback;
	}

	protected function fire($type, Token $token)
	{
		if (empty($this->listeners[$type]))
			return;

		foreach ($this->listeners[$type] as $callback)
		{
			$result = call_user_func($callback, $token, $this);
			if ($result === false)
				break;
		}
	}
}