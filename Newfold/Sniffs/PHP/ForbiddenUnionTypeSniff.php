<?php

namespace Newfold\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use Newfold\Sniffs\PHP\Helpers\PHPCompatibilityHelper;

/**
 * Sniff to detect PHP 8.0 union type syntax (e.g. "array | \WP_Error") in type declarations.
 *
 * Union types cause parse errors on PHP 7.x. Use docblocks for union types when targeting PHP 7.
 */
class ForbiddenUnionTypeSniff implements Sniff {

	/**
	 * Registers the tokens to listen for.
	 *
	 * T_BITWISE_OR: pipe in type context on PHP 7 (or when tokenizer doesn't distinguish).
	 * T_TYPE_UNION: pipe in type context on PHP 8+ tokenizer.
	 *
	 * @return array<int|string>
	 */
	public function register() {
		$tokens = array( T_BITWISE_OR );
		if ( defined( 'T_TYPE_UNION' ) ) {
			$tokens[] = T_TYPE_UNION;
		}
		return $tokens;
	}

	/**
	 * Processes the token and checks for union type usage in type declarations.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		// Do not run this if the minimum test version is PHP 8 or higher.
		if ( PHPCompatibilityHelper::is_min_test_version_php_8_or_newer() ) {
			return;
		}

		$tokens = $phpcs_file->getTokens();

		$prev = $phpcs_file->findPrevious( T_WHITESPACE, $stack_ptr - 1, null, true );
		$next = $phpcs_file->findNext( T_WHITESPACE, $stack_ptr + 1, null, true );

		if ( false === $prev || false === $next ) {
			return;
		}

		// Union type: "type | type". Left side must be a type name (T_STRING), not e.g. T_VARIABLE.
		if ( T_STRING !== $tokens[ $prev ]['code'] ) {
			return;
		}

		// Right side: T_STRING (e.g. "string") or T_NS_SEPARATOR (e.g. "\WP_Error").
		if ( T_STRING !== $tokens[ $next ]['code'] && T_NS_SEPARATOR !== $tokens[ $next ]['code'] ) {
			return;
		}

		// Confirm we're in a type declaration context (return type or parameter type),
		// not in a bitwise expression like $a | $b.
		if ( ! $this->is_in_type_declaration_context( $phpcs_file, $tokens, $prev ) ) {
			return;
		}

		$phpcs_file->addError(
			'Union type declarations (e.g. "array | \\WP_Error") are not supported in PHP 7. Use docblock @return or @param for union types when targeting PHP 7 compatibility.',
			$stack_ptr,
			'ForbiddenUnionType'
		);
	}

	/**
	 * Check if the pipe at $stack_ptr is inside a type declaration (return or parameter type).
	 *
	 * Walks backward from the left type name to find ":" (return type) or "," or "(" (parameter).
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param array<int, array<string, mixed>> $tokens   The token stack.
	 * @param int $left_type_ptr Position of the token that starts the left type (T_STRING).
	 * @return bool True if in type declaration context.
	 */
	private function is_in_type_declaration_context( File $phpcs_file, array $tokens, $left_type_ptr ) {
		$ptr = $left_type_ptr;

		// Walk backward through the left type: type is (T_NS_SEPARATOR T_STRING)*.
		while ( $ptr > 0 ) {
			$prev = $phpcs_file->findPrevious( T_WHITESPACE, $ptr - 1, null, true );
			if ( false === $prev ) {
				break;
			}
			if ( T_STRING === $tokens[ $prev ]['code'] || T_NS_SEPARATOR === $tokens[ $prev ]['code'] ) {
				$ptr = $prev;
				continue;
			}
			break;
		}

		// $ptr is now the start of the left type. Token before (skipping whitespace) should be : or , or (.
		$before_type = $phpcs_file->findPrevious( T_WHITESPACE, $ptr - 1, null, true );
		if ( false === $before_type ) {
			return false;
		}

		$code = $tokens[ $before_type ]['code'];
		return T_COLON === $code || T_COMMA === $code || T_OPEN_PARENTHESIS === $code;
	}
}
