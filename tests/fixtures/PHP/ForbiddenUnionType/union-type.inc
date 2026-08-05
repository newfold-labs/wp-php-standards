<?php

namespace Demo;

use Countable;
use WP_Error;

// Violations in parameter types.
function param_union( int|string $value ) {}
function param_qualified( array|\WP_Error $value ) {}
function param_second( $first, array|WP_Error $second ) {}
function param_variadic( int|string ...$rest ) {}

// Violations in return types.
function return_union(): array|\WP_Error {}
function return_scalar(): int|false {}

// Violations in closures and arrow functions.
$closure = function ( int|float $n ): int|false {};
$arrow   = fn ( int|string $v ): string|null => (string) $v;

// Not violations. Bitwise or, not a type declaration.
$bitwise  = 1 | 2;
$variable = $one | $two;
$constant = FLAG_A | FLAG_B;
$call     = compact( A | B );

// Not violations. Single types and nullable shorthand.
function single( array $value ): array {}
function nullable( ?int $value ): ?string {}

// Not a violation. Multi-catch reuses the same pipe token.
function multi_catch() {
	try {
		noop();
	} catch ( \RuntimeException | \LogicException $e ) {
	}
}

// Not a violation. Intersection types use an ampersand.
function intersection( Countable&\Traversable $value ) {}
