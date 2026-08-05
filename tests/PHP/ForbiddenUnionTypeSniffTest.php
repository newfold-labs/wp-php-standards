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
	 * Property types, relative type names and DNF types report.
	 *
	 * Line 10 is a property type, 13 to 15 use self, static and parent in type
	 * position, and 18 is a disjunctive normal form type. None of these were
	 * reported before the sniff moved onto PHPCSUtils: it only visited pipes
	 * reachable from a parameter list or a return type, and its list of type
	 * tokens had no entry for the relative names.
	 *
	 * @return void
	 */
	public function test_reports_property_relative_and_dnf_types() {
		$this->assertErrorsOnLines(
			array( 10, 13, 14, 15, 18 ),
			'union-type-relative-and-property.inc'
		);
	}

	/**
	 * A union on a typed class constant reports, and untyped constants do not.
	 *
	 * Typed class constants are PHP 8.3. Line 8 declares one with a union type.
	 * The untyped class constant, the global const and the define() call have no
	 * type declaration to check.
	 *
	 * @return void
	 */
	public function test_reports_union_on_typed_constant_only() {
		$this->assertErrorsOnLines(
			array( 8 ),
			'union-type-constant.inc'
		);
	}

	/**
	 * A promoted constructor property reports once, not once per role.
	 *
	 * Constructor property promotion declares a parameter and a property from a
	 * single piece of syntax, and the sniff visits both parameter lists and
	 * property declarations. Counting the reports rather than only the lines is
	 * the point of this test.
	 *
	 * @return void
	 */
	public function test_reports_promoted_constructor_property_once() {
		$errors = $this->get_errors( 'union-type-promoted-property.inc' );

		$this->assertSame( array( 9 ), array_keys( $errors ) );
		$this->assertCount( 1, $errors[9], 'A promoted property must not be reported twice.' );
	}

	/**
	 * The sniff stands down when the target is PHP 8 or newer.
	 *
	 * @return void
	 */
	public function test_skips_when_targeting_php_8() {
		foreach ( array( 'union-type.inc', 'union-type-relative-and-property.inc', 'union-type-constant.inc' ) as $fixture ) {
			$this->assertSame(
				array(),
				$this->get_errors( $fixture, '8.0-' ),
				sprintf( '%s should be silent when targeting PHP 8.', $fixture )
			);
		}
	}
}
