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
	 * The two are reported at different severities, so both have to be read.
	 * ShortPrefixPassed is an error and InvalidPrefixPassed is a warning.
	 *
	 * @return void
	 */
	public function test_configured_prefixes_are_accepted_by_the_sniff() {
		$this->assertNotContains(
			'WordPress.NamingConventions.PrefixAllGlobals.ShortPrefixPassed',
			$this->flatten( $this->get_errors( 'prefixes.inc' ) ),
			'A configured prefix is shorter than the four characters the sniff requires.'
		);

		$this->assertNotContains(
			'WordPress.NamingConventions.PrefixAllGlobals.InvalidPrefixPassed',
			$this->flatten( $this->get_warnings( 'prefixes.inc' ) ),
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
		$this->assertErrorCodes(
			array(
				19 => array( 'NonPrefixedFunctionFound' ),
				23 => array( 'NonPrefixedConstantFound' ),
				27 => array( 'NonPrefixedClassFound' ),
				31 => array( 'NonPrefixedVariableFound' ),
			),
			'prefixes.inc'
		);
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
		$ruleset = simplexml_load_file( __DIR__ . '/../Newfold/ruleset.xml' );

		$this->assertNotFalse( $ruleset, 'Newfold/ruleset.xml should be readable XML.' );

		$this->assertSame( '7.4-', $this->config_value( $ruleset, 'testVersion' ) );
		$this->assertSame( '6.6', $this->config_value( $ruleset, 'minimum_supported_wp_version' ) );
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
