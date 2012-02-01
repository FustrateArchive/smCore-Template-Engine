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
	protected $_common_vars = array();
	protected $_debugging = false;
	protected $_namespaces = array();

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
	 *
	 * @access protected
	 */
	protected function _createParser($source_file)
	{
		return new Parser($source_file);
	}

	/**
	 * Do stuff before we compile anything. In here, we create all of the tokens and validate
	 * their structure. This includes finding all block and template definitions so we know
	 * what's what when we output code.
	 *
	 * @return array Data about the source we parse here, including template and blocks defined
	 *
	 * @access public
	 */
	public function prepareCompile()
	{
		$this->_parser = $this->_createParser($this->_source['source_file']);
		$this->_parser->setNamespaces($this->_namespaces);

		$this->_parser->parse();

		$this->_tokens = $this->_parser->getTokens();

		// @todo: validate tokens across templates here?

		return array(
			'templates' => $this->_parser->getTemplatesDefined(),
			'blocks' => $this->_parser->getBlocksDefined(),
			'tokens' => $this->_tokens,
		);
	}

	/**
	 * On the second pass, we put the compiled template together and save it.
	 *
	 * @access public
	 */
	public function compile()
	{
		$this->_builder->setDebugging($this->_debugging);
		$this->_builder->setCommonVars($this->_common_vars);
		$this->_builder->setCacheFile($this->_source['cache_file']);

		try
		{
			$this->_builder->run($this->_tokens);
			$this->_builder->finalize();
		}
		// Anything goes wrong, we kill the cache file.
		catch (Exception $e)
		{
			$this->_builder->abort();
			@unlink($this->_source['cache_file']);

			throw $e;
		}
	}
}