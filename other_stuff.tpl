<!--- Top level, could be block or template call --->
<site:head>
		<tpl:content />
		<style type="text/css">
			html {
				background: #f5f5f5;
				}
		</style>
</site:head>

<!---
	Make sure you define this namespace somewhere, even if it's for another module, using
	$theme->addNamespace('undefined_ns', null, false); or else it will be output as content.
--->
<undefined_ns:whatever>
	Add this before the rest of the block.<br />
	<tpl:content />
</undefined_ns:whatever>

<tpl:template name="site:rounded">
	<div class="rounded">
		<tpl:content />
	</div>
</tpl:template>