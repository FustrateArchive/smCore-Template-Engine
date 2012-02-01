<tpl:options doctype="html" /><!--- This line is optional and would be removed from output. --->
<!DOCTYPE html>
<html>
	<head><tpl:block name="site:head">
		<title>Blocks: <tpl:output value="{$context.nothing |default('N/A')}" /></title>
	</tpl:block></head>
	<body><tpl:block name="site:body">
		Hello World
		<thisGoes:straightThrough because="the namespace isn't recognized" />
		<tpl:foreach from="{$context.nums}" as="{$num}">
			<!-- Inside, has to be template call -->
			<site:rounded>{$num}</site:rounded>
		</tpl:foreach>

		<tpl:content />

		<p>
			More stuff after the tpl:content, but inside the body block.
		</p>
	</tpl:block></body>
</html>