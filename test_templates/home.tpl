<!---
	Views are simple - they just output content. They can define blocks and use templates,
	but they can't use <tpl:content /> (then it would be a layer) or 
--->
<site:menu menu="{$site_menu}" />
<h2>Hello World!</h2>
<p>
	This is my site.
</p>

<h3>Credits</h3>
<ul>
	<tpl:block name="site:credits"></tpl:block>
</ul>