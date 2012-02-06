<?php 

// PSR-0 autoloader, mostly compliant (enough for our purposes)
spl_autoload_register(function($name)
{
	$path = str_replace('\\', '/', $name);

	if (file_exists(dirname(__DIR__) . '/library/' . $path . '.php'))
		include(dirname(__DIR__) . '/library/' . $path . '.php');
	else
		throw new Exception('Could not find file to autoload: ' . dirname(__DIR__) . '/library/' . $path . '.php');
});