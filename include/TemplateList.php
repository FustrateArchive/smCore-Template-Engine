<?php

/**
 * TemplateList
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class TemplateList
{
	protected $_builder = null;
	protected $_namespaces = array();
	protected $_common_vars = array();
	protected $_debugging = false;
	protected $_templates = array();
	protected $_loaded = false;

	protected static $_registered_templates = array();

	/**
	 * Constructor
	 *
	 * @return smCore\TemplateEngine\TemplateList
	 * @access public
	 */
	public function __construct()
	{
	}

	/**
	 * Set the namespaces to use in these templates
	 *
	 * @param 
	 * @return 
	 * @access public
	 */
	public function setNamespaces(array $namespaces)
	{
		$this->_namespaces = $namespaces;
	}

	/**
	 * Set the common variables to use in these templates
	 *
	 * @param array $common_vars An array of variable names
	 * @access public
	 */
	public function setCommonVars(array $common_vars)
	{
		$this->_common_vars = $common_vars;
	}

	/**
	 * Tell the compiler to emit debugging code
	 *
	 * @param boolean $enabled True to enable debugging, false to disable
	 * @access public
	 */
	public function setDebugging($debugging = true)
	{
		$this->_debugging = (boolean) $debugging;
	}

	/**
	 * Add a callback to call when an element is found
	 *
	 * @param string $ns
	 * @param string $name
	 * @param callback $callback
	 *
	 * @access public
	 */
	public function listenEmit($ns, $name, $callback)
	{
		if ($this->_builder === null)
			$this->_builder = new Builder();

		$this->_builder->listenEmit($ns, $name, $callback);
	}

	/**
	 * Add a template to the template list
	 *
	 * @param string $source_file The source file name
	 * @param string $cache_file The cache file name
	 * @param string $class_name The class name that this template will be saved under
	 * @param string $extend_file An array of filenames that can be inherited from
	 *
	 * @access public
	 */
	public function addTemplate($source_file, $cache_file, $class_name, $extend_class_name = null)
	{
		$this->_templates[] = array(
			'source_file' => $source_file,
			'cache_file' => $cache_file,
			'class_name' => $class_name,
			'extend_class_name' => $extend_class_name,
		);
	}

	/**
	 * Set up a compiler for this template
	 *
	 * @param array $template A template from the internal list
	 * @return smCore\TemplateEngine\Compiler
	 * @access protected
	 */
	protected function _setupCompiler($template)
	{
		$compiler = new Compiler($template, $this->_builder);

		$compiler->setNamespaces($this->_namespaces);
		$compiler->setCommonVars($this->_common_vars);
		$compiler->setDebugging($this->_debugging);

		return $compiler;
	}

	/**
	 * Run through each of the files and compile it to a cache file
	 *
	 * @access public
	 */
	public function compileAll()
	{
		if ($this->_builder === null)
			$this->_builder = new Builder();

		$templates = array();

		// Create a Compiler object for each template array
		foreach ($this->_templates as $k => $template)
		{
			$templates[$k] = $template;
			$templates[$k]['compiler'] = $this->_setupCompiler($template);
		}

		// Now loop through and tokenize/validate them all, before we get to the real compiling.
		foreach ($templates as $k => $template)
		{
			$data = $templates[$k]['compiler']->prepareCompile();

			$templates[$k]['tokens'] = $data['tokens'];
			// @todo: check $data for overridden templates, duplicate block names
		}

		// And finally, put everything together
		foreach ($templates as $template)
		{
			$this->_builder->build($template);
		}
	}

	/**
	 * Load all template files and initialize them
	 *
	 * @access public
	 */
	public function loadAll()
	{
		$this->_loaded = true;

		foreach ($this->_templates as $template)
		{
			include_once($template['cache_file']);
			$class_name = 'Template__' . $template['class_name'];
			new $class_name($this);
		}
	}

	/**
	 * 
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	public function callTemplate($name)
	{
	}

	public static function registerTemplate($name, $callback, array $required_attributes = array())
	{
		self::$_registered_templates[$name] = array(
			'required_attributes' => $required_attributes,
			'callback' => $required_attributes,
		);
	}

	public static function addBlockListener($name, $position, $callback)
	{
	}
}