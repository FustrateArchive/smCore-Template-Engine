<?php

// .compiled.home_fustrate_public_html_dev_toxg_templates_tpl.php
// There would be no comments in the outputted code.

// extract() makes it so we can use the variables natively, $context['var'], as opposed to $__tpl_params['context']['var']
// the final line of each function, compact(...) puts everything back into $__tpl_params so that the next portion of code can use it.

// In another file, we can have
// class Templates__other_template_tpl extends Templates__home_fustrate_public_html_dev_toxg_templates_tpl
class Templates__home_fustrate_public_html_dev_toxg_templates_tpl extends smCore\TemplateEngine\Template
{
	public function __construct()
	{
		parent::__construct();

		// 'namespace:name' => array('method_name', 'what it does based on where tpl:content is'),
		// Valid values are 'before', 'after', 'both', 'replace'
		$this->_addBlockListeners(array(
			'site:head' => array('site_head', 'before'),
			'site:body' => array('site_body', 'after'),
			'site:footer' => array('site_footer', 'both'),
			'site:title' => array('site_title', 'replace'),
		);

		// 'namespace:name' => array('required', 'attributes'),
		$this->_definesTemplates(array(
			'site:box' => array('title'),
		));
	}

	public function output__above(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '<!DOCTYPE html>
<html>
	<head>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
		$this->_runBlock('site:head', $__tpl_params);
		extract($__tpl_params, EXTR_OVERWRITE);

		echo '
	</head>
	<body>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
		$this->_startBlock('site:body', $__tpl_params);
		// We don't need to extract/compact again because it's at the end of the function.
	}

	// Main template stuff (not a template definition or block usage
	public function output__below(&$__tpl_params = array())
	{
		// We don't need to compact/extract here because it's at the start of the function.
		$this->_endBlock('site:body', $__tpl_params);
		extract($__tpl_params, EXTR_OVERWRITE);

		echo '
	</body>
</html>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}

	// The template actually uses its own block calls
	public function block__site_head__above(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '
		<title>Blocks: ', (!empty($context['nothing']) ? $context['nothing'] : 'N/A'), '</title>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}

	// It wasn't around <tpl:content /> so we don't need to do anything.
	public function block__site_head__below(&$__tpl_params = array())
	{
	}

	public function block__site_body__above(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '
		Hello World';

		if (!empty($context['nums']))
		{
			foreach ($context['nums'] as $num)
			{
				$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
				$this->_callTemplate('site:rounded', 'above', $__tpl_params);
				extract($__tpl_params, EXTR_OVERWRITE);

				echo $num;

				$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
				$this->_callTemplate('site:rounded', 'below', $__tpl_params);
				extract($__tpl_params, EXTR_OVERWRITE);
			}
		}

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}

	public function block__site_body__below(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '
		<p>
			More stuff after the tpl:content, but inside the body block.
		</p>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}













	public function template__site_box__above(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '
			<div class="box">';

		if (!empty($title))
			echo '<h1>', $title, '</h1>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}

	public function template__site_box__below(&$__tpl_params = array())
	{
		extract($__tpl_params, EXTR_SKIP);

		echo '
			</div>';

		$__tpl_params = compact(array_diff(array_keys(get_defined_vars()), array('__tpl_args', '__tpl_argstack', '__tpl_stack', '__tpl_params', '__tpl_func', '__tpl_error_handler')));
	}
}