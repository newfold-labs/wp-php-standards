<?php

namespace Demo;

class Thing {
	const FOO = 1;
}

// Violations. Dynamic class constant fetch is PHP 8.3 and fatals on PHP 7.
$a = Thing::class::FOO;
$b = \Demo\Thing::class::FOO;

// Violation. "::CLASS" is valid PHP and means the same as "::class".
$c = Thing::CLASS::FOO;

// Violation. Whitespace between "::class" and the next "::" is legal.
$d = Thing::class ::FOO;

// Not violations.
$e = Thing::class;
$f = Thing::FOO;
$g = Thing::method();
$h = static::class;
$i = self::class;

// Violations. The chained "::" is the problem, whatever follows it.
$j = Thing::class::$prop;
$k = Thing::class::{$name};
