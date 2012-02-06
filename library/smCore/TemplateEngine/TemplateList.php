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
	protected $_debugging = false;
	protected $_files = array();
	protected $_ordered_files = array();
	protected $_loaded = false;
	protected $_loaded_classes = array();

	protected static $_registered_macros = array();
	protected static $_block_listeners = array();

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
	 * @param string $type The file of file to parse this as (layer, view, macros, blocks)
	 * @param string $source_file The source file name
	 * @param string $cache_file The cache file name
	 * @param string $class_name The class name that this template will be saved under
	 * @param string $extend_file An array of filenames that can be inherited from
	 *
	 * @access public
	 */
	public function addFile($type, $source_file, $cache_file, $class_name, $extend_class_name = null)
	{
		$this->_files[$type][] = array(
			'source_file' => $source_file,
			'cache_file' => $cache_file,
			'class_name' => $class_name,
			'extend_class_name' => $extend_class_name,
		);

		$this->_ordered_files[] = array(
			'source_file' => $source_file,
			'cache_file' => $cache_file,
			'class_name' => $class_name,
		);
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

		$this->_builder->setDebugging($this->_debugging);

		Parser::setNamespaces($this->_namespaces);

		if (!empty($this->_files['macros']))
		{
			$cleaner = new Cleaner\Macros();

			foreach ($this->_files['macros'] as $file)
			{
				$parser = new Parser\Macros($file['source_file']);
				$parser->parse();

				$file['tokens'] = $parser->getTokens();

				$cleaner->clean($file['tokens'], $file['source_file']);

				$this->_builder->build($file);
			}
		}

		if (!empty($this->_files['blocks']))
		{
			$cleaner = new Cleaner\Blocks();

			foreach ($this->_files['blocks'] as $file)
			{
				$parser = new Parser\Blocks($file['source_file']);
				$parser->parse();

				$file['tokens'] = $parser->getTokens();

				$cleaner->clean($file['tokens'], $file['source_file']);

				$this->_builder->build($file);
			}
		}

		if (!empty($this->_files['views']))
		{
			$cleaner = new Cleaner\View();

			foreach ($this->_files['views'] as $file)
			{
				$parser = new Parser\View($file['source_file']);
				$parser->parse();

				$file['tokens'] = $parser->getTokens();

				$cleaner->clean($file['tokens'], $file['source_file']);

				$this->_builder->build($file);
			}
		}

		if (!empty($this->_files['layers']))
		{
			$cleaner = new Cleaner\Layer();

			foreach ($this->_files['layers'] as $file)
			{
				$parser = new Parser\Layer($file['source_file']);
				$parser->parse();

				$file['tokens'] = $parser->getTokens();

				$cleaner->clean($file['tokens'], $file['source_file']);

				$this->_builder->build($file);
			}
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

		foreach ($this->_ordered_files as $file)
		{
			include_once($file['cache_file']);
			$this->_loaded_classes[$file['class_name']] = new $file['class_name']();
		}
	}

	/**
	 * Output a view file by its filename.
	 *
	 * @param string $class_name Name of the class we're calling the output function for.
	 * @param array $context The context variables the view can use.
	 *
	 * @access public
	 */
	public function callView($class_name, array $context)
	{
		$this->_loaded_classes[$class_name]->view($context);
	}

	/**
	 * Output the top or bottom of a layer file by its filename.
	 *
	 * @param string $class_name The class name to call the layer from
	 * @param string $side Which side to call, 'above' or 'below'
	 * @param array $context 
	 *
	 * @access public
	 */
	public function callLayer($class_name, $side, array $context)
	{
		$method = 'layer_' . $side;
		$this->_loaded_classes[$class_name]->{$method}($context);
	}

	/**
	 * Call a macro from inside a template.
	 *
	 * @param string $name The name of the macro to call, such as 'badges:mini_grid'
	 * @param string $side Which side to call, 'above', 'below', or 'both'
	 * @param array $parameters
	 *
	 * @access public
	 */
	public static function callMacro($name, $side, array $parameters = array())
	{
		if (array_key_exists($name, self::$_registered_macros))
		{
			if ($side === 'above' || $side === 'both')
			{
				$method = 'macro_' . str_replace(':', '_', $name) . '_above';
				self::$_registered_macros[$name]->{$method}($parameters);
			}

			if ($side === 'below' || $side === 'both')
			{
				$method = 'macro_' . str_replace(':', '_', $name) . '_below';
				self::$_registered_macros[$name]->{$method}($parameters);
			}
		}
	}

	/**
	 * Claim ownership of a specific macro name.
	 *
	 * @param string $name The name of the macro to claim, such as "site:menu"
	 * @param smCore\TemplateEngine\Template $owner The class which claims ownership.
	 *
	 * @access public
	 */
	public static function registerMacro($name, Template $owner)
	{
		self::$_registered_macros[$name] = $owner;
	}

	/**
	 * Add a block listener, to modify a pre-existing block if we run into it.
	 *
	 * @param string $name The name of the block to add a listener for
	 * @param string $position What position this listener attaches to, "above" "below" or "replace"
	 * @param callback $callback
	 *
	 * @access public
	 */
	public static function addBlockListener($name, $position, $callback)
	{
		// Only one "replace" listener - the most recent one
		if ($position === 'replace')
			self::$_block_listeners[$name]['replace'] = $callback;
		else
			self::$_block_listeners[$name][$position][] = $callback;
	}

	/**
	 * Fire a block event from inside a template file
	 *
	 * @param string $name The name of the block listener to fire, such as "site:profile"
	 * @param string $side Which side we're looking for, "above" "below" or "replace"
	 * @param callback $default_content The default content for this block.
	 * @param array $parameters
	 *
	 * @access public
	 */
	public static function fireBlockEvent($name, $side, $default_content, array $parameters)
	{
		// If there are no listeners, just pass through
		if (empty(self::$_block_listeners[$name]))
		{
			$default_content();
			return;
		}

		// Allow one full-block replacement. We'll only do this for 'above' or 'both'
		if ($side !== 'below' && !empty(self::$_block_listeners[$name]['replace']))
			$default_content = array(self::$_block_listeners[$name]['replace'], 'block_' . str_replae(':', '_', $name) . '_replace');

		if ($side === 'above' || $side === 'both')
		{
			$method = 'block_' . str_replace(':', '_', $name) . '_above';

			if (!empty(self::$_block_listeners[$name]['above']))
			{
				foreach (self::$_block_listeners[$name]['above'] as $listener)
					$listener->{$method}($parameters);
			}
		}

		$default_content();

		if ($side === 'below' || $side === 'both')
		{
			$method = 'block_' . str_replace(':', '_', $name) . '_below';

			if (!empty(self::$_block_listeners[$name]['below']))
			{
				foreach (self::$_block_listeners[$name]['below'] as $listener)
					$listener->{$method}($parameters);
			}
		}
	}

	/**
	 * Set listeners for tpl: elements during the build phase, to emit custom code into the compiled files.
	 *
	 * @param smCore\TemplateEngine\Elements $elements
	 *
	 * @access public
	 */
	public function useElements(Elements $elements)
	{
		$elements->setBuildListeners($this);
	}
}