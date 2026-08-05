<?php
/**
 * Tests for the ForbiddenUnionType sniff.
 *
 * @package Newfold
 */

namespace Newfold\Tests\PHP;

use Newfold\Tests\SniffTestCase;

/**
 * Covers Newfold.PHP.ForbiddenUnionType.
 */
class ForbiddenUnionTypeSniffTest extends SniffTestCase {

	/**
	 * The sniff under test.
	 *
	 * @var string
	 */
	protected $sniff = 'Newfold.PHP.ForbiddenUnionType';

	/**
	 * Fixture directory for this sniff.
	 *
	 * @var string
	 */
	protected $fixture_dir = 'PHP/ForbiddenUnionType';

	/**
	 * Union types report in parameters, return types, closures and arrow functions.
	 *
	 * @return void
	 */
	public function test_reports_union_types_in_declarations() {
		$this->assertErrorsOnLines(
			array( 9, 10, 11, 12, 15, 16, 19, 20 ),
			'union-type.inc'
		);
	}

	/**
	 * Bitwise or, single types and multi-catch are left alone.
	 *
	 * These share the pipe token with union types, so they are the cases most
	 * likely to regress into false positives during a rewrite. The line
	 * assertion above already fails on any extra report; this names them so a
	 * failure says which construct broke.
	 *
	 * @return void
	 */
	public function test_ignores_pipes_outside_type_declarations() {
		$errors = $this->get_errors( 'union-type.inc' );

		$cases = array(
			23 => 'integer literals',
			24 => 'variables',
			25 => 'constants',
			26 => 'a function call argument',
			29 => 'a single parameter and return type',
			30 => 'nullable shorthand',
			36 => 'multi-catch',
			40 => 'an intersection type',
		);

		foreach ( $cases as $line => $description ) {
			$this->assertArrayNotHasKey(
				$line,
				$errors,
				sprintf( 'A pipe in %s is not a union type declaration.', $description )
			);
		}
	}

	/**
	 * Property types and relative type names are missed today.
	 *
	 * Every declaration in this fixture is a PHP 8 union type that fatals on
	 * PHP 7, but the sniff only visits pipes reachable from a parameter list or
	 * a return type, and its list of type tokens has no entry for self, static
	 * or parent. Property types, relative names and disjunctive normal form all
	 * fall through.
	 *
	 * @return void
	 */
	public function test_property_and_relative_types_are_not_reported_yet() {
		$this->assertSame(
			array(),
			$this->get_errors( 'union-type-relative-and-property.inc' ),
			'A gap closed. Replace this with the lines that now report.'
		);
	}

	/**
	 * The sniff stands down when the target is PHP 8 or newer.
	 *
	 * @return void
	 */
	public function test_skips_when_targeting_php_8() {
		$this->assertSame(
			array(),
			$this->get_errors( 'union-type.inc', '8.0-' )
		);
	}
}
