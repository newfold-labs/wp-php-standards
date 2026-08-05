<?php
/**
 * Tests for the ForbiddenDoubleColonClass sniff.
 *
 * @package Newfold
 */

namespace Newfold\Tests\PHP;

use Newfold\Tests\SniffTestCase;

/**
 * Covers Newfold.PHP.ForbiddenDoubleColonClass.
 */
class ForbiddenDoubleColonClassSniffTest extends SniffTestCase {

	/**
	 * The sniff under test.
	 *
	 * @var string
	 */
	protected $sniff = 'Newfold.PHP.ForbiddenDoubleColonClass';

	/**
	 * Fixture directory for this sniff.
	 *
	 * @var string
	 */
	protected $fixture_dir = 'PHP/ForbiddenDoubleColonClass';

	/**
	 * Chained "::class::" reports on the lines that use it.
	 *
	 * @return void
	 */
	public function test_reports_chained_double_colon() {
		$this->assertErrorsOnLines(
			array( 10, 11, 14, 17, 27, 28 ),
			'double-colon-class.inc'
		);
	}

	/**
	 * Casing and spacing do not hide the construct.
	 *
	 * PHP treats "::CLASS" and "::class" identically, and whitespace between the
	 * two operators is legal, so lines 14 and 17 are the same violation as line
	 * 10. Both were missed while the sniff compared token content case
	 * sensitively and read the raw neighbouring token.
	 *
	 * @return void
	 */
	public function test_reports_regardless_of_casing_and_spacing() {
		$errors = $this->get_errors( 'double-colon-class.inc' );

		$this->assertArrayHasKey( 14, $errors, 'Thing::CLASS::FOO is the same construct as Thing::class::FOO.' );
		$this->assertArrayHasKey( 17, $errors, 'Whitespace before the second "::" is legal PHP.' );
	}

	/**
	 * The chain reports whatever follows the second operator.
	 *
	 * Line 27 fetches a static property and line 28 uses a "{$expr}" fetch. Both
	 * parse only on PHP 8.3 and up. The sniff used to require a plain name after
	 * the second operator, which let both through.
	 *
	 * @return void
	 */
	public function test_reports_non_name_fetches_after_the_chain() {
		$errors = $this->get_errors( 'double-colon-class.inc' );

		$this->assertArrayHasKey( 27, $errors, 'Thing::class::$prop parses only on PHP 8.3 and up.' );
		$this->assertArrayHasKey( 28, $errors, 'Thing::class::{$name} parses only on PHP 8.3 and up.' );
	}

	/**
	 * Plain "::class", constants and method calls stay clean.
	 *
	 * @return void
	 */
	public function test_ignores_valid_scope_resolution() {
		$errors = $this->get_errors( 'double-colon-class.inc' );

		foreach ( array( 20, 21, 22, 23, 24 ) as $line ) {
			$this->assertArrayNotHasKey( $line, $errors, sprintf( 'Line %d is valid PHP 7 syntax.', $line ) );
		}
	}

	/**
	 * The sniff stands down when the target is PHP 8 or newer.
	 *
	 * @return void
	 */
	public function test_skips_when_targeting_php_8() {
		$this->assertSame(
			array(),
			$this->get_errors( 'double-colon-class.inc', '8.0-' )
		);
	}
}
