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
			array( 10, 11 ),
			'double-colon-class.inc'
		);
	}

	/**
	 * An uppercase "::CLASS" is missed today.
	 *
	 * PHP treats "::CLASS" and "::class" identically, so line 14 is the same
	 * violation as line 10. The sniff compares the token content case
	 * sensitively and walks past it.
	 *
	 * @return void
	 */
	public function test_uppercase_class_keyword_is_not_reported_yet() {
		$this->assertArrayNotHasKey(
			14,
			$this->get_errors( 'double-colon-class.inc' ),
			'Line 14 now reports. Move it into the expected set in test_reports_chained_double_colon().'
		);
	}

	/**
	 * Whitespace before the second "::" is missed today.
	 *
	 * The sniff reads the raw neighbouring token rather than the next non-empty
	 * one, so a space breaks detection on line 17.
	 *
	 * @return void
	 */
	public function test_whitespace_before_second_double_colon_is_not_reported_yet() {
		$this->assertArrayNotHasKey(
			17,
			$this->get_errors( 'double-colon-class.inc' ),
			'Line 17 now reports. Move it into the expected set in test_reports_chained_double_colon().'
		);
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
