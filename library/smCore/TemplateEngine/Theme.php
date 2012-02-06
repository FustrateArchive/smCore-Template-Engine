<?php

/**
 * Theme
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;
use smCore\TemplateEngine\Elements\Standard as StandardElements;

class Theme
{
	public $context = array();

	protected $_extension = 'tpl';
	protected $_template_dir = '.';
	protected $_compile_dir = '.';
	protected $_parent_theme = null;

	protected $_mtime = 0;
	protected $_mtime_check = true;
	protected $_recompile = false;

	protected $_templatelist = null;

	protected $_files = array();

	protected static $_build_listeners = array();

	// Primary namespace definitions
	protected static $_namespaces = array();

	// Fallback namespaces can be overridden by primary definitions
	protected static $_fallback_namespaces = array();

	/**
	 * Construct a new theme object
	 *
	 * @param string $template_dir Where to find the templates included with this theme
	 * @param string $compile_dir Where to save the compiled templates to
	 * @param array $inherited_dirs An array of strings, directories in which to search for missing templates
	 * @param boolean $needs_compile Set to true in order to recompile templates on every page load.
	 *
	 * @access public
	 */
	public function __construct($template_dir, $compile_dir, $parent_theme = null, $recompile = null)
	{
		if (is_bool($recompile))
			$this->_recompile = $recompile;

		$this->_template_dir = $template_dir;
		$this->_compile_dir = $compile_dir;
		$this->_parent_theme = $parent_theme;

		$this->_templatelist = new TemplateList();

		self::$_namespaces = array(
			'tpl' => Parser::TPL_NSURI,
		);
	}

	/**
	 * Add a namespace to be recognized by the parser. Namespaces not in this array are passed
	 * over as "content" tokens.
	 *
	 * @param string $name The namespace to use, i.e. "forum"
	 * @param string $nsuri The URI for this namespace, i.e. "org.simplemachines.forum"
	 *
	 * @access public
	 */
	public function addNamespace($name, $nsuri = null, $primary = true)
	{
		self::$_namespaces[$name] = is_null($nsuri) ? 'urn:ns:' . $name : $nsuri;
	}

	/**
	 * Listen for an element in order to hook into it.
	 *
	 * @param string $nsuri Namespace to listen for, i.e. "site"
	 * @param string $name Name to listen for, i.e. "my_template"
	 * @param callback $callback Something callable, to tell when we find this element.
	 *
	 * @access public
	 */
	public function listenEmit($nsuri, $name, $callback)
	{
		return $this->_templatelist->listenEmit($nsuri, $name, $callback);
	}

	/**
	 * Add a view file onto the stack.
	 *
	 * @param string $filename The filename of a template file.
	 *
	 * @access public
	 */
	public function loadView($filename)
	{
		$this->_files['views'][] = $filename;
	}

	/**
	 * Reset the list of view files to use.
	 *
	 * @access public
	 */
	public function resetViews()
	{
		$this->_files['views'] = array();
	}

	/**
	 * Add a template file to be used as a layer (layers surround the templates)
	 *
	 * @param string $filename The filename of a template file.
	 *
	 * @access public
	 */
	public function loadLayer($filename)
	{
		$this->_files['layers'][] = $filename;
	}

	/**
	 * Reset the list of layers to use.
	 *
	 * @access public
	 */
	public function resetLayers()
	{
		$this->_files['layers'] = array();
	}

	/**
	 * Load templates that we can reuse elsewhere.
	 *
	 * @param string $filename The filename to load
	 *
	 * @access public
	 */
	public function loadTemplates($filename)
	{
		$this->_files['templates'][] = $filename;
	}

	/**
	 * Reset the list of template files to load.
	 *
	 * @access public
	 */
	public function resetTemplates()
	{
		$this->_files['templates'] = array();
	}

	/**
	 * Load block references to override or extend block definitions
	 *
	 * @param string $filename The filename to load
	 *
	 * @access public
	 */
	public function loadBlocks($filename)
	{
		$this->_files['blocks'][] = $filename;
	}

	/**
	 * Reset the list of blocks to override.
	 *
	 * @access public
	 */
	public function resetBlocks()
	{
		$this->_files['blocks'] = array();
	}

	/**
	 * Tells the template engine to recompile all templates even if none have changed.
	 *
	 * @access public
	 */
	public function recompile()
	{
		$this->_recompile = true;
	}

	/**
	 * Read, compile, and call all of the template files.
	 *
	 * @access public
	 */
	public function output()
	{
		$filename_classes = array();

		$files = array(
			'layers' => $this->_layers,
			'views' => $this->_views,
			'templates' => $this->_templates,
			'blocks' => $this->_blocks,
		);

		foreach ($this->_files as $type => $files)
		{
			foreach ($files as $filename)
			{
				// Did they give us the full path to a file? This way, we support both a common template directory and modular template directories.
				if (file_exists($filename))
					$source = realpath($filename);
				else if (file_exists($this->_template_dir . '/' . $filename . '.' . $this->_extension))
					$source = realpath($this->_template_dir . '/' . $filename . '.' . $this->_extension);
				else
					throw new \Exception('template_file_not_found');

				$extend_source = null;
				$extend_compiled = null;
				$extend_class_name = 'smCore\TemplateEngine\Template';

				// @todo: Extend parent theme

				$class_name = 'Template__' . preg_replace('~[^a-zA-Z0-9_-]~', '_', $filename);
				$compiled = $this->_compile_dir . '/.compiled.' . $class_name . '.php';

				$filename_classes[$filename] = $class_name;

				if ($this->_mtime_check && !$this->_recompile)
					$this->_recompile = !file_exists($compiled) || filemtime($compiled) <= filemtime($source);

				// Add the extended file before the file we want, so that it doesn't choke on extending a non-existent class
				if ($extend_source !== null)
					$this->_templatelist->addFile($type, $extend_source, $extend_compiled, $extend_class_name);

				$this->_templatelist->addFile($type, $source, $compiled, $class_name, $extend_class_name);
			}
		}

		if ($this->_recompile)
		{
			$this->_templatelist->setNamespaces(self::$_namespaces);

			// @todo: allow for more sets like this
			$this->_templatelist->useElements(new StandardElements());

			$this->_templatelist->compileAll();
		}

		$this->_templatelist->loadAll();

		if (!empty($this->_files['layers']))
		{
			foreach ($this->_files['layers'] as $filename)
				$this->_templatelist->callLayer($filename_classes[$filename], 'above', $this->context);
		}

		foreach ($this->_files['views'] as $filename)
			$this->_templatelist->callView($filename_classes[$filename], $this->context);

		if (!empty($this->_files['layers']))
		{
			$reversed = array_reverse($this->_files['layers']);

			foreach ($reversed as $filename)
				$this->_templatelist->callLayer($filename_classes[$filename], 'below', $this->context);
		}
	}

	/**
	 * Add an external callback to be called when we encounter a certain block.
	 *
	 * @param string $name The name of the block, i.e. "smf:post"
	 * @param callback $callback
	 *
	 * @access public
	 */
	public function addBlockListener($name, $callback)
	{
		if (!is_callable($callback))
			throw new \Exception('theme_invalid_block_callback');

		Compiler::addBlockListener($name, $callback);
	}

	/**
	 * Find the namespace URI (i.e. "org.simplemachines.forum") for a given namespace.
	 *
	 * @param string $namespace The namespace to look for.
	 * @return string The namespace URI for this namespace.
	 *
	 * @access public
	 */
	public static function getNamespace($namespace)
	{
		return self::$_namespaces[$namespace];
	}
}