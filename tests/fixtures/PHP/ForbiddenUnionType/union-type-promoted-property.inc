<?php

namespace Demo;

class Promoted {

	// Constructor property promotion declares a parameter and a property from one
	// piece of syntax. It must be counted once, not once per role.
	public function __construct( public int|string $promoted ) {}
}
