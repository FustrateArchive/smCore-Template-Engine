<?php

namespace smCore\TemplateEngine;

class Filters
{
	protected static function requireParams($num, $params)
	{
		return count($params) >= $num;
	}

	public static function filter($value, $filters)
	{
		// @todo: required number of params for each filter
		foreach ($filters as $type => $params)
		{
			/**
			 *
			 * String manipulation
			 *
			 */
			if ($type === 'ucwords')
			{
				$value = mb_convert_case($value, MB_CASE_TITLE, "UTF-8");
			}
			else if ($type === 'ucfirst')
			{
				$value = mb_strtoupper($value[0]) . mb_strtolower(mb_substr($value, 1));
			}
			else if ($type === 'upper')
			{
				$value = mb_strtoupper($value);
			}
			else if ($type === 'lower')
			{
				$value = mb_strtolower($value);
			}
			else if ($type === 'trim')
			{
				$value = trim($value);
			}
			else if ($type === 'rtrim')
			{
				$value = rtrim($value);
			}
			else if ($type === 'ltrim')
			{
				$value = ltrim($value);
			}

			/**
			 *
			 * Boolean filters return booleans
			 *
			 */

			else if ($type === 'even')
			{
				$value = is_numeric($value) && $value % 2 === 0;
			}
			else if ($type === 'odd')
			{
				$value = is_numeric($value) && $value % 2 === 1;
			}
			else if ($type === 'contains')
			{
				self::requireParams(1, $params);

				if (is_string($value))
				{
					// Cast to a string so it doesn't try it as an int
					$value = strpos($value, (string) $param[0]) > -1;
				}
				else if (is_array($value))
				{
					$value = in_array($params[0], $value);
				}
			}
			else if ($type === 'empty')
			{
				$value = empty($value);
			}
			else if ($type === 'null')
			{
				$value = $value === null;
			}
			else if ($type === 'divisibleby')
			{
				$value = ($value % $params[0]) === 0;
			}

			/**
			 *
			 * Other stuff
			 *
			 */

			else if ($type === 'length')
			{
				$value = is_array($value) ? count($value) : mb_strlen((string) $value);
			}
			else if ($type === 'wordcount')
			{
			}
			else if ($type === 'urlencode')
			{
				$value = urlencode($value);
			}
			else if ($type === 'json')
			{
				$value = json_encode($value);
			}
			else if ($type === 'join')
			{
				self::requireParams(1, $params);

				$value = implode($params[0], (array) $value);
			}

			else if ($type === 'random')
			{
			}
			else if ($type === 'stripchars')
			{
			}
			else if ($type === 'striptags')
			{
			}
			else if ($type === 'truncate')
			{
			}
			else if ($type === 'truncatewords')
			{
			}
			else if ($type === 'wrap')
			{
			}
			else if ($type === 'wordwrap')
			{
			}
			else if ($type === 'date')
			{
			}
			else if ($type === 'time')
			{
			}
			else if ($type === 'money')
			{
			}
			else if ($type === 'float')
			{
			}
			else if ($type === 'default')
			{
				// @todo: should be handled in Expression, in case it's not set
				self::requireParams(1, $params);

				if (empty($value))
					$value = $params[0];
			}
			else
			{
				// @todo: custom filters
			}
		}

		return $value;
	}
}