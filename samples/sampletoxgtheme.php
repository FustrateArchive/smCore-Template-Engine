<?php

function filter($value, $filters)
{
	// @todo: required number of params for each filter
	foreach ($filters as $type => $params)
	{
		switch ($type)
		{
			case 'ucwords':
				$value = mb_convert_case($value, MB_CASE_TITLE, "UTF-8");
				break;

			case 'upper':
				$value = mb_strtoupper($value);
				break;

			case 'lower':
				$value = mb_strtolower($value);
				break;

			case 'trim':
				$value = trim($value);
				break;

			case 'rtrim':
				$value = rtrim($value);
				break;

			case 'ltrim':
				$value = ltrim($value);
				break;

			case 'default':
				if (empty($value))
					$value = $params[0];

				break;

			case 'length':
				$value = is_array($value) ? count($value) : mb_strlen($value);
				break;

			case 'ucfirst':
				$value = mb_strtoupper($value[0]) . mb_strtolower(mb_substr($value, 1));
				break;

			case 'date':
				break;

			case 'time':
				break;

			case 'money':
				break;

			case 'float':
				break;

			case 'urlencode':
				$value = urlencode($value);
				break;

			case 'json':
				
				break;

			case 'join':
				$value = implode($params[0], (array) $value);
				break;

			case 'random':
				break;

			case 'stripchars':
				break;

			case 'striptags':
				break;

			case 'truncate':
				break;

			case 'truncatewords':
				break;

			case 'wordcount':
				break;

			case 'wrap':
				break;

			case 'wordwrap':
				break;

			default:
				// @todo: custom filters
				break;
		}
	}

	return $value;
}

class SampleToxgTheme extends Toxg\Theme
{
	public $context = array();
	protected $needs_compile = true;

	public function isTemplateUsed($name)
	{
		return Toxg\Template::isTemplateUsed($this->nsuri, $name);
	}

	public function output()
	{
		$this->setTemplateParam('context', $this->context);
		$this->addCommonVars(array('context'));
		parent::output();
	}
}

?>