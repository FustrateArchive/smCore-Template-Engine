<?php

/**
 * Template
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Template
{
	protected $_compiler = null;

	/**
	 * Create a new Template object.
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	public function __construct(Compiler $compiler)
	{
		$this->_compiler = $compiler;
	}

	/**
	 * Tell the Compiler that we'll want to output stuff if we see these blocks.
	 *
	 * @param array $blocks An array of blocks and hwo they should be called.
	 *
	 * @access protected
	 */
	protected function _usesBlocks(array $blocks = array())
	{
		$this->_compiler->registerUsingBlocks($this, $blocks);
	}

	/**
	 * Tell the Compiler that this template file "owns" certain template names
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	protected function _definesTemplates(array $templates = array())
	{
		$this->_compiler->registerTemplates($this, $templates);
	}

	/**
	 * Call a template from inside a template file
	 *
	 * @param string $name
	 * @param string $side
	 * @param array $__toxg_params
	 *
	 * @access protected
	 */
	protected function _callTemplate($name, $side, &$__toxg_params)
	{
		// @todo: the calling code
	}

	/**
	 * Method to output the template's top part. Redefined in child classes.
	 *
	 * @param array $__toxg_params Array of )'var_name' => $value) to extract in the template
	 *
	 * @access public
	 */
	public function output__above(&$__toxg_params = array())
	{
	}

	/**
	 * Method to output the template's bottom part, if it had a <tpl:content />. Redefined in child classes.
	 *
	 * @param array $__toxg_params Array of )'var_name' => $value) to extract in the template
	 *
	 * @access public
	 */
	public function output__below(&$__toxg_params = array())
	{
	}
}