<tpl:options doctype="html" /><!--- This line is optional and would be removed from output. --->
<!DOCTYPE html>
<html>
	<head><tpl:block name="site:head"></tpl:block>
	</head>
	<body><tpl:block name="site:body">
		Hello World
		<tpl:foreach from="{$context.nums}" as="{$num}">
			<!--- Inside, has to be template call --->
			<site:rounded>{$num}</site:rounded>
		</tpl:foreach>

		<tpl:content />

		<p>
			This will be green if blocks are working.
		</p>
	</tpl:block>

	{$context.test |default('N/A')}</body>
</html>

<site:head><tpl:parent />
		<title>Test Block</title>

		<style type="text/css">
			p {
				background: red;
			}
		</style>
</site:head>