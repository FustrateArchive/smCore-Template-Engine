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
	protected $builder = null;
	protected $namespaces = array();
	protected $common_vars = array();
	protected $debugging = false;
	protected $templates = array();
	protected $template_objects = array();

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
	public function setNamespaces(array $uris)
	{
		$this->namespaces = $uris;
	}

	/**
	 * Set the common variables to use in these templates
	 *
	 * @param array $names An array of variable names
	 * @access public
	 */
	public function setCommonVars(array $names)
	{
		$this->common_vars = $names;
	}

	/**
	 * Tell the compiler to emit debugging code
	 *
	 * @param boolean $enabled True to enable debugging, false to disable
	 * @access public
	 */
	public function setDebugging($debugging = true)
	{
		$this->debugging = (boolean) $debugging;
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
		if ($this->builder === null)
			$this->builder = new Builder();

		$this->builder->listenEmit($ns, $name, $callback);
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
		$this->templates[] = array(
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
		$compiler = new Compiler($template, $this->builder);

		$compiler->setNamespaces($this->namespaces);
		$compiler->setCommonVars($this->common_vars);
		$compiler->setDebugging($this->debugging);

		return $compiler;
	}

	/**
	 * Run through each of the files and compile it to a cache file
	 *
	 * @access public
	 */
	public function compileAll()
	{
		if ($this->builder === null)
			$this->builder = new Builder();

		$templates = array();

		// Create a Compiler object for each template array
		foreach ($this->templates as $k => $template)
			$templates[$k] = $this->_setupCompiler($template);

		// Now loop through and tokenize/validate them all before we...
		foreach ($templates as $template)
		{
			$template->prepareCompile();
			$template->compileFirstPass();
		}

		// ...output code!
		foreach ($this->templates as $k => $template)
		{
			$templates[$k]->compileSecondPass($template['cache_file']);
		}
	}

	/**
	 * Load all template files and initialize them
	 *
	 * @access public
	 */
	public function loadAll()
	{
		foreach ($this->templates as $template)
		{
			include_once($template['cache_file']);
			$this->template_objects['class_name'] = new $template['class_name'];
		}
	}
}