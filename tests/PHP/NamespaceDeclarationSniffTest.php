<?php
/**
 * Tests for the NamespaceDeclaration sniff.
 *
 * @package Newfold
 */

namespace Newfold\Tests\PHP;

use Newfold\Tests\SniffTestCase;

/**
 * Covers Newfold.PHP.NamespaceDeclaration.
 */
class NamespaceDeclarationSniffTest extends SniffTestCase {

	/**
	 * The sniff under test.
	 *
	 * @var string
	 */
	protected $sniff = 'Newfold.PHP.NamespaceDeclaration';

	/**
	 * Fixture directory for this sniff.
	 *
	 * @var string
	 */
	protected $fixture_dir = 'PHP/NamespaceDeclaration';

	/**
	 * Every namespace that does not match the convention reports, with the reason.
	 *
	 * @return void
	 */
	public function test_reports_namespaces_that_break_the_convention() {
		$this->assertErrorCodes(
			array(
				26 => array( 'InvalidPrefix' ),
				29 => array( 'InvalidPrefix' ),
				32 => array( 'InvalidPrefix' ),
				35 => array( 'InvalidPrefix' ),
				38 => array( 'MissingName' ),
				41 => array( 'MissingName' ),
				44 => array( 'InvalidName' ),
				47 => array( 'InvalidName' ),
			),
			'namespace-declaration.inc'
		);
	}

	/**
	 * The fixed part of the namespace is matched case sensitively.
	 *
	 * PHP resolves namespaces without regard to case, so "newfold\WP" and
	 * "Newfold\Wp" both work at runtime. The convention is about how the code
	 * reads, and PSR-4 directories mirror the casing, so both report.
	 *
	 * @return void
	 */
	public function test_matches_the_fixed_part_case_sensitively() {
		$errors = $this->get_errors( 'namespace-declaration.inc' );

		$this->assertArrayHasKey( 29, $errors, 'The vendor is "Newfold", not "newfold".' );
		$this->assertArrayHasKey( 32, $errors, 'The platform is "WP", not "Wp".' );
	}

	/**
	 * Sub-namespaces below the product name are left alone.
	 *
	 * The convention fixes the first four parts. What a product puts below its own
	 * name is not something the standard has an opinion on.
	 *
	 * @return void
	 */
	public function test_allows_sub_namespaces_below_the_product_name() {
		$errors = $this->get_errors( 'namespace-declaration.inc' );

		$this->assertArrayNotHasKey(
			23,
			$errors,
			'Newfold\WP\Module\Staging\Data\Repository is a valid sub-namespace.'
		);
	}

	/**
	 * Brand casing is preserved rather than normalised.
	 *
	 * @return void
	 */
	public function test_allows_capitals_inside_the_product_name() {
		$errors = $this->get_errors( 'namespace-declaration.inc' );

		$this->assertArrayNotHasKey( 14, $errors, 'HostGator keeps its capital G.' );
	}

	/**
	 * A single unbraced declaration passes, and the namespace operator is not one.
	 *
	 * "namespace\get_environment()" is the namespace operator resolving a name
	 * against the current namespace. It shares a token with the declaration and is
	 * not one, so a sniff that only looked at the token would report it.
	 *
	 * @return void
	 */
	public function test_ignores_the_namespace_operator() {
		$this->assertErrorCodes( array(), 'namespace-declaration-single.inc' );
	}

	/**
	 * A file with no namespace reports nothing.
	 *
	 * The main file of a plugin or theme runs in the global namespace on purpose,
	 * so the absence of a declaration is not a violation.
	 *
	 * @return void
	 */
	public function test_ignores_a_file_with_no_namespace() {
		$this->assertErrorCodes( array(), 'namespace-declaration-none.inc' );
	}
}
