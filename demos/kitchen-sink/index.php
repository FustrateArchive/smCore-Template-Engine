<?php

include(dirname(__DIR__) . '/include.php');

$theme = new smCore\TemplateEngine\Theme(__DIR__, __DIR__, null, true);

$theme->addNamespace('site', 'com.fustrate.site');

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

$theme->loadLayer(__DIR__ . '/main_layer.tpl');
$theme->loadView(__DIR__ . '/home.tpl');

// Stuff to use in the layers/views
$theme->loadMacros(__DIR__ . '/macros.tpl');
$theme->loadBlocks(__DIR__ . '/block_refs.tpl');

$theme->output();