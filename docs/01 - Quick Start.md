# Quick start

## Overview

To get started fast, start in the samples directory. Look inside there and also read the rest of this documentation.

The basic syntax is similar to XML, and described in more detail within the
[element reference documentation](./03 - Element Reference.md). Note that because it's not XML, the HTML you use can
be built more freely. You don't even need to write HTML - the entire system is format agnostic, so you could output
your custom subset of JSON, ascii art, or even PNG data (if you're insane).

## Namespaces

The use of XML namespaces is central to this system, even though the system itself doesn't use much XML. Namespaces
are really just URIs, which you associate with a short name for convenience. The only one that's built into the
system is "tpl", which tells the engine that 

The URI itself doesn't matter, as long as you use the exact same one when referring to it. You can just use your
site's URL.


## Writing your first view

A basic template need not have much in it:

	Llamas are cool.

No, nothing is missing - that's a completely valid view file. You can add HTML tags around it, throw in a few blank
lines, and it still works.


## Adding layers

Now we're going to wrap the whole thing in a layer. Layers surround views, so this complete layer file:

	<p>
		<strong>Quote of the Day:</strong>
		<tpl:content />
	</p>

when loaded along with the view we created will output:

	<p>
		<strong>Quote of the Day:</strong>
		Llamas are cool.
	</p>

The extra element we added in the layer, `<tpl:content />`, tells us where to split the layer into a top and a bottom.
When you add multiple layers, each of their top halves are run, then the views are run, then finally each of the
bottom layer halves are run.

Now that you have your a view and a layer written, let's do something with them.


## Using it in some code

For now, create a new directory under samples, "quick-start". Save the view we created as "llamas.tpl", and the layer
as "my_layer.tpl". Let's put a basic index.php in there as well:

	<?php

	// Pull in the sample code.
	include(dirname(__DIR__) . '/include.php');

	$theme = new smCore\TemplateEngine\Theme(__DIR__, __DIR__);

	$theme->loadView('llamas');
	$theme->loadLayer('my_layer');

	$theme->output();

	?>

So now, you already have a template driven site. But we want to have another page, don't we?  Create a copy of that
index.php and save it as "about.php".

Now try it out.


## What's next?

There's lots of documentation in here, so take a gander.
