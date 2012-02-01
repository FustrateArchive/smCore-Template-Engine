<?php

ini_set('display_errors', 1);

// Dirty exception handler, to make things readable instead of throwing them all on one line.
set_exception_handler(function($exception)
{
	echo '<h1>Exception Thrown</h1>

<strong>Message:</strong> ' . $exception->getMessage() . '<br />
<br />
<strong>File:</strong> ' . $exception->getFile() . '<br />
<br />
<strong>Line:</strong> ' . $exception->getLine() . '<br />
<br />
<strong>Trace:</strong><br />
<pre style="background: #eaeaea; border: 1px solid #555; padding: .5em; overflow: scroll; max-height: 300px;">' . print_r($exception->getTrace(), true) . '</pre>';

	die();
});

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

$theme->addNamespace('site', 'com.fustrate.site');
$theme->loadTemplate('templates.tpl');
$theme->loadTemplate('other_stuff.tpl');
$theme->output();
