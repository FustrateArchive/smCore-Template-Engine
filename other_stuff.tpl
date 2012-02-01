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

	If we don't find anything under the name "undefined_ns:whatever" after the first compile pass, we
	assume it's a block usage for a block that doesn't exist - therefore it is skipped.
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