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
	 * Resets state that leaks between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
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
	protected function get_errors( $fixture, $test_version = '7.3-' ) {
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
	protected function get_warnings( $fixture, $test_version = '7.3-' ) {
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
	protected function assertErrorsOnLines( array $expected_lines, $fixture, $test_version = '7.3-' ) {
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
		$config->sniffs    = array( $this->sniff );

		$file = new LocalFile( $path, new Ruleset( $config ), $config );
		$file->process();

		$violations = ( 'warnings' === $type ) ? $file->getWarnings() : $file->getErrors();

		$found = array();
		foreach ( $violations as $line => $columns ) {
			foreach ( $columns as $messages ) {
				foreach ( $messages as $message ) {
					$found[ $line ][] = $message['source'];
				}
			}
		}

		ksort( $found );

		return $found;
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
