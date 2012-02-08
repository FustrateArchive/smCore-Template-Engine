# Terminology

## List of terms

Terms used in this documentation and error messages:

### tag

A tag is, for example, `<div>`. In ToxG, these can be surrounded by curly braces or angle brackets. Any of the
following are tags:

	<tpl:tag>
	{tpl:whatever /}
	</tpl:if>

A start tag is one that doesn't have a / at the start or end. An empty tag is one that has a / last, and an end
tag is one that has a / first. The above examples are a start, empty, and end tag, respectively.

### element

An element is considered to be the start tag, content, and end tag together. In the case of an empty tag, it is
the element all by itself.

### view

A view is a basic file which outputs content. 

### layer



### block



### template

A template is essentially a view or layer that can be used repeatedly inside your code. If you define a "user
profile link" template, 

The contents of a `<tpl:template>` are considered a template, and multiple templates can be defined in a single
template file:

	<tpl:template name="my:profile_link">
		<a href="/profile.php">Your Profile</a>
	</tpl:template>

	<tpl:template name="site:menu" requires="menu">
		<ul>
			...menu code here...
		</ul>
	</tpl:template>

Notice the "requires" attribute on 

### overlay

An overlay is a file that contains alterations to apply to templates. The
alterations are "overlayed" on the templates, if you will.

### alter or alteration

An alteration is source code, tags, and/or text that is added to templates.
For more information see the documentation on "overlays".

### theme

A theme is a collection of templates meant to be used together.
