<?php

// .compiled.home_fustrate_public_html_dev_toxg_templates_tpl.php
// There would be no comments in the outputted code.

// extract() makes it so we can use the variables natively, $context['var'], as opposed to $__toxg_params['context']['var']
// the final line of each function, compact(...) puts everything back into $__toxg_params so that the next portion of code can use it.

// In another file, we can have
// class Templates__other_template_tpl extends Templates__home_fustrate_public_html_dev_toxg_templates_tpl
class Templates__home_fustrate_public_html_dev_toxg_templates_tpl extends smCore\TemplateEngine\Template
{
	public function __construct()
	{
		parent::__construct();

		// 'namespace:name' => array('method_name', 'what it does based on where tpl:content is'),
		// Valid values are 'before', 'after', 'beforecontent', 'aftercontent', 'aroundcontent', 'replace'
		$this->_usesBlocks(array(
			'site:head' => array('site_head', 'before'),
			'site:body' => array('site_body', 'after'),
			'site:footer' => array('site_footer', 'aftercontent'),
			'site:title' => array('site_title', 'replace'),
		);

		// 'namespace:name' => array('required', 'attributes'),
		$this->_definesTemplates(array(
			'site:box' => array('title'),
		));
	}

	public function output__above(&$__toxg_params = array())
	{
		extract($__toxg_params, EXTR_SKIP);

		echo '<!DOCTYPE html>
<html>
	<head>';

		// @todo: start block code
		

		echo '
		<title>Blocks: ';

		if (!empty($context['nothing']))
			echo $context['nothing'];
		else
			echo 'N/A';

		echo '</title>';

		// @todo: end block code

		echo '
	</head>
	<body>';

		// @todo: start block code

		echo '
		Hello World';

		if (!empty($context['nums']))
		{
			foreach ($context['nums'] as $num)
			{
				$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')))
				$this->_callTemplate('site:rounded', 'above', $__toxg_params);
				extract($__toxg_params, EXTR_OVERWRITE);

				echo $num;

				$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')))
				$this->_callTemplate('site:rounded', 'below', $__toxg_params);
				extract($__toxg_params, EXTR_OVERWRITE);
			}
		}

		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}

	public function output__below(&$__toxg_params = array())
	{
		// @todo: end block code

		extract($__toxg_params, EXTR_SKIP);
		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}

	public function block__site_head__above(&$__toxg_params = array())
	{
		extract($__toxg_params, EXTR_SKIP);
		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}

	public function block__site_head__below(&$__toxg_params = array())
	{
		extract($__toxg_params, EXTR_SKIP);
		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}

	public function template__site_box__above(&$__toxg_params = array())
	{
		extract($__toxg_params, EXTR_SKIP);

		echo '
			<div class="box">';

		if (!empty($title))
			echo '<h1>', $title, '</h1>';

		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}

	public function template__site_box__below(&$__toxg_params = array())
	{
		extract($__toxg_params, EXTR_SKIP);

		echo '
			</div>';

		$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array('__toxg_args', '__toxg_argstack', '__toxg_stack', '__toxg_params', '__toxg_func', '__toxg_error_handler')));
	}
}