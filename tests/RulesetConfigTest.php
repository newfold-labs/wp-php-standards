<?php
/**
 * Tests for the configuration the ruleset ships.
 *
 * @package Newfold
 */

namespace Newfold\Tests;

/**
 * Covers the config values and sniff properties set in Newfold/ruleset.xml.
 *
 * These are not sniff logic, but they decide what every consuming repository
 * checks by default, and two of them are easy to get wrong in ways that fail
 * quietly rather than loudly.
 */
class RulesetConfigTest extends SniffTestCase {

	/**
	 * The sniff whose configured properties are under test.
	 *
	 * @var string
	 */
	protected $sniff = 'WordPress.NamingConventions.PrefixAllGlobals';

	/**
	 * Fixture directory for the prefix fixture.
	 *
	 * @var string
	 */
	protected $fixture_dir = 'NamingConventions/PrefixAllGlobals';

	/**
	 * The prefixes under test are set in the ruleset, which a restricted run skips.
	 *
	 * @var bool
	 */
	protected $use_full_ruleset = true;

	/**
	 * The configured prefixes are ones the sniff accepts.
	 *
	 * A prefix under four characters, or one that is not a legal PHP identifier, is
	 * discarded and reported against the top of the file rather than against the
	 * code. The sniff then reports every global in the codebase as unprefixed,
	 * which reads as the codebase being wrong rather than the ruleset.
	 *
	 * The ruleset reports this sniff as a warning, so both codes land in the warning
	 * list. Reading both lists anyway keeps the assertion honest if that changes.
	 *
	 * @return void
	 */
	public function test_configured_prefixes_are_accepted_by_the_sniff() {
		$reported = array_merge(
			$this->flatten( $this->get_errors( 'prefixes.inc' ) ),
			$this->flatten( $this->get_warnings( 'prefixes.inc' ) )
		);

		$this->assertNotContains(
			'WordPress.NamingConventions.PrefixAllGlobals.ShortPrefixPassed',
			$reported,
			'A configured prefix is shorter than the four characters the sniff requires.'
		);

		$this->assertNotContains(
			'WordPress.NamingConventions.PrefixAllGlobals.InvalidPrefixPassed',
			$reported,
			'A configured prefix is not a legal PHP identifier.'
		);
	}

	/**
	 * Flattens a line-keyed violation map into a plain list of codes.
	 *
	 * @param array<int, array<int, string>> $violations Violation codes keyed by line.
	 *
	 * @return array<int, string>
	 */
	private function flatten( array $violations ) {
		return array_merge( array(), ...array_values( $violations ) );
	}

	/**
	 * Prefixed globals pass and unprefixed ones report.
	 *
	 * Matching is case insensitive, so "nfd_" covers "NFD_" and "newfold" covers
	 * "Newfold". Lines 13 and 17 are the ones that would fail if that stopped
	 * being true and the uppercase variants had to be listed separately.
	 *
	 * @return void
	 */
	public function test_reports_only_the_globals_without_a_company_prefix() {
		$this->assertWarningCodes(
			array(
				19 => array( 'NonPrefixedFunctionFound' ),
				23 => array( 'NonPrefixedConstantFound' ),
				27 => array( 'NonPrefixedClassFound' ),
				31 => array( 'NonPrefixedVariableFound' ),
			),
			'prefixes.inc'
		);

		$this->assertErrorCodes( array(), 'prefixes.inc' );
	}

	/**
	 * The ruleset targets the floor of the support matrix.
	 *
	 * testVersion decides which PHP version PHPCompatibility checks against, and
	 * minimum_supported_wp_version decides how far back WordPress deprecations are
	 * reported. Both are read out of the shipped ruleset rather than a copy, so
	 * this fails if the file and the intent drift apart.
	 *
	 * @return void
	 */
	public function test_ruleset_targets_the_supported_versions() {
		$path = __DIR__ . '/../Newfold/ruleset.xml';

		$this->assertFileExists( $path );

		// LIBXML_NONET keeps the parse local, so a ruleset can never pull in anything
		// over the network while the suite runs.
		$ruleset = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );

		$this->assertNotFalse( $ruleset, 'Newfold/ruleset.xml should be readable XML.' );

		$this->assertSame( '7.4-', $this->config_value( $ruleset, 'testVersion' ) );
		$this->assertSame( '6.6', $this->config_value( $ruleset, 'minimum_supported_wp_version' ) );
	}

	/**
	 * Errors are reserved for code that does not parse.
	 *
	 * Everything else reports as a warning, which the ruleset's
	 * ignore_warnings_on_exit keeps out of the exit code. A convention this standard
	 * has only just started checking should be visible in a repository long before
	 * it is allowed to stop that repository shipping.
	 *
	 * Listing every custom sniff means adding one without deciding its severity
	 * fails here rather than landing at whatever PHP_CodeSniffer defaults to.
	 *
	 * @return void
	 */
	public function test_only_unparsable_code_is_reported_as_an_error() {
		$expected = array(
			// Union types and "::class::" chains are parse errors on the versions we
			// support, so the file does not run at all.
			'Newfold.PHP.ForbiddenUnionType'               => null,
			'Newfold.PHP.ForbiddenDoubleColonClass'        => null,

			// Conventions. Code that breaks them still runs.
			'Newfold.NamingConventions.ValidHookName'      => 'warning',
			'Newfold.PHP.NamespaceDeclaration'             => 'warning',
			'WordPress.NamingConventions.PrefixAllGlobals' => 'warning',
		);

		$path = __DIR__ . '/../Newfold/ruleset.xml';

		$this->assertFileExists( $path );

		$ruleset = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );

		$this->assertNotFalse( $ruleset, 'Newfold/ruleset.xml should be readable XML.' );

		$found = array();

		foreach ( $ruleset->rule as $rule ) {
			$ref = (string) $rule['ref'];

			if ( false === array_key_exists( $ref, $expected ) ) {
				continue;
			}

			$found[ $ref ] = isset( $rule->type ) ? (string) $rule->type : null;
		}

		// Sorted, so the assertion is about severity rather than declaration order.
		ksort( $expected );
		ksort( $found );

		$this->assertSame(
			$expected,
			$found,
			'A sniff in the ruleset does not report at the severity the standard intends.'
		);
	}

	/**
	 * Reads a <config> value out of the ruleset.
	 *
	 * @param \SimpleXMLElement $ruleset The parsed ruleset.
	 * @param string            $name    The config name to read.
	 *
	 * @return string|null The configured value, or null when it is not set.
	 */
	private function config_value( \SimpleXMLElement $ruleset, $name ) {
		foreach ( $ruleset->config as $config ) {
			if ( $name === (string) $config['name'] ) {
				return (string) $config['value'];
			}
		}

		return null;
	}
}
