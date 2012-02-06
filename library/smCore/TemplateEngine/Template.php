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
	/**
	 * Create a new Template object.
	 *
	 * @access public
	 */
	public function __construct()
	{
	}

	/**
	 * Tell the Compiler that we'll want to output stuff if we see these blocks.
	 *
	 * @param array $blocks An array of blocks and hwo they should be called.
	 *
	 * @access protected
	 */
	protected function _addBlockListeners(array $blocks = array())
	{
		foreach ($blocks as $block => $data)
			TemplateList::addBlockListener($data[0], $data[1], $this);
	}

	protected function _fireBlockEvent($name, $side, $default_content, $context)
	{
		TemplateList::fireBlockEvent($name, $side, $default_content, $context);
	}

	/**
	 * Tell the TemplateList that this object "owns" certain template names
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	protected function _registerTemplates(array $templates = array())
	{
		foreach ($templates as $name)
			TemplateList::registerTemplate($name, $this);
	}

	/**
	 * Call a template from inside a view, layer, block, or template
	 *
	 * @param string $name
	 * @param string $side
	 * @param array $parameters8
	 *
	 * @access public
	 */
	public function callTemplate($name, $side, $parameters)
	{
		TemplateList::callTemplate($name, $side, $parameters);
	}
}