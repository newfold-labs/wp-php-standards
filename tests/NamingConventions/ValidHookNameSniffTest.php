<?php
/**
 * Tests for the ValidHookName sniff.
 *
 * @package Newfold
 */

namespace Newfold\Tests\NamingConventions;

use Newfold\Tests\SniffTestCase;

/**
 * Covers Newfold.NamingConventions.ValidHookName.
 */
class ValidHookNameSniffTest extends SniffTestCase {

	/**
	 * The sniff under test.
	 *
	 * @var string
	 */
	protected $sniff = 'Newfold.NamingConventions.ValidHookName';

	/**
	 * Fixture directory for this sniff.
	 *
	 * @var string
	 */
	protected $fixture_dir = 'NamingConventions/ValidHookName';

	/**
	 * Every structural violation reports, with the code that says why.
	 *
	 * @return void
	 */
	public function test_reports_names_that_break_the_convention() {
		$this->assertErrorCodes(
			array(
				29 => array( 'InvalidStructure' ),
				30 => array( 'InvalidStructure' ),
				31 => array( 'InvalidStructure' ),
				34 => array( 'InvalidType' ),
				37 => array( 'TypeMismatch' ),
				38 => array( 'TypeMismatch' ),
				41 => array( 'InvalidContext' ),
				42 => array( 'InvalidContext' ),
				45 => array( 'NotCamelCase' ),
				46 => array( 'NotCamelCase' ),
				47 => array( 'NotCamelCase' ),
				57 => array( 'NotCamelCase' ),
			),
			'hook-name.inc'
		);
	}

	/**
	 * A hook name passed by name rather than by position is still checked.
	 *
	 * Line 57 passes a bad name as "hook_name:" and line 58 passes a good one. The
	 * position of the hook name moves with named arguments, so a sniff reading the
	 * first parameter by index alone would check the wrong thing or nothing at all.
	 *
	 * @return void
	 */
	public function test_reads_the_hook_name_from_a_named_argument() {
		$errors = $this->get_errors( 'hook-name.inc' );

		$this->assertArrayHasKey( 57, $errors, 'A named argument carries the hook name too.' );
		$this->assertArrayNotHasKey( 58, $errors, 'A valid name passed by name is still valid.' );
	}

	/**
	 * A vendor that is only known at runtime is not treated as a missing prefix.
	 *
	 * Line 54 embeds the first segment. It could resolve to "newfold", so reporting
	 * a missing prefix would be a guess. The sniff stops rather than guessing, which
	 * also means the rest of that name goes unchecked.
	 *
	 * @return void
	 */
	public function test_ignores_a_hook_whose_vendor_is_dynamic() {
		$this->assertArrayNotHasKey( 54, $this->get_errors( 'hook-name.inc' ) );
		$this->assertArrayNotHasKey( 54, $this->get_warnings( 'hook-name.inc' ) );
	}

	/**
	 * A hook without the vendor prefix is a warning, not an error.
	 *
	 * The hook naming standard puts a "newfold/" prefix on every hook, while the
	 * PHP standard still shows "nfd_platform_branding" as an example of good
	 * prefixing. Until those two agree, this reports without failing a build.
	 *
	 * @return void
	 */
	public function test_reports_a_missing_vendor_prefix_as_a_warning() {
		$this->assertWarningCodes(
			array(
				25 => array( 'MissingVendorPrefix' ),
				26 => array( 'MissingVendorPrefix' ),
			),
			'hook-name.inc'
		);
	}

	/**
	 * Names assembled at runtime are checked as far as they are known.
	 *
	 * Line 16 embeds the context, so the prefix, type and name are still checked
	 * and the context is not. Line 17 appends a dynamic value after a colon, which
	 * leaves the name in front of the colon complete. Line 18 passes a variable,
	 * which says nothing at all. None of the three should report.
	 *
	 * Line 31 does report, because five segments cannot become four however the
	 * embedded value resolves.
	 *
	 * @return void
	 */
	public function test_checks_runtime_names_as_far_as_they_are_known() {
		$errors = $this->get_errors( 'hook-name.inc' );

		foreach ( array( 16, 17, 18 ) as $line ) {
			$this->assertArrayNotHasKey(
				$line,
				$errors,
				sprintf( 'Line %d is only partly known, so it cannot be judged.', $line )
			);
		}

		$this->assertArrayHasKey( 31, $errors, 'An embedded value cannot reduce the number of segments.' );
	}

	/**
	 * Deprecated hooks are left alone.
	 *
	 * A hook deprecated under an old name has to keep firing under that name, or
	 * everything listening for it stops working.
	 *
	 * @return void
	 */
	public function test_ignores_deprecated_hook_invocations() {
		$errors   = $this->get_errors( 'hook-name.inc' );
		$warnings = $this->get_warnings( 'hook-name.inc' );

		foreach ( array( 21, 22 ) as $line ) {
			$this->assertArrayNotHasKey( $line, $errors, sprintf( 'Line %d fires a deprecated hook.', $line ) );
			$this->assertArrayNotHasKey( $line, $warnings, sprintf( 'Line %d fires a deprecated hook.', $line ) );
		}
	}

	/**
	 * Methods that happen to share a name with the hook functions are left alone.
	 *
	 * @return void
	 */
	public function test_ignores_methods_with_the_same_name() {
		$errors   = $this->get_errors( 'hook-name.inc' );
		$warnings = $this->get_warnings( 'hook-name.inc' );

		foreach ( array( 50, 51 ) as $line ) {
			$this->assertArrayNotHasKey( $line, $errors, sprintf( 'Line %d is a method call.', $line ) );
			$this->assertArrayNotHasKey( $line, $warnings, sprintf( 'Line %d is a method call.', $line ) );
		}
	}

	/**
	 * A hook listed in allowed_hook_names is skipped.
	 *
	 * Hooks that predate the convention cannot be renamed without breaking
	 * whoever hooked onto them, so a ruleset can name them and move on.
	 *
	 * @return void
	 */
	public function test_allows_hook_names_set_in_the_ruleset() {
		$this->sniff_properties = array( 'allowed_hook_names' => array( 'nfd_module_loaded' ) );

		$this->assertWarningCodes(
			array( 26 => array( 'MissingVendorPrefix' ) ),
			'hook-name.inc'
		);
	}
}
