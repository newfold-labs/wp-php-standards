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

		$valid_type_tokens = $this->get_valid_type_token_codes();

		// Union type: "type | type". Left side must be a type (T_STRING, T_ARRAY, T_NAME_QUALIFIED, etc.), not e.g. T_VARIABLE.
		if ( ! in_array( $tokens[ $prev ]['code'], $valid_type_tokens, true ) ) {
			return;
		}

		// Right side: same set (T_STRING, T_NS_SEPARATOR, T_ARRAY, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, etc.).
		if ( ! in_array( $tokens[ $next ]['code'], $valid_type_tokens, true ) ) {
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
	 * Token codes that can appear as or within a type (either side of the union pipe).
	 *
	 * Includes built-in types (T_ARRAY, T_CALLABLE), PHP 8+ name tokens (T_NAME_QUALIFIED,
	 * T_NAME_FULLY_QUALIFIED), and T_STRING / T_NS_SEPARATOR for classic tokenization.
	 *
	 * @return array<int|string>
	 */
	private function get_valid_type_token_codes() {
		static $codes = null;
		if ( null !== $codes ) {
			return $codes;
		}

		$codes = array(
			T_STRING,
			T_NS_SEPARATOR,
			T_ARRAY,
			T_CALLABLE,
			T_FALSE,
			T_TRUE,
			T_NULL,
		);
		if ( defined( 'T_NAME_QUALIFIED' ) ) {
			$codes[] = T_NAME_QUALIFIED;
		}
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
			$codes[] = T_NAME_FULLY_QUALIFIED;
		}
		return $codes;
	}

	/**
	 * Check if the pipe at $stack_ptr is inside a type declaration (return or parameter type).
	 *
	 * Restricts to: ( and , only when they belong to a function/closure parameter list;
	 * : only when it is the return-type colon after a function signature. Excludes
	 * T_CATCH (e.g. multi-catch), bitwise in calls like foo(A | B), and ternary :.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param array<int, array<string, mixed>> $tokens   The token stack.
	 * @param int $left_type_ptr Position of the last token of the left type (e.g. T_STRING, T_ARRAY, T_NAME_QUALIFIED).
	 * @return bool True if in type declaration context.
	 */
	private function is_in_type_declaration_context( File $phpcs_file, array $tokens, $left_type_ptr ) {
		$ptr = $left_type_ptr;

		// Walk backward through the left type: (T_NS_SEPARATOR T_STRING)* or a single type token (T_ARRAY, T_NAME_QUALIFIED, etc.).
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

		// Token immediately before the left type (skipping whitespace).
		$before_type = $phpcs_file->findPrevious( T_WHITESPACE, $ptr - 1, null, true );
		if ( false === $before_type ) {
			return false;
		}

		$code = $tokens[ $before_type ]['code'];

		if ( T_OPEN_PARENTHESIS === $code ) {
			return $this->is_function_or_closure_parameter_list( $tokens, $before_type );
		}

		if ( T_COMMA === $code ) {
			$opener = $this->find_parameter_list_opener( $tokens, $before_type );
			return false !== $opener && $this->is_function_or_closure_parameter_list( $tokens, $opener );
		}

		if ( T_COLON === $code ) {
			return $this->is_return_type_colon( $phpcs_file, $tokens, $before_type );
		}

		return false;
	}

	/**
	 * Check if the given open parenthesis is the parameter list of a function or closure.
	 *
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $open_paren_ptr Position of T_OPEN_PARENTHESIS.
	 * @return bool True if the paren belongs to T_FUNCTION or T_CLOSURE; false for T_CATCH, calls, etc.
	 */
	private function is_function_or_closure_parameter_list( array $tokens, $open_paren_ptr ) {
		$owner = $this->find_parenthesis_owner( $tokens, $open_paren_ptr );
		if ( false === $owner ) {
			return false;
		}
		$owner_code = $tokens[ $owner ]['code'];
		if ( T_CATCH === $owner_code ) {
			return false;
		}
		return T_FUNCTION === $owner_code || T_CLOSURE === $owner_code || T_FN === $owner_code;
	}

	/**
	 * Find the token that owns the given opening parenthesis (e.g. T_FUNCTION, T_CATCH).
	 *
	 * Short-circuits when it hits a statement boundary (semicolon, open/close curly brace) to
	 * avoid an unbounded linear scan on large files.
	 *
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $open_paren_ptr Position of T_OPEN_PARENTHESIS.
	 * @return int|false Position of owner token, or false if not found.
	 */
	private function find_parenthesis_owner( array $tokens, $open_paren_ptr ) {
		static $stop_tokens = null;
		if ( null === $stop_tokens ) {
			$stop_tokens = array(
				T_SEMICOLON           => true,
				T_OPEN_CURLY_BRACKET  => true,
				T_CLOSE_CURLY_BRACKET => true,
			);
		}

		for ( $ptr = $open_paren_ptr - 1; $ptr >= 0; $ptr-- ) {
			if ( isset( $tokens[ $ptr ]['parenthesis_opener'] )
				&& $tokens[ $ptr ]['parenthesis_opener'] === $open_paren_ptr ) {
				return $ptr;
			}
			if ( isset( $stop_tokens[ $tokens[ $ptr ]['code'] ] ) ) {
				break;
			}
		}
		return false;
	}

	/**
	 * Find the opening parenthesis of the parameter list that contains the given comma.
	 *
	 * Handles both T_OPEN_PARENTHESIS and T_TYPE_OPEN_PARENTHESIS (PHP 8+ tokenizer).
	 *
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $comma_ptr Position of T_COMMA.
	 * @return int|false Position of T_OPEN_PARENTHESIS or T_TYPE_OPEN_PARENTHESIS, or false.
	 */
	private function find_parameter_list_opener( array $tokens, $comma_ptr ) {
		$type_close = defined( 'T_TYPE_CLOSE_PARENTHESIS' ) ? T_TYPE_CLOSE_PARENTHESIS : -1;
		$type_open  = defined( 'T_TYPE_OPEN_PARENTHESIS' ) ? T_TYPE_OPEN_PARENTHESIS : -1;

		$depth = 0;
		for ( $ptr = $comma_ptr - 1; $ptr >= 0; $ptr-- ) {
			$code = $tokens[ $ptr ]['code'];
			if ( T_CLOSE_PARENTHESIS === $code || $type_close === $code ) {
				$depth++;
			} elseif ( T_OPEN_PARENTHESIS === $code || $type_open === $code ) {
				if ( 0 === $depth ) {
					return $ptr;
				}
				$depth--;
			}
		}
		return false;
	}

	/**
	 * Check if the given colon is a return-type colon (after a function signature's closing paren).
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $colon_ptr Position of T_COLON.
	 * @return bool True if this is "function(...) : ReturnType".
	 */
	private function is_return_type_colon( File $phpcs_file, array $tokens, $colon_ptr ) {
		$before_colon = $phpcs_file->findPrevious( T_WHITESPACE, $colon_ptr - 1, null, true );
		if ( false === $before_colon ) {
			return false;
		}
		$code = $tokens[ $before_colon ]['code'];
		// PHPCS converts ")" before return type to T_TYPE_CLOSE_PARENTHESIS in type context (PHP 8+).
		$is_close_paren = ( T_CLOSE_PARENTHESIS === $code )
			|| ( defined( 'T_TYPE_CLOSE_PARENTHESIS' ) && T_TYPE_CLOSE_PARENTHESIS === $code );
		if ( ! $is_close_paren ) {
			return false;
		}
		$close_paren = $before_colon;
		$owner       = $this->find_parenthesis_owner_by_closer( $tokens, $close_paren );
		if ( false !== $owner ) {
			$owner_code = $tokens[ $owner ]['code'];
			if ( T_FUNCTION === $owner_code || T_CLOSURE === $owner_code || T_FN === $owner_code ) {
				return true;
			}
		}
		// Fallback: find matching open paren by bracket count (handles T_TYPE_CLOSE_PARENTHESIS etc.),
		// then look for T_FUNCTION, T_CLOSURE, or T_FN.
		$open_paren = $this->find_matching_open_paren( $tokens, $close_paren );
		if ( false === $open_paren ) {
			return false;
		}
		for ( $ptr = $open_paren - 1; $ptr >= 0; $ptr-- ) {
			if ( T_WHITESPACE === $tokens[ $ptr ]['code'] ) {
				continue;
			}
			// Skip function/method name (T_STRING) so we reach T_FUNCTION, T_CLOSURE, or T_FN.
			if ( T_STRING === $tokens[ $ptr ]['code'] ) {
				continue;
			}
			$code = $tokens[ $ptr ]['code'];
			return T_FUNCTION === $code || T_CLOSURE === $code || T_FN === $code;
		}
		return false;
	}

	/**
	 * Find the position of the opening parenthesis matching the closing paren at $close_ptr.
	 *
	 * Handles both T_CLOSE_PARENTHESIS and T_TYPE_CLOSE_PARENTHESIS (PHP 8+ tokenizer).
	 *
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $close_ptr Position of T_CLOSE_PARENTHESIS or T_TYPE_CLOSE_PARENTHESIS.
	 * @return int|false Position of T_OPEN_PARENTHESIS or T_TYPE_OPEN_PARENTHESIS, or false.
	 */
	private function find_matching_open_paren( array $tokens, $close_ptr ) {
		$type_close = defined( 'T_TYPE_CLOSE_PARENTHESIS' ) ? T_TYPE_CLOSE_PARENTHESIS : -1;
		$type_open  = defined( 'T_TYPE_OPEN_PARENTHESIS' ) ? T_TYPE_OPEN_PARENTHESIS : -1;

		$depth = 0;
		for ( $ptr = $close_ptr - 1; $ptr >= 0; $ptr-- ) {
			$code = $tokens[ $ptr ]['code'];
			if ( T_CLOSE_PARENTHESIS === $code || $type_close === $code ) {
				$depth++;
			} elseif ( T_OPEN_PARENTHESIS === $code || $type_open === $code ) {
				if ( 0 === $depth ) {
					return $ptr;
				}
				$depth--;
			}
		}
		return false;
	}

	/**
	 * Find the token that owns the given closing parenthesis (e.g. T_FUNCTION has parenthesis_closer).
	 *
	 * Short-circuits on statement boundaries to avoid an unbounded linear scan on large files.
	 * The owner (e.g. T_FUNCTION) always appears before the matching open paren, so crossing a
	 * curly brace or semicolon means we have gone too far.
	 *
	 * @param array<int, array<string, mixed>> $tokens Token stack.
	 * @param int $close_paren_ptr Position of T_CLOSE_PARENTHESIS.
	 * @return int|false Position of owner token, or false if not found.
	 */
	private function find_parenthesis_owner_by_closer( array $tokens, $close_paren_ptr ) {
		static $stop_tokens = null;
		if ( null === $stop_tokens ) {
			$stop_tokens = array(
				T_SEMICOLON           => true,
				T_OPEN_CURLY_BRACKET  => true,
				T_CLOSE_CURLY_BRACKET => true,
			);
		}

		for ( $ptr = $close_paren_ptr - 1; $ptr >= 0; $ptr-- ) {
			if ( isset( $tokens[ $ptr ]['parenthesis_closer'] )
				&& $tokens[ $ptr ]['parenthesis_closer'] === $close_paren_ptr ) {
				return $ptr;
			}
			if ( isset( $stop_tokens[ $tokens[ $ptr ]['code'] ] ) ) {
				break;
			}
		}
		return false;
	}
}
