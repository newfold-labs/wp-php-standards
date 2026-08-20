<?php

namespace Demo;

class WithConstants {

	// Typed class constants are PHP 8.3.
	const int|string TYPED_UNION = 1;

	const UNTYPED = 2;
}

// A global constant has no type declaration and must not be visited.
const GLOBAL_UNTYPED = 3;

define( 'DEFINED_CONSTANT', 4 );
