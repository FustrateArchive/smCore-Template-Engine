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

// PSR-0 autoloader, mostly compliant (enough for our purposes)
spl_autoload_register(function($name)
{
	$path = str_replace('\\', '/', $name);

	if (file_exists(__DIR__ . '/library/' . $path . '.php'))
		include(__DIR__ . '/library/' . $path . '.php');
	else
		throw new Exception('Could not find file to autoload: ' . __DIR__ . '/library/' . $path . '.php');
});

class testTheme extends smCore\TemplateEngine\Theme
{
	protected $needs_compile = true;
}

$theme = new testTheme(__DIR__, __DIR__, __DIR__ . '/other_theme', true);

$theme->context['title'] = 'Welcome!';
$theme->context['site_menu'] = array(
	array(
		'label' => 'Home',
		'link' => '#',
		'active' => true,
	),
	array(
		'label' => 'About',
		'link' => '#',
		'active' => false,
	),
	array(
		'label' => 'Stuff',
		'link' => '#',
		'active' => false,
	),
);

$theme->addNamespace('site', 'com.fustrate.site');

$theme->loadLayer('test_templates/main_layer.tpl');
$theme->loadView('test_templates/home.tpl');

// Stuff to use in the layers/views
$theme->loadTemplates('test_templates/templates.tpl');
$theme->loadBlocks('test_templates/block_refs.tpl');

$theme->output();