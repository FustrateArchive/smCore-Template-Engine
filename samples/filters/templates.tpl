<tpl:container>
	<tpl:template name="site:main"><!DOCTYPE html>
		<html>
			<head>
				<title>Filters Demo</title>
				<style type="text/css">
					body
					{
						font: 10pt sans-serif;
					}
					h3
					{
					}
					code
					{
						background: #eaeaea;
						border: 1px solid #555;
						display: block;
						width: 500px;
						max-height: 300px;
						overflow: auto;
						padding: 0.5em;
						font-family: "Consolas", monospace;
					}
					div.malicious
					{
						height: 100px;
						background: #fee;
						border: 1px solid red;
						color: #a33;
						font-weight: bold;
						padding: 0.2em;
					}
				</style>
			</head>
			<body>
				<h3>contains</h3>
				<code>
					<tpl:if test="{$context.grades |contains(95)}">
						You did score a 95.<br />
					<tpl:else />
						You did not score a 95.<br />
					</tpl:if>

					<tpl:if test="{$context.alphabet |contains('apt')}">
						"apt" is in the alphabet.
					<tpl:else />
						"apt" is not in the alphabet.
					</tpl:if>
				</code>

				<h3>date</h3>
				<code>
					Default format: {$context.time |date}<br />
					Custom format: {$context.time |date("n/j/Y @ h:i:s A")}
				</code>

				<h3>default</h3>
				<code>
					This expression has a default value of "{$context.empty_string |default("N/A")}".
				</code>

				<h3>divisibleby</h3>
				<code>
					Is 9 divisible by 3?
					<tpl:if test="{$context.nine |divisibleby(3)}">
						Yes!<br />
					<tpl:else />
						No!<br />
					</tpl:if>

					Is 9 divisible by 4?
					<tpl:if test="{$context.nine |divisibleby(4)}">
						Yes!
					<tpl:else />
						No!
					</tpl:if>
				</code>

				<h3>empty</h3>
				<code>
					<tpl:if test="{$context.empty_array |empty}">
						There's nothing in an empty array.<br />
					<tpl:else />
						That's weird, there's something in an empty array...<br />
					</tpl:if>

					<tpl:if test="{$context.alphabet |empty}">
						The alphabet is empty...
					<tpl:else />
						The alphabet is not empty, thankfully.
					</tpl:if>
				</code>

				<h3>escape</h3>
				<code>
					<tpl:output value="{$context.malicious_js |escape}" escape="false" />
				</code>

				<h3>even</h3>
				<code>
				</code>

				<h3>float</h3>
				<code>
				</code>

				<h3>join</h3>
				<code>
				</code>

				<h3>json</h3>
				<code>
				</code>

				<h3>length</h3>
				<code>
				</code>

				<h3>lower</h3>
				<code>
				</code>

				<h3>ltrim</h3>
				<code>
				</code>

				<h3>money</h3>
				<code>
				</code>

				<h3>nl2br</h3>
				<code>
				</code>

				<h3>null</h3>
				<code>
				</code>

				<h3>odd</h3>
				<code>
				</code>

				<h3>random</h3>
				<code>
				</code>

				<h3>raw</h3>
				<code>
				</code>

				<h3>rtrim</h3>
				<code>
				</code>

				<h3>stripchars</h3>
				<code>
				</code>

				<h3>striptags</h3>
				<code>
				</code>

				<h3>time</h3>
				<code>
				</code>

				<h3>trim</h3>
				<code>
				</code>

				<h3>truncate</h3>
				<code>
				</code>

				<h3>truncatewords</h3>
				<code>
				</code>

				<h3>ucfirst</h3>
				<code>
				</code>

				<h3>ucwords</h3>
				<code>
				</code>

				<h3>upper</h3>
				<code>
				</code>

				<h3>urlencode</h3>
				<code>
				</code>

				<h3>wordcount</h3>
				<code>
				</code>

				<h3>wordwrap</h3>
				<code>
				</code>

				<h3>wrap</h3>
				<code>
				</code>
			</body>
		</html>
	</tpl:template>
</tpl:container>