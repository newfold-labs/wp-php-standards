<?php
/**
 * Enforces the Newfold namespace convention.
 *
 * @package Newfold
 */

namespace Newfold\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\Namespaces;

/**
 * Checks that declared namespaces start with "Newfold\WP\{Plugin|Theme|Module}\{Name}".
 *
 * Only the first four parts are fixed. Anything below the product name is up to
 * the product, so "Newfold\WP\Module\Staging\Data\Repository" is fine.
 *
 * A file with no namespace declaration is left alone. The main file of a plugin
 * or theme is expected to run in the global namespace, since it defines the
 * constants and the plugin header that WordPress reads.
 */
class NamespaceDeclarationSniff implements Sniff {

	/**
	 * The vendor part, which every namespace starts with.
	 *
	 * @var string
	 */
	const VENDOR = 'Newfold';

	/**
	 * The platform part, which follows the vendor.
	 *
	 * @var string
	 */
	const PLATFORM = 'WP';

	/**
	 * The kinds of thing we ship, one of which follows the platform.
	 *
	 * @var array<string>
	 */
	const TYPES = array( 'Plugin', 'Theme', 'Module' );

	/**
	 * Registers the tokens to listen for.
	 *
	 * @return array<int|string>
	 */
	public function register() {
		return array( T_NAMESPACE );
	}

	/**
	 * Checks a namespace declaration.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$name = Namespaces::getDeclaredName( $phpcs_file, $stack_ptr );

		// False for the namespace operator and for a parse error. An empty string for
		// "namespace {}", which is a deliberate declaration of the global namespace.
		if ( false === $name || '' === $name ) {
			return;
		}

		$segments = explode( '\\', $name );
		$expected = sprintf(
			'%s\\%s\\{%s}\\{Name}',
			self::VENDOR,
			self::PLATFORM,
			implode( '|', self::TYPES )
		);

		if ( self::VENDOR !== $segments[0] ) {
			$this->report_prefix( $phpcs_file, $stack_ptr, $segments[0], self::VENDOR, $name, $expected );
			return;
		}

		if ( isset( $segments[1] ) && self::PLATFORM !== $segments[1] ) {
			$this->report_prefix( $phpcs_file, $stack_ptr, $segments[1], self::PLATFORM, $name, $expected );
			return;
		}

		if ( isset( $segments[2] ) && false === in_array( $segments[2], self::TYPES, true ) ) {
			$phpcs_file->addError(
				'A namespace should say what it belongs to. Expected one of %s, but found "%s" in "%s".',
				$stack_ptr,
				'InvalidPrefix',
				array( '"' . implode( '", "', self::TYPES ) . '"', $segments[2], $name )
			);

			return;
		}

		if ( count( $segments ) < 4 ) {
			$phpcs_file->addError(
				'A namespace should name the plugin, theme or module it belongs to. Expected "%s", found "%s".',
				$stack_ptr,
				'MissingName',
				array( $expected, $name )
			);

			return;
		}

		/*
		 * Only the product name is checked. What a product puts below it is its own
		 * business, and the standard says nothing about it.
		 */
		if ( 1 === preg_match( '`^[A-Z][a-zA-Z0-9]*$`', $segments[3] ) ) {
			return;
		}

		$phpcs_file->addError(
			'The name in a namespace should be upper camel case, with the casing of the brand preserved as in "HostGator". Found "%s" in "%s".',
			$stack_ptr,
			'InvalidName',
			array( $segments[3], $name )
		);
	}

	/**
	 * Reports a namespace whose fixed prefix does not match.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param int    $stack_ptr  Position of the T_NAMESPACE token.
	 * @param string $found      The part that does not match.
	 * @param string $wanted     What that part should have been.
	 * @param string $name       The whole namespace name.
	 * @param string $expected   The whole expected form, for the message.
	 *
	 * @return void
	 */
	private function report_prefix( File $phpcs_file, $stack_ptr, $found, $wanted, $name, $expected ) {
		$phpcs_file->addError(
			'Namespaces should start with "%s". Expected "%s" but found "%s" in "%s".',
			$stack_ptr,
			'InvalidPrefix',
			array( $expected, $wanted, $found, $name )
		);
	}
}
