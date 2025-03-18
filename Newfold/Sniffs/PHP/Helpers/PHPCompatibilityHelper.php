<?php

namespace Newfold\Sniffs\PHP\Helpers;

use PHP_CodeSniffer\Config;

/**
 * Helper class for PHP compatibility checks.
 */
class PHPCompatibilityHelper {

	/**
	 * Determines if PHP 8 or newer is included in the minimum testVersion setting.
	 *
	 * This function extracts the PHP version range from the testVersion configuration
	 * (set in ruleset.xml) and checks whether the `minVersion` is PHP 8 or newer.
	 * The `testVersion` value typically follows the format `"minVersion-maxVersion"`
	 * (e.g., `"7.3-8.1"` or `"8.0-"`).
	 *
	 * Example Scenarios:
	 * - `"7.3-"`   → Sniff runs (PHP 8+ is not explicitly required).
	 * - `"7.3-7.4"` → Sniff runs (PHP 8+ is not included).
	 * - `"7.3-8.0"` → Sniff runs (PHP 7.3 is included).
	 * - `"8.0-"`   → Sniff does NOT run (PHP 8+ is explicitly required).
	 *
	 * @return bool True if `minVersion` is PHP 8 or newer, false otherwise.
	 */
	public static function is_min_test_version_php_8_or_newer() {
		// Retrieve testVersion from config.
		$test_version = Config::getConfigData( 'testVersion' ) ?? '';
		if ( empty( $test_version ) ) {
			return false;
		}

		$versions    = explode( '-', $test_version );
		$min_version = isset( $versions[0] ) ? (float) $versions[0] : null;
		if ( null !== $min_version && 8.0 <= $min_version ) {
			return true;
		}

		return false;
	}
}
