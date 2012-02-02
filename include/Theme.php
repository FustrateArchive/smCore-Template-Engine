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

class Theme
{
	protected $_extension = 'tpl';
	protected $_template_dir = '.';
	protected $_compile_dir = '.';
	protected $_parent_theme = null;

	protected $_template_params = array();
	protected $_common_vars = array();

	protected $_mtime = 0;
	protected $_mtime_check = true;
	protected $_needs_compile = false;

	protected $_templatelist = null;
	protected $_templates = array();
	protected $_layers = array();
	protected $_ordered_filenames = array();

	protected $_parsing_doctype = 'xhtml';

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
	public function __construct($template_dir, $compile_dir, $parent_theme = null, $needs_compile = null)
	{
		if (!is_null($needs_compile) && is_bool($needs_compile))
			$this->_needs_compile = $needs_compile;

		$this->_template_dir = $template_dir;
		$this->_compile_dir = $compile_dir;
		$this->_parent_theme = $parent_theme;

		$this->_templatelist = new TemplateList();

		self::$_namespaces = array(
			'tpl' => Compiler::TPL_NSURI,
		);
	}

	/**
	 * Set a variable to be used in a template.
	 *
	 * @param string $key The key to use to access this variable in a template
	 * @param mixed $value The value to be accessed by this parameter
	 *
	 * @access public
	 */
	public function setTemplateParam($key, $value)
	{
		$this->_template_params[$key] = $value;
	}

	/**
	 * Variables which are common to all templates
	 *
	 * @todo: Should be merged with setTemplateParam?
	 *
	 * @param array $vars An array of variable names, i.e. array('context', 'names', 'app')
	 *
	 * @access public
	 */
	public function addCommonVars(array $vars)
	{
		$this->_common_vars = array_merge((array) $vars, $this->_common_vars);
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
	 * Add a template file onto the stack.
	 *
	 * @param string $filename The filename of a template file.
	 *
	 * @access public
	 */
	public function addTemplate($filename)
	{
		$this->_ordered_filenames[] = $filename;
		$this->_templates[] = $filename;
	}

	/**
	 * Reset the list of template files to use.
	 *
	 * @access public
	 */
	public function resetTemplates()
	{
		$this->_templates = array();
		$this->_ordered_filenames = $this->_layers;
	}

	/**
	 * Add a template file to be used as a layer (layers surround the templates)
	 *
	 * @param string $filename The filename of a template file.
	 *
	 * @access public
	 */
	public function addLayer($filename)
	{
		$this->_ordered_filenames[] = $filename;
		$this->_layers[] = $filename;
	}

	/**
	 * Reset the list of layers to use.
	 *
	 * @access public
	 */
	public function resetLayers()
	{
		$this->_layers = array();
		$this->_ordered_filenames = $this->_templates;
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
	 * Tells the template engine to recompile all templates even if none have changed.
	 *
	 * @access public
	 */
	public function recompile()
	{
		$this->_needs_compile = true;
	}

	/**
	 * Read, compile, and call all of the template files.
	 *
	 * @access public
	 */
	public function output()
	{
		$this->_templatelist->setCommonVars($this->_common_vars);
		$filename_classes = array();

		foreach ($this->_ordered_filenames as $filename)
		{
			// Did they give us the full path to a file? This way, we support both a common template directory and modular template directories.
			if (file_exists($filename))
				$source = $filename;
			else if (file_exists($this->_template_dir . '/' . $filename . '.' . $this->_extension))
				$source = $this->_template_dir . '/' . $filename . '.' . $this->_extension;
			else
				throw new \Exception('template_file_not_found');

			$extend_source = null;
			$extend_compiled = null;
			$extend_class_name = null;

			if ($this->_parent_theme !== null)
			{
				$extend_source = $this->_parent_theme . substr(realpath($source), strlen(realpath($this->_template_dir)));

				if (!file_exists($extend_source))
					$extend_source = null;
				else
				{
					$extend_class_name = 'Template__' . preg_replace('~[^a-zA-Z0-9_-]~', '_', $extend_source);
					$extend_compiled = $this->_compile_dir . '/.compiled.' . $extend_class_name . '.php';

					if ($this->_mtime_check && !$this->_needs_compile)
					{
						$this->_mtime = max($this->_mtime, filemtime($extend_source));

						$this->_needs_compile = !file_exists($extend_compiled) || filemtime($extend_compiled) <= $this->_mtime;
					}
				}
			}

			$class_name = 'Template__' . preg_replace('~[^a-zA-Z0-9_-]~', '_', $filename);
			$compiled = $this->_compile_dir . '/.compiled.' . $class_name . '.php';

			$filename_classes[$filename] = $class_name;

			if ($this->_mtime_check && !$this->_needs_compile)
			{
				$this->_mtime = max($this->_mtime, filemtime($source));

				$this->_needs_compile = !file_exists($compiled) || filemtime($compiled) <= $this->_mtime;
			}

			// Add the extended file before the file we want, so that it doesn't choke on extending a non-existent class
			if ($extend_source !== null)
				$this->_templatelist->addTemplate($extend_source, $extend_compiled, $extend_class_name);

			$this->_templatelist->addTemplate($source, $compiled, $class_name, $extend_class_name);
		}

		if ($this->_needs_compile)
		{
//			StandardElements::useIn($this->_templatelist);
			$this->_templatelist->setNamespaces(self::$_namespaces);
			$this->_templatelist->compileAll();
		}

		$this->_templatelist->loadAll();

		foreach ($this->_layers as $filename)
			$this->_templatelist->callTemplateOutput($filename_classes[$filename], 'above', $this->_template_params);

		foreach ($this->_templates as $filename)
		{
			$this->_templatelist->callTemplateOutput($filename_classes[$filename], 'above', $this->_template_params);
			$this->_templatelist->callTemplateOutput($filename_classes[$filename], 'below', $this->_template_params);
		}

		$reversed = array_reverse($this->_layers);

		foreach ($reversed as $filename)
			$this->_templatelist->callTemplateOutput($filename_classes[$filename], 'below', $this->_template_params);
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