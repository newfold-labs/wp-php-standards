<?php

namespace Newfold\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Sniff to detect invalid "::class::" usage.
 */
class ForbiddenDoubleColonClassSniff implements Sniff {

	/**
	 * Registers the tokens to listen for.
	 *
	 * @return array<int>
	 */
	public function register() {
		return array( T_DOUBLE_COLON );
	}

	/**
	 * Processes the token and checks for invalid "::class::" usage.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens     = $phpcs_file->getTokens();
		$prev_token = $tokens[ $stack_ptr - 1 ] ?? null;
		$next_token = $tokens[ $stack_ptr + 1 ] ?? null;

		if (
			$prev_token
			&& 'class' === $prev_token['content']
			&& T_STRING === $prev_token['code']
			&& $next_token
			&& T_STRING === $next_token['code']
		) {
			$phpcs_file->addError(
				'Invalid use of "::class::". "::class" cannot be followed by another "::".',
				$stack_ptr,
				'ForbiddenDoubleColonClass'
			);
		}
	}
}
