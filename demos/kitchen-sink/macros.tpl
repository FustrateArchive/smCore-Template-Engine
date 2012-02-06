<!---
	This file contains macro definitions for us to use elsewhere, over and over again. We
	can define blocks inside macros, and the type of macro is determined by the
	presence or absence of a <tpl:content /> tag.
--->

<!---
	This one can only be used as an empty tag, <site:menu />, because there isn't a
	<tpl:content /> tag. If you try to use this as a start and end tag pair, an exception
	will be thrown.
--->
<tpl:macro name="site:menu" requires="menu">
	<ul id="menu"><tpl:foreach from="{$menu}" as="{$item}">
		<li{tpl:if test="{$item.active}"} class="active"{/tpl:if}>
			<a href="{$item.link}">{$item.label}</a>
		</li></tpl:foreach>
	</ul>
</tpl:macro>

<!---
	If you use this macro as an empty tag, <site:info_box />, it will just output the top
	and bottom one after the other. Otherwise, whatever you put between the start and end tags
	will go where <tpl:content /> is.
--->
<tpl:macro name="site:info_box">
	<h1>Info</h1>
	<div>
		<tpl:content />
	</div>
	<tpl:block name="site:info_box_footer">
		Hello! <site:say name="Steven" />
	</tpl:block>
</tpl:macro>

<!---
	Lastly, this is a macro that has a block inside it. Whenever this macro is used, we'll
	see if there are block references that have been loaded, and run them each time.
--->
<tpl:macro name="site:block_macro">
	<h1>With Block!</h1>
	<div><tpl:block name="site:macro_with_block">
		<tpl:content /></tpl:block>
	</div>
</tpl:macro>

<tpl:macro name="site:say" requires="name">Yo {$name}!</tpl:macro>