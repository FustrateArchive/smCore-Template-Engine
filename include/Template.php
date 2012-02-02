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

abstract class Template
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
		{
			if ($data[1] === 'above' || $data[1] === 'below')
			{
				TemplateList::addBlockListener($data[0], $data[1], array($this, 'block__' . $data[0] . '__' . $data[1]));
			}
			else if ($data[1] === 'both')
			{
				TemplateList::addBlockListener($data[0], 'above', array($this, 'block__' . $data[0] . '__above'));
				TemplateList::addBlockListener($data[0], 'below', array($this, 'block__' . $data[0] . '__below'));
			}
			else if ($data[1] === 'replace')
			{
				TemplateList::addBlockListener($data[0], 'replace', array($this, 'block__' . $data[0]));
			}
		}
	}

	/**
	 * Tell the TemplateList that this template file "owns" certain template names
	 *
	 * @param 
	 * @return 
	 * @access 
	 */
	protected function _registerTemplates(array $templates = array())
	{
		foreach ($templates as $name => $required_attributes)
			TemplateList::registerTemplate($name, array($this, 'template__' . $name), $required_attributes);
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
	abstract public function output__above(&$__toxg_params = array());

	/**
	 * Method to output the template's bottom part, if it had a <tpl:content />. Redefined in child classes.
	 *
	 * @param array $__toxg_params Array of )'var_name' => $value) to extract in the template
	 *
	 * @access public
	 */
	abstract public function output__below(&$__toxg_params = array());
}