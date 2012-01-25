<?php

$time_st = microtime(true);

require(dirname(__DIR__) . '/include.php');

$theme = new SampleTheme(__DIR__, __DIR__);
$theme->loadTemplates('templates');
$theme->addTemplate('main');

$theme->context = array(
	'grades' => array(82, 90, 95, 99),
	'alphabet' => 'abcdefghijklmnopqrstuvwxyz',
	'time' => time(),
	'empty_string' => '',
	'empty_array' => array(),
	'nine' => 9,
	'malicious_js' => '<script>document.write("<div class=\"malicious\">This should be escaped!</div>");</script>',
);

$theme->output();

$time_et = microtime(true);

//echo 'Took: ', number_format($time_et - $time_st, 4), ' seconds.';