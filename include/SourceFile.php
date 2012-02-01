<?php

/**
 * SourceFile
 * Represents a file-based Source, which is what most people should be using.
 *
 * @package smCore Template Engine
 * @author Steven "Fustrate" Hoffman
 * @license MPL 1.1
 * @version 0.1 Alpha 1
 */

namespace smCore\TemplateEngine;

class SourceFile extends Source
{
	// Caching will eat plenty of memory, so it may really help things.
	const ENABLE_CACHE = false;
	static $cached_tokens = array();

	public function __construct($file, $line = 1)
	{
		if (!file_exists($file) || !is_readable($file))
			throw new \Exception('parsing_cannot_open');

		// If it's cached, this will return an array we will operate on.
		$data = self::cacheForFile($file);

		if ($data === false)
		{
			$data = @fopen($file, 'rt');

			if (!$data)
				throw new \Exception('parsing_cannot_open');
		}

		parent::__construct($data, $file, $line);
	}

	public function __destruct()
	{
		if (is_resource($this->_data))
			fclose($this->_data);

		$this->_data = null;

		parent::__destruct();
	}

	public function readToken()
	{
		$token = parent::readToken();

		// If we're operating from a file, we might cache its tokens.
		if ($token !== false && is_resource($this->data))
			self::cacheAddToken($this->file, $token);

		return $token;
	}

	static function cacheForFile($file)
	{
		// This assumes no concurrent usage (otherwise this list would be incomplete.)
		if (isset(self::$cached_tokens[$file]))
			return self::$cached_tokens[$file];
		else
			return false;
	}

	static function cacheAddToken($file, Token $token)
	{
		if (self::ENABLE_CACHE)
			self::$cached_tokens[$file][] = $token;
	}
}