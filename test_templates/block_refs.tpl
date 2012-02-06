<!---
	Let's add some stuff to the end of the header
--->
<site:head><tpl:parent />
		<style type="text/css">
			html {
				background: #f5f5f5;
				}
			#content {
				color: #12450a;
				font-weight: bold;
				}
		</style>
</site:head>

<!---
	Add something before a CMS comment
--->
<cms:comment>
	The opinion in this comment is that of the commenter, not of this website:<br />
<tpl:parent /></cms:comment>

<!---
	This is a bad idea, at least if you put it in your main block references file. Nobody
	would be able to read posts, since there's no <tpl:parent /> tag! Put something like
	this in its own file, or with others that should always be used under the same
	conditions, and load the file conditionally.
--->
<forum:post_content>
	You must be logged in in order to read posts!
</forum:post_content>