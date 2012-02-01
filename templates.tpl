<tpl:options doctype="html" /><!--- This line is removed. --->
<!DOCTYPE html>
<html>
	<head><tpl:block name="site:head">
		<title>Blocks: <tpl:output value="{$context.nothing |default('N/A')}" /></title>
	</tpl:block></head>
	<body><tpl:block name="site:body">
		Hello World
		<tpl:foreach from="{$context.nums}" as="{$num}">
			<!-- Inside, has to be template call -->
			<site:rounded>{$num}</site:rounded>
		</tpl:foreach>

		<tpl:content />
	</tpl:block></body>
</html>

<!-- Top level, could be block or template -->
<site:head>
		<style type="text/css">
			html {
				background: #f5f5f5;
				}
		</style>
</site:head>

<tpl:template name="site:rounded">
	<div class="rounded">
		<tpl:content />
	</div>
</tpl:template>