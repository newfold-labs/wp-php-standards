<?php
/**
 * PHPUnit bootstrap.
 *
 * PHP_CodeSniffer defines a number of its own token constants (T_BITWISE_OR and
 * friends) when the Tokens class is first loaded. Sniffs reference them at
 * registration time, so they have to exist before any Ruleset is built.
 *
 * @package Newfold
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI bootstrap, WP_Filesystem is not loaded and STDERR is the right stream for a setup failure.
	fwrite( STDERR, 'Dependencies are not installed. Run "composer install" first.' . PHP_EOL );
	exit( 1 );
}

require_once $autoload;
require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/autoload.php';

if ( ! defined( 'PHP_CODESNIFFER_IN_TESTS' ) ) {
	define( 'PHP_CODESNIFFER_IN_TESTS', true );
}

if ( ! defined( 'PHP_CODESNIFFER_VERBOSITY' ) ) {
	define( 'PHP_CODESNIFFER_VERBOSITY', 0 );
}

new PHP_CodeSniffer\Util\Tokens();
