<?php

include(dirname(__DIR__) . '/include.php');

$theme = new smCore\TemplateEngine\Theme(__DIR__, __DIR__, __DIR__ . '/other_theme', true);

$theme->context['site_name'] = 'my site';

$theme->loadLayer(__DIR__ . '/html_layer.tpl');
$theme->loadView(__DIR__ . '/my_view.tpl');

$theme->output();