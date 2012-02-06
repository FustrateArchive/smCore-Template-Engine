<?php

/**
 * File Source
 *
 * Represents a file-based Source, which is what most people should be using.
 *
 * This file is mostly unchanged from the original by Unknown W. Brackets.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine\Source;
use smCore\TemplateEngine\Source;

class File extends Source
{
	// Caching will eat plenty of memory, so it may really help things.
	const ENABLE_CACHE = false;
	static $cached_tokens = array();

	/**
	 * Create a File Source out of a file path
	 *
	 * @param string $filename
	 * @param int $line The line we're starting on.
	 *
	 * @access public
	 */
	public function __construct($filename, $line = 1)
	{
		if (!file_exists($filename) || !is_readable($filename))
			throw new \Exception('parsing_cannot_open');

		// If it's cached, this will return an array we will operate on.
		$data = self::cacheForFile($filename);

		if ($data === false)
		{
			$data = @fopen($filename, 'rt');

			if (!$data)
				throw new \Exception('parsing_cannot_open');
		}

		parent::__construct($data, $filename, $line);
	}

	/**
	 * Close the resource we opened if it's still active. Don't want to tie anything up.
	 *
	 * @access public
	 */
	public function __destruct()
	{
		if (is_resource($this->_data))
			fclose($this->_data);

		$this->_data = null;

		parent::__destruct();
	}

	/**
	 * Read a token from the data. We might also cache the tokens.
	 *
	 * @return smCore\TemplateEngine\Token
	 *
	 * @access public
	 */
	public function readToken()
	{
		$token = parent::readToken();

		// If we're operating from a file, we might cache its tokens.
		if ($token !== false && is_resource($this->data))
			self::cacheAddToken($this->file, $token);

		return $token;
	}

	/**
	 * Try to find tokens for a file if we've already cached them
	 *
	 * @param string $filename The file we're looking for
	 * @return mixed An array of tokens if we cached them, otherwise false
	 *
	 * @access protected
	 */
	protected static function cacheForFile($filename)
	{
		// This assumes no concurrent usage (otherwise this list would be incomplete.)
		if (isset(self::$cached_tokens[$filename]))
			return self::$cached_tokens[$filename];

		return false;
	}

	/**
	 * Add a token to a specific file's cache array
	 *
	 * @param string $filename Path to the file we're running through
	 *
	 * @access protected
	 */
	protected static function cacheAddToken($filename, Token $token)
	{
		if (self::ENABLE_CACHE)
			self::$cached_tokens[$filename][] = $token;
	}
}