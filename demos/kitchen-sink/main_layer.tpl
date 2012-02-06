<!---
	This is a layer file - you can tell because there's a <tpl:content /> tag. This comment
	and the <tpl:options /> tag that follow it won't be output - nothing will until the
	doctype declaration. Comments and options are special like that.
--->
<tpl:options doctype="html" />

<!DOCTYPE html>
<html>
	<head><tpl:block name="site:head">
		<title>My Site - {$title}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></tpl:block>
	</head>
	<body><tpl:block name="site:body">
		<header>
			<h1>My Site</h1>
		</header>

		<div id="content">
<tpl:content />
		</div>

		<footer>
			&copy;2012 Me
		</footer></tpl:block>
	</body>
</html>

<!---
	There are also blank lines and a comment down here, but they don't get output either. 
--->