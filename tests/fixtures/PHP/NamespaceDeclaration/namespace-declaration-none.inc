<?php
/**
 * Fixture for the NamespaceDeclaration sniff: a file with no namespace.
 *
 * The main file of a plugin runs in the global namespace, since it carries the
 * plugin header and the constants the rest of the codebase reads.
 *
 * @package Newfold
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NFD_STAGING_VERSION', '1.0.0' );
define( 'NFD_STAGING_FILE', __FILE__ );
