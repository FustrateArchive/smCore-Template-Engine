# Filters

## Overview

Filters are used on variable and language references to manipulate the value that
is output. There are currently 32 filters built into the smCore template engine,
and developers can add their own filters with a simple method call.

**The filter syntax has not yet been updated from the percent+semicolon syntax.
This document references a future syntax.**

## Filter Syntax

Formatting is very easy to use. In order to add a filter to an existing reference,
add `|filter_name($param, 'eters')` at the end of the reference. Multiple filters
can be added to a single reference.

In most cases, expressions with filters might look like the following:

	{$birthday |date}
		Formats a birthday using the default date format.

	{$today.date |date("g:i A")}
		Formats with a custom format.

	{$name |upper}
		Shows a name in all capital letters.

	{#welcome_message |lower |raw}
		Formats a language string into lowercase, and does not escape it.

	{$price |number}
		Formats $price into something like 2,000.57

	{$thing |my_filter($first, $second.param, 'third', #fourth)}
		Formats using a custom filter named 'my_formatter' with many parameters.

## Default Filters - WIP

### ucwords
Capitalizes the first character of every word.

	{$var |ucwords}

### ucfirst
Capitalizes the first chracter of the string, lowercases everything else.

	{$var |ucfirst}

### upper
Turns the entire string to uppercase.

	{$var |upper}

### lower
Turns the entire string to lowercase.

	{$var |lower}

### trim
Removes whitespace from the start and end of the string.

	{$var |trim}

### rtrim
Removes whitespace from the end (right) of the string.

	{$var |rtrim}

### ltrim
Removes whitespace from the start (left) of the string.

	{$var |ltrim}

### even
Returns true if the variable is an even integer (modulo 2 = 0).

	{$var |even}

### odd
Returns true if the variable is an odd integer (modulo 2 = 1).

	{$var |odd}

### contains
If the variable is an array, returns true if the parameter is in the array.
If the variable is a string, returns true if the parameter can be found in the string.

	{$array_of_vars |contains('5')}
	{$string_var |contains('abc')}

### empty
Returns true if the variable is empty, using PHP's `empty()` function.

	{$var |empty}

### null
Returns true if the variable is null.

	{$var |null}

### divisibleby
Returns true if the variable is a number divisible by the parameter.

	{$var |divisibleby(3)}

### length
Returns the length of the variable. Arrays return their count, and strings return their length.

	{$var |length}

### wordcount
Returns the number of words in the variable.

	{$var |wordcount}

### urlencode

	{$var |urlencode}

### json
Returns a JSON representation of the data in `$var`

	{$var |json}

### join

	{$var |join(", ")}

### random

	{$var |random}

### stripchars

	{$var |stripchars}

### striptags

	{$var |striptags}

### truncate

	{$var |truncate(15)}

### truncatewords

	{$var |truncatewords(12)}

### wrap

	{$var |wrap(80)}

### wordwrap

	{$var |wordwrap(80)}

### date

	{$var |date}
	{$var |date("n/j/Y @ g:i:s A")}

### time

	{$var |time}

### money

	{$var |money}

### float

	{$var |float}
	{$var |float(3)}

### default

	{$var |default('N/A')}

### raw

	{$var |raw}

### escape

	{$var |escape}