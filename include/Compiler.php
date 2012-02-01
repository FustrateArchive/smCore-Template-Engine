<?php

/**
 * Compiler
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Compiler
{
	const TPL_NSURI = 'urn:toxg:template';

	protected $_source = null;
	protected $_parser = null;

	protected $_builder = null;
	protected $_namespaces = array();
	protected $_common_vars = array();
	protected $_debugging = false;
	protected $_compile_source = true;
	protected $_compile_extend = true;

	/**
	 * Create a new Compiler object. We'll have one per file.
	 *
	 * @param string $source_file
	 * @param mixed $builder
	 *
	 * @return smCore\TemplateEngine\Compiler
	 * @access public
	 */
	public function __construct($source_data, $builder = null)
	{
		$this->_builder = $builder !== null ? $builder : new Builder();
		$this->_source = $source_data;
	}

	/**
	 * Set the namespaces we should recognize as special elements
	 *
	 * @param array $namespaces
	 *
	 * @access public
	 */
	public function setNamespaces(array $namespaces)
	{
		$this->_namespaces = $namespaces;
	}

	/**
	 * Set the variables that are going to be passed to each template no matter what
	 *
	 * @param array $common_vars
	 *
	 * @access public
	 */
	public function setCommonVars(array $common_vars)
	{
		$this->_common_vars = $common_vars;
	}

	/**
	 * Tell the template engine to output debug code
	 *
	 * @param boolean $debugging
	 *
	 * @access public
	 */
	public function setDebugging($debugging = true)
	{
		$this->_debugging = (boolean) $debugging;
	}

	/**
	 * Creates a new Parser object
	 *
	 * @param 
	 * @return smCore\TemplateEngine\Parser
	 * @access protected
	 */
	protected function _createParser($source_file)
	{
		return new Parser($source_file);
	}

	/**
	 * Do stuff before we compile anything
	 *
	 * @access public
	 */
	public function prepareCompile()
	{
		$this->_parser = $this->_createParser($this->_source['source_file']);
		$this->_parser->setNamespaces($this->_namespaces);
	}

	/**
	 * On the first pass, we create all of the tokens and validate their structure. This includes
	 * finding all block and template definitions so we know what's what when we output code.
	 *
	 * @access public
	 */
	public function compileFirstPass()
	{
		$this->_parser->parse();
	}

	/**
	 * On the second pass, we put the compiled template together and save it
	 *
	 * @access public
	 */
	public function compileSecondPass()
	{
	}
}