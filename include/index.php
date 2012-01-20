<?php

// Needed for execution (and compiling.)
// For debugging.
require(dirname(__FILE__) . '/Errors.php');
// For makeTemplateName().
require(dirname(__FILE__) . '/Expression.php');
// For VERSION, callTemplate().
require(dirname(__FILE__) . '/Template.php');
// For simple handling of many templates.
require(dirname(__FILE__) . '/TemplateList.php');

// Needed for only compiling.
require(dirname(__FILE__) . '/Exception.php');
require(dirname(__FILE__) . '/ExceptionFile.php');
require(dirname(__FILE__) . '/Source.php');
require(dirname(__FILE__) . '/SourceFile.php');
require(dirname(__FILE__) . '/Prebuilder.php');
require(dirname(__FILE__) . '/Builder.php');
require(dirname(__FILE__) . '/Overlay.php');
require(dirname(__FILE__) . '/Parser.php');
require(dirname(__FILE__) . '/Token.php');
require(dirname(__FILE__) . '/StandardElements.php');
require(dirname(__FILE__) . '/Theme.php');

?>