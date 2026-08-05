<?php
/**
 * Flags PHP 8 union type declarations when the target includes PHP 7.
 *
 * @package Newfold
 */

namespace Newfold\Sniffs\PHP;

use Newfold\Sniffs\PHP\Helpers\PHPCompatibilityHelper;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\Constants;
use PHPCSUtils\Utils\FunctionDeclarations;
use PHPCSUtils\Utils\Scopes;
use PHPCSUtils\Utils\TypeString;
use PHPCSUtils\Utils\Variables;

/**
 * Sniff to detect PHP 8.0 union type syntax (e.g. "array|\WP_Error") in type declarations.
 *
 * Union types are a parse error on PHP 7.x, so they take the whole file down rather
 * than failing at the call site. Use docblocks for union types when targeting PHP 7:
 * @param and @return on a function, @var on a property or constant.
 */
class ForbiddenUnionTypeSniff implements Sniff {

	/**
	 * Error message, with a placeholder for the offending type.
	 *
	 * @var string
	 */
	const MESSAGE = 'Union type declarations are not supported in PHP 7. Found: "%s". Use a docblock (@param, @return or @var) for union types when targeting PHP 7 compatibility.';

	/**
	 * Error code reported by this sniff.
	 *
	 * @var string
	 */
	const CODE = 'ForbiddenUnionType';

	/**
	 * Registers the tokens to listen for.
	 *
	 * Types can only appear on a function-like declaration, an OO property or,
	 * since PHP 8.3, an OO constant. Listening for the declaration rather than
	 * for the pipe character means the sniff never has to work out whether a
	 * given pipe sits in a type or in a bitwise expression.
	 *
	 * @return array<int|string>
	 */
	public function register() {
		return array(
			T_FUNCTION,
			T_CLOSURE,
			T_FN,
			T_VARIABLE,
			T_CONST,
		);
	}

	/**
	 * Processes a declaration and reports any union type it declares.
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

		switch ( $tokens[ $stack_ptr ]['code'] ) {
			case T_VARIABLE:
				$this->process_property( $phpcs_file, $stack_ptr );
				break;

			case T_CONST:
				$this->process_constant( $phpcs_file, $stack_ptr );
				break;

			default:
				$this->process_function( $phpcs_file, $stack_ptr );
				break;
		}
	}

	/**
	 * Checks the parameter types and return type of a function-like declaration.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  Position of the T_FUNCTION, T_CLOSURE or T_FN token.
	 *
	 * @return void
	 */
	private function process_function( File $phpcs_file, $stack_ptr ) {
		$parameters = FunctionDeclarations::getParameters( $phpcs_file, $stack_ptr );

		foreach ( $parameters as $parameter ) {
			$this->report_if_union(
				$phpcs_file,
				$parameter['type_hint'],
				$parameter['type_hint_token']
			);
		}

		$properties = FunctionDeclarations::getProperties( $phpcs_file, $stack_ptr );

		$this->report_if_union(
			$phpcs_file,
			$properties['return_type'],
			$properties['return_type_token']
		);
	}

	/**
	 * Checks the declared type of an OO property.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  Position of the T_VARIABLE token.
	 *
	 * @return void
	 */
	private function process_property( File $phpcs_file, $stack_ptr ) {
		// Most variables are not properties, and getMemberProperties() throws for
		// anything that is not one.
		if ( false === Scopes::isOOProperty( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		$properties = Variables::getMemberProperties( $phpcs_file, $stack_ptr );

		$this->report_if_union( $phpcs_file, $properties['type'], $properties['type_token'] );
	}

	/**
	 * Checks the declared type of an OO constant.
	 *
	 * Typed class constants are PHP 8.3, so a union on one cannot run on PHP 7
	 * either way, but reporting it keeps the message consistent with the rest.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  Position of the T_CONST token.
	 *
	 * @return void
	 */
	private function process_constant( File $phpcs_file, $stack_ptr ) {
		// Global constants declared with const have no type, and getProperties()
		// throws for anything that is not an OO constant.
		if ( false === Scopes::isOOConstant( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		$properties = Constants::getProperties( $phpcs_file, $stack_ptr );

		$this->report_if_union( $phpcs_file, $properties['type'], $properties['type_token'] );
	}

	/**
	 * Reports an error when the given type string is a union type.
	 *
	 * Disjunctive normal form types such as "(A&B)|null" are unions too, but
	 * TypeString::isUnion() deliberately excludes them, so both are checked.
	 *
	 * @param File      $phpcs_file The file being scanned.
	 * @param string    $type       The declared type, empty when none was declared.
	 * @param int|false $type_token Position of the first token of the type, or false.
	 *
	 * @return void
	 */
	private function report_if_union( File $phpcs_file, $type, $type_token ) {
		if ( '' === $type || false === $type_token ) {
			return;
		}

		if ( false === TypeString::isUnion( $type ) && false === TypeString::isDNF( $type ) ) {
			return;
		}

		$phpcs_file->addError( self::MESSAGE, $type_token, self::CODE, array( $type ) );
	}
}
