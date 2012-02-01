<?php

ini_set('display_errors', 1);

include_once(__DIR__ . '/include/index.php');

class testTheme extends smCore\TemplateEngine\Theme
{
	protected $needs_compile = true;
	public $context = array();

	public function output()
	{
		$this->setTemplateParam('context', $this->context);
		$this->addCommonVars(array('context'));
		parent::output();
	}
}

$theme = new testTheme(__DIR__, __DIR__, __DIR__ . '/other_theme', true);

$theme->loadTemplate('templates.tpl');
$theme->output();
