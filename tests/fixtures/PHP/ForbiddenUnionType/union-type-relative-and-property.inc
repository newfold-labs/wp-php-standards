<?php

namespace Demo;

class Base {}

class Gaps extends Base implements \Countable {

	// Property types.
	public int|string $typed_property;

	// self, static and parent in type position.
	public function relative_param( self|Base $value ) {}
	public function relative_return(): static|null {}
	public function parent_param( parent|Base $value ) {}

	// Disjunctive normal form, PHP 8.2.
	public function dnf( (Gaps&\Countable)|null $value ) {}

	public function count(): int {
		return 0;
	}
}
