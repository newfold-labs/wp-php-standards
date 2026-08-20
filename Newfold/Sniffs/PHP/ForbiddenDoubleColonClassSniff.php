<?php
/**
 * Flags chained "::class::" fetches when the target includes PHP 7.
 *
 * @package Newfold
 */

namespace Newfold\Sniffs\PHP;

use Newfold\Sniffs\PHP\Helpers\PHPCompatibilityHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\GetTokensAsString;

/**
 * Sniff to detect invalid "::class::" usage.
 *
 * Fetching off the result of "::class", as in "Thing::class::FOO", is dynamic class
 * constant fetch, added in PHP 8.3. On PHP 7 it does not parse, so the whole file
 * fails to load rather than erroring at the point of use.
 */
class ForbiddenDoubleColonClassSniff implements Sniff {

	/**
	 * Error message, with a placeholder for the offending snippet.
	 *
	 * @var string
	 */
	const MESSAGE = 'Invalid use of "::class::". "::class" cannot be followed by another "::" before PHP 8.3. Found: "%s".';

	/**
	 * Error code reported by this sniff.
	 *
	 * @var string
	 */
	const CODE = 'ForbiddenDoubleColonClass';

	/**
	 * Registers the tokens to listen for.
	 *
	 * @return array<int|string>
	 */
	public function register() {
		return array( T_DOUBLE_COLON );
	}

	/**
	 * Processes the token and checks for chained "::class::" usage.
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

		// Skipping empty tokens rather than reading the neighbour directly means
		// "Thing::class ::FOO" and a comment between the two operators are both
		// caught. Whitespace there is legal PHP.
		$keyword = $phpcs_file->findPrevious( Tokens::$emptyTokens, ( $stack_ptr - 1 ), null, true );
		if ( false === $keyword ) {
			return;
		}

		// PHP_CodeSniffer tokenizes the "class" of a "::class" fetch as T_STRING, and
		// PHP accepts any casing, so "::CLASS" is the same construct.
		if ( T_STRING !== $tokens[ $keyword ]['code']
			|| 'class' !== strtolower( $tokens[ $keyword ]['content'] )
		) {
			return;
		}

		// The keyword has to belong to a "::class" fetch of its own. Without this a
		// class constant that happens to be named "class" would be reported.
		$operator = $phpcs_file->findPrevious( Tokens::$emptyTokens, ( $keyword - 1 ), null, true );
		if ( false === $operator || T_DOUBLE_COLON !== $tokens[ $operator ]['code'] ) {
			return;
		}

		/*
		 * Whatever follows the second "::" is not checked. A constant name, a static
		 * property or a "{$expr}" fetch are all dynamic class constant fetch and all
		 * fail to parse on PHP 7. The previous version required a plain name here,
		 * which let "Thing::class::$prop" and "Thing::class::{$name}" through.
		 */
		// Start the reported snippet at the class name so the message identifies the
		// expression rather than repeating the operators back.
		$name = $phpcs_file->findPrevious( Tokens::$emptyTokens, ( $operator - 1 ), null, true );
		if ( false === $name ) {
			$name = $operator;
		}

		$phpcs_file->addError(
			self::MESSAGE,
			$stack_ptr,
			self::CODE,
			array( GetTokensAsString::compact( $phpcs_file, $name, $stack_ptr, true ) )
		);
	}
}
