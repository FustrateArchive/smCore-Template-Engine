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
	protected $_debugging = true;
	protected $_common_vars = array();

	protected $_data = null;
	protected $_data_close = false;

	protected $last_file = null;
	protected $last_line = 1;
	protected $prebuilder = null;
	protected $last_template = null;
	protected $has_emitted = false;
	protected $disable_emit = false;
	protected $emit_output = array();
	protected $listeners = array();

	public function __construct()
	{
	}

	public function __destruct()
	{
		$this->abort();
	}

	public function setDebugging($enabled)
	{
		$this->_debugging = (boolean) $enabled;
	}

	public function setCommonVars(array $names)
	{
		$this->_common_vars = $names;
	}

	public function setCacheFile($cache_file)
	{
	}

	public function abort()
	{
		// Release the file so it isn't left open until the request end.
		if ($this->_data !== null && $this->_data_close)
			@fclose($this->_data);
	}


}