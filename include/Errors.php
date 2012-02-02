<?php

/**
 * Errors
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class Errors
{
	protected static $_prior = null;
	protected static $_retain = 0;
	protected static $_files = array();

	/**
	 * When we create one of these, it registers as the error handler
	 *
	 * @access public
	 */
	public function __construct()
	{
		self::register();
	}

	/**
	 * When we destroy this object, it unregisters its error handler
	 *
	 * @access public
	 */
	public function __destruct()
	{
		self::restore();
	}

	/**
	 * Register the internal error handler
	 *
	 * @access public
	 */
	public static function register()
	{
		if (self::$_retain++ > 0)
			return;

		self::$_prior = set_error_handler(array(__CLASS__, 'handler'));
	}

	/**
	 * Restore the previous error handler
	 *
	 * @access public
	 */
	public static function restore()
	{
		if (--self::$_retain > 0)
			return;

		restore_error_handler();
	}

	/**
	 * Reset the list of files we need to worry about
	 *
	 * @access public
	 */
	public static function reset()
	{
		self::$_files = array();
	}

	/**
	 * Add a mapping for our fake filename and line to the real set
	 *
	 * @param string $file The filename we're remapping to
	 * @param int $line The line number we're pretending to be
	 *
	 * @access public
	 */
	public static function remap($file, $line)
	{
		list ($trace) = debug_backtrace();

		// Make sure the path we have is absolute.
		$from_file = realpath($trace['file']);
		$from_line = (int) $trace['line'];

		self::$_files[$from_file][$from_line] = array(
			'file' => $file,
			'line' => (int) $line,
		);
	}

	/**
	 * Our (public) custom error handler
	 *
	 * @param int $errno Integer value of an error constant
	 * @param string $errstr Basic message of what went wrong
	 * @param string $file File in which the error occurred
	 * @param int $line Line on which the error occurred
	 * @param array $ctx Error context
	 *
	 * @access public
	 */
	public static function handler($errno, $errstr, $file, $line, $ctx)
	{
		$from_file = realpath($file);

		if (isset(self::$_files[$from_file]))
		{
			$mappings = self::$_files[$from_file];

			// Put the highest first so we can stop at the right one.
			krsort($mappings);
			foreach ($mappings as $from_line => $mapping)
			{
				// The first one we hit that's lower is the correct one (higher won't be.)
				if ($from_line <= $line)
				{
					$file = $mapping['file'];
					$line = $mapping['line'] + ($line - $from_line);
				}
			}

			if (self::$_prior === null)
				return self::_defaultHandler($errno, $errstr, $file, $line);
		}

		// Call the old error handler with the updated file and line.
		if (self::$_prior !== null)
			return call_user_func(self::$_prior, $errno, $errstr, $file, $line, $ctx);

		return false;
	}

	/**
	 * Handle the error, if we were doing anything with them in the first place
	 *
	 * @param int $errno
	 * @param string $errstr
	 * @param string $file
	 * @param int $line
	 *
	 * @access protected
	 */
	protected static function _defaultHandler($errno, $errstr, $file, $line)
	{
		// We're only going to be custom if there was no prior...
		// This shouldn't ever really happen anyway.
		$setting = @ini_get('display_errors');

		if ($setting == 0 && $setting !== 'stderr')
			return true;

		// We need some way to get the pretty error, even if there are new errors later.
		$consts = get_defined_constants();
		$error_type = $errno;

		foreach ($consts as $name => $value)
		{
			if (strpos($name, 'E_') === 0 && $value === $errno)
				$error_type = $name;
		}

		if ($fp = fopen($setting === 'stderr' ? 'php://stderr' : 'php://output', 'wt'))
		{
			fwrite($fp, $error_type . ': ' . $errstr . ' in ' . $file . ' on line ' . $line . "\n");
			fclose($fp);
		}

		if ($errno % 255 == E_ERROR)
			die;

		return true;
	}
}