<?php
/**
 * Base class for sniff tests.
 *
 * @package Newfold
 */

namespace Newfold\Tests;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Runs a single sniff over a fixture file and reports what it found.
 *
 * This drives PHP_CodeSniffer through Config, Ruleset and LocalFile, which are
 * the same public classes the phpcs binary uses. PHP_CodeSniffer also ships an
 * AbstractSniffUnitTest, but it reads test paths out of globals that only its
 * own test suite populates, and those internals are not part of its public API.
 * Building on the public classes keeps this harness working across PHP_CodeSniffer
 * versions.
 */
abstract class SniffTestCase extends TestCase {

	/**
	 * The sniff under test, in PHP_CodeSniffer dot notation.
	 *
	 * @var string
	 */
	protected $sniff = '';

	/**
	 * Directory holding this sniff's fixtures, relative to tests/fixtures.
	 *
	 * @var string
	 */
	protected $fixture_dir = '';

	/**
	 * Properties to set on the sniff before the run, as a ruleset would.
	 *
	 * @var array<string, mixed>
	 */
	protected $sniff_properties = array();

	/**
	 * Whether to run the whole standard rather than only the sniff under test.
	 *
	 * Set this when the behaviour under test depends on what Newfold/ruleset.xml
	 * configures, since a restricted run never reads the ruleset. The report is
	 * filtered to the sniff under test either way.
	 *
	 * @var bool
	 */
	protected $use_full_ruleset = false;

	/**
	 * Resets state that leaks between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->sniff_properties = array();
		$this->reset_php_compatibility_helper_cache();
	}

	/**
	 * Runs the sniff under test over a fixture and returns its errors by line.
	 *
	 * @param string $fixture      Fixture file name, relative to this sniff's fixture directory.
	 * @param string $test_version Value for the testVersion config, which decides whether the
	 *                             PHP version sniffs run at all.
	 *
	 * @return array<int, array<int, string>> Error codes found, keyed by line number.
	 */
	protected function get_errors( $fixture, $test_version = '7.4-' ) {
		return $this->run_sniff( $fixture, $test_version, 'errors' );
	}

	/**
	 * Runs the sniff under test over a fixture and returns its warnings by line.
	 *
	 * @param string $fixture      Fixture file name, relative to this sniff's fixture directory.
	 * @param string $test_version Value for the testVersion config.
	 *
	 * @return array<int, array<int, string>> Warning codes found, keyed by line number.
	 */
	protected function get_warnings( $fixture, $test_version = '7.4-' ) {
		return $this->run_sniff( $fixture, $test_version, 'warnings' );
	}

	/**
	 * Asserts that the sniff reports errors on exactly the given lines.
	 *
	 * Comparing the whole line map rather than asserting line by line means a new
	 * false positive fails the test, not just a missing detection.
	 *
	 * @param array<int, int> $expected_lines Line numbers expected to report an error.
	 * @param string          $fixture        Fixture file name.
	 * @param string          $test_version   Value for the testVersion config.
	 *
	 * @return void
	 */
	protected function assertErrorsOnLines( array $expected_lines, $fixture, $test_version = '7.4-' ) {
		$found = array_keys( $this->get_errors( $fixture, $test_version ) );
		sort( $found );
		sort( $expected_lines );

		$this->assertSame(
			$expected_lines,
			$found,
			sprintf( 'Errors reported on unexpected lines in %s.', $fixture )
		);
	}

	/**
	 * Asserts the exact error codes the sniff reports, keyed by line.
	 *
	 * Line numbers alone say a violation was found somewhere, not that the right
	 * one was found. A sniff with several codes needs the code asserted too, or a
	 * check reporting the wrong reason still passes.
	 *
	 * @param array<int, array<int, string>> $expected     Error codes by line, without the sniff prefix.
	 * @param string                         $fixture      Fixture file name.
	 * @param string                         $test_version Value for the testVersion config.
	 *
	 * @return void
	 */
	protected function assertErrorCodes( array $expected, $fixture, $test_version = '7.4-' ) {
		$this->assertSame(
			$this->qualify( $expected ),
			$this->get_errors( $fixture, $test_version ),
			sprintf( 'Unexpected errors in %s.', $fixture )
		);
	}

	/**
	 * Asserts the exact warning codes the sniff reports, keyed by line.
	 *
	 * @param array<int, array<int, string>> $expected     Warning codes by line, without the sniff prefix.
	 * @param string                         $fixture      Fixture file name.
	 * @param string                         $test_version Value for the testVersion config.
	 *
	 * @return void
	 */
	protected function assertWarningCodes( array $expected, $fixture, $test_version = '7.4-' ) {
		$this->assertSame(
			$this->qualify( $expected ),
			$this->get_warnings( $fixture, $test_version ),
			sprintf( 'Unexpected warnings in %s.', $fixture )
		);
	}

	/**
	 * Expands short error codes into the full sources PHP_CodeSniffer reports.
	 *
	 * @param array<int, array<int, string>> $expected Codes by line, without the sniff prefix.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function qualify( array $expected ) {
		$qualified = array();

		foreach ( $expected as $line => $codes ) {
			foreach ( $codes as $code ) {
				$qualified[ $line ][] = $this->sniff . '.' . $code;
			}
		}

		ksort( $qualified );

		return $qualified;
	}

	/**
	 * Runs the sniff and collects one violation type.
	 *
	 * @param string $fixture      Fixture file name.
	 * @param string $test_version Value for the testVersion config.
	 * @param string $type         Either "errors" or "warnings".
	 *
	 * @return array<int, array<int, string>> Violation codes keyed by line number.
	 */
	private function run_sniff( $fixture, $test_version, $type ) {
		$path = $this->fixture_path( $fixture );

		// testVersion is config data rather than a CLI setting, and it is global to the
		// process, so it has to be set before the ruleset is built.
		Config::setConfigData( 'testVersion', $test_version, true );

		$config            = new Config( array(), false );
		$config->standards = array( 'Newfold' );

		/*
		 * Restricting the sniff list makes PHP_CodeSniffer register the sniff class
		 * directly and skip the ruleset XML, so anything configured on a sniff in
		 * Newfold/ruleset.xml never reaches it. A test covering that configuration has to
		 * run the whole standard and filter the report instead.
		 */
		if ( false === $this->use_full_ruleset ) {
			$config->sniffs = array( $this->sniff );
		}

		$ruleset = new Ruleset( $config );
		$this->apply_sniff_properties( $ruleset );

		$file = new LocalFile( $path, $ruleset, $config );
		$file->process();

		$violations = ( 'warnings' === $type ) ? $file->getWarnings() : $file->getErrors();
		$prefix     = $this->sniff . '.';

		$found = array();
		foreach ( $violations as $line => $columns ) {
			foreach ( $columns as $messages ) {
				foreach ( $messages as $message ) {
					if ( 0 !== strpos( $message['source'], $prefix ) ) {
						continue;
					}

					$found[ $line ][] = $message['source'];
				}
			}
		}

		ksort( $found );

		return $found;
	}

	/**
	 * Sets the configured properties on the sniff under test.
	 *
	 * This is the same call PHP_CodeSniffer makes for a <property> element in a
	 * ruleset, so a property tested here behaves the way it will when a consuming
	 * repository configures it.
	 *
	 * @param Ruleset $ruleset The ruleset holding the sniff.
	 *
	 * @return void
	 */
	private function apply_sniff_properties( Ruleset $ruleset ) {
		if ( array() === $this->sniff_properties ) {
			return;
		}

		$this->assertArrayHasKey(
			$this->sniff,
			$ruleset->sniffCodes,
			sprintf( 'Sniff %s is not in the ruleset.', $this->sniff )
		);

		foreach ( $this->sniff_properties as $name => $value ) {
			$ruleset->setSniffProperty(
				$ruleset->sniffCodes[ $this->sniff ],
				$name,
				array(
					'scope' => 'sniff',
					'value' => $value,
				)
			);
		}
	}

	/**
	 * Resolves a fixture name to an absolute path.
	 *
	 * @param string $fixture Fixture file name.
	 *
	 * @return string
	 */
	private function fixture_path( $fixture ) {
		$path = __DIR__ . '/fixtures/' . $this->fixture_dir . '/' . $fixture;

		$this->assertFileExists( $path, sprintf( 'Fixture %s is missing.', $fixture ) );

		return $path;
	}

	/**
	 * Clears the memoised testVersion lookup in PHPCompatibilityHelper.
	 *
	 * The helper caches whether testVersion starts at PHP 8 in a private static, so
	 * without this the first test to run would fix that answer for the whole suite
	 * and any test using a different testVersion would silently read a stale value.
	 *
	 * @return void
	 */
	private function reset_php_compatibility_helper_cache() {
		$property = ( new ReflectionClass( \Newfold\Sniffs\PHP\Helpers\PHPCompatibilityHelper::class ) )
			->getProperty( 'cached_min_version_result' );

		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}
