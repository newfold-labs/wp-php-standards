<?php
/**
 * Enforces the Newfold hook naming convention.
 *
 * @package Newfold
 */

namespace Newfold\Sniffs\NamingConventions;

use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\TextStrings;
use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use WordPressCS\WordPress\Helpers\WPHookHelper;

/**
 * Checks that hooks we fire are named "newfold/[context]/[action|filter]/[name]".
 *
 * Only the functions that invoke a hook are checked. The name passed to
 * add_action() or add_filter() belongs to whoever declared the hook, so there is
 * nothing to enforce at that end.
 *
 * Hook names built at runtime are checked as far as they can be. An embedded
 * variable stands in for one path segment, so "newfold/{$context}/action/onSave"
 * still has its prefix, type and name checked while the context is left alone.
 */
class ValidHookNameSniff extends AbstractFunctionParameterSniff {

	/**
	 * Name of the group of functions this sniff matches.
	 *
	 * @var string
	 */
	protected $group_name = 'hook_invoke_functions';

	/**
	 * Stands in for a part of the hook name that is only known at runtime.
	 *
	 * A NUL byte cannot occur in the source of a string literal, so it cannot
	 * collide with anything an author actually wrote. "\0" in a double quoted
	 * string is two characters of source, a backslash and a zero.
	 *
	 * @var string
	 */
	const DYNAMIC = "\0";

	/**
	 * Hook names that are exempt from this sniff.
	 *
	 * Set in a ruleset to grandfather hooks that predate the convention and
	 * cannot be renamed without breaking whoever hooked onto them:
	 *
	 * <rule ref="Newfold.NamingConventions.ValidHookName">
	 *   <properties>
	 *     <property name="allowed_hook_names" type="array">
	 *       <element value="nfd_build_url"/>
	 *     </property>
	 *   </properties>
	 * </rule>
	 *
	 * @var array<string>
	 */
	public $allowed_hook_names = array();

	/**
	 * Builds the list of functions to match.
	 *
	 * Deprecated hooks keep the name they were deprecated under, so the two
	 * functions that fire them are left out.
	 *
	 * @return array<string, array<string, array<string>>>
	 */
	public function getGroups() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Method name is set by the parent class.
		$this->target_functions = WPHookHelper::get_functions( false );

		return parent::getGroups();
	}

	/**
	 * Checks the hook name passed to a matched call.
	 *
	 * @param int                         $stackPtr        Position of the function name token.
	 * @param string                      $group_name      Name of the group which was matched.
	 * @param string                      $matched_content Function name that was matched, lowercased.
	 * @param array<string, array<mixed>> $parameters      Parameters passed to the function.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Parameter name is set by the parent class.
		$hook_name_param = WPHookHelper::get_hook_name_param( $matched_content, $parameters );

		if ( false === $hook_name_param ) {
			return;
		}

		$hook_name = $this->get_hook_name( $hook_name_param );

		// Nothing to go on when the name carries no literal text, as with a bare
		// variable or a name assembled entirely out of expressions.
		if ( '' === str_replace( self::DYNAMIC, '', $hook_name ) ) {
			return;
		}

		if ( in_array( $hook_name, $this->allowed_hook_names, true ) ) {
			return;
		}

		$report_ptr = $this->phpcsFile->findNext(
			Tokens::$emptyTokens,
			$hook_name_param['start'],
			( $hook_name_param['end'] + 1 ),
			true
		);

		if ( false === $report_ptr ) {
			return;
		}

		$this->check_hook_name( $hook_name, $matched_content, $report_ptr );
	}

	/**
	 * Reads the hook name out of the tokens that make up the parameter.
	 *
	 * Anything whose value is only known at runtime, an embedded variable, a
	 * concatenated expression or a function call, is replaced by a placeholder
	 * rather than dropped. Dropping it would join the text either side and
	 * silently change how many path segments the name appears to have.
	 *
	 * @param array<string, mixed> $hook_name_param Parameter information from PassedParameters.
	 *
	 * @return string The hook name, with runtime values replaced by placeholders.
	 */
	private function get_hook_name( array $hook_name_param ) {
		$name = '';

		for ( $i = $hook_name_param['start']; $i <= $hook_name_param['end']; $i++ ) {
			$code = $this->tokens[ $i ]['code'];

			if ( isset( Tokens::$emptyTokens[ $code ] ) || T_STRING_CONCAT === $code ) {
				continue;
			}

			if ( isset( Tokens::$stringTokens[ $code ] ) === false ) {
				// A variable, a constant or a call. Everything it could hold is unknown,
				// including whether it contains a separator, so it counts as one segment.
				$name .= self::DYNAMIC;
				continue;
			}

			$name .= $this->mask_embeds( TextStrings::stripQuotes( $this->tokens[ $i ]['content'] ) );
		}

		return $name;
	}

	/**
	 * Replaces every embedded variable or expression in a string with a placeholder.
	 *
	 * @param string $text Contents of a string token, without its quotes.
	 *
	 * @return string
	 */
	private function mask_embeds( $text ) {
		$embeds = TextStrings::getEmbeds( $text );

		// Replace from the end of the string, so the offsets of the embeds still
		// to be handled are not shifted by the replacements already made.
		krsort( $embeds );

		foreach ( $embeds as $offset => $embed ) {
			$text = substr_replace( $text, self::DYNAMIC, $offset, strlen( $embed ) );
		}

		return $text;
	}

	/**
	 * Checks a hook name against the convention and reports what does not fit.
	 *
	 * @param string $hook_name       The hook name, with runtime values masked.
	 * @param string $matched_content Function name that was matched, lowercased.
	 * @param int    $report_ptr      Token to report violations against.
	 *
	 * @return void
	 */
	private function check_hook_name( $hook_name, $matched_content, $report_ptr ) {
		$segments  = explode( '/', $hook_name );
		$printable = $this->printable( $hook_name );

		if ( 'newfold' !== $segments[0] ) {
			/*
			 * Reported as a warning rather than an error. The hook naming standard puts a
			 * "newfold/" prefix on every hook, while the PHP standard still gives
			 * "nfd_platform_branding" as an example of good prefixing, so the two do not
			 * yet agree on what an unprefixed hook name means. Promote it in a ruleset
			 * with <type>error</type> once they do.
			 *
			 * A masked first segment could hold anything, including the prefix.
			 */
			if ( false === $this->is_dynamic( $segments[0] ) ) {
				$this->phpcsFile->addWarning(
					'Hook names should start with the "newfold/" vendor prefix. Found: "%s".',
					$report_ptr,
					'MissingVendorPrefix',
					array( $printable )
				);
			}

			return;
		}

		/*
		 * A masked segment can expand to contain separators, so a name that is short
		 * may still be complete once it runs. One that is already too long cannot
		 * become shorter, so that is the only length worth reporting when the name is
		 * built at runtime.
		 */
		$expected_segments = 4;
		$is_dynamic        = $this->is_dynamic( $hook_name );

		if ( count( $segments ) !== $expected_segments
			&& ( false === $is_dynamic || count( $segments ) > $expected_segments )
		) {
			$this->phpcsFile->addError(
				'Hook names should be "newfold/[context]/[action|filter]/[name]". Found: "%s".',
				$report_ptr,
				'InvalidStructure',
				array( $printable )
			);

			return;
		}

		if ( isset( $segments[1] ) ) {
			$this->check_context( $segments[1], $printable, $report_ptr );
		}

		if ( isset( $segments[2] ) ) {
			$this->check_type( $segments[2], $matched_content, $printable, $report_ptr );
		}

		if ( isset( $segments[3] ) ) {
			$this->check_name( $segments[3], $printable, $report_ptr );
		}
	}

	/**
	 * Checks the context segment, which is a slug of the module, plugin or theme.
	 *
	 * @param string $context    The context segment.
	 * @param string $printable  The whole hook name, for the error message.
	 * @param int    $report_ptr Token to report violations against.
	 *
	 * @return void
	 */
	private function check_context( $context, $printable, $report_ptr ) {
		if ( $this->is_dynamic( $context ) ) {
			return;
		}

		if ( 1 === preg_match( '`^[a-z0-9]+(?:-[a-z0-9]+)*$`', $context ) ) {
			return;
		}

		$this->phpcsFile->addError(
			'The context in a hook name should be a lowercase slug, such as "help-center". Found: "%s" in "%s".',
			$report_ptr,
			'InvalidContext',
			array( $this->printable( $context ), $printable )
		);
	}

	/**
	 * Checks that the type segment is "action" or "filter" and matches the call.
	 *
	 * @param string $type            The type segment.
	 * @param string $matched_content Function name that was matched, lowercased.
	 * @param string $printable       The whole hook name, for the error message.
	 * @param int    $report_ptr      Token to report violations against.
	 *
	 * @return void
	 */
	private function check_type( $type, $matched_content, $printable, $report_ptr ) {
		if ( $this->is_dynamic( $type ) ) {
			return;
		}

		if ( 'action' !== $type && 'filter' !== $type ) {
			$this->phpcsFile->addError(
				'A hook name should say whether it is an "action" or a "filter". Found: "%s" in "%s".',
				$report_ptr,
				'InvalidType',
				array( $this->printable( $type ), $printable )
			);

			return;
		}

		$expected = ( 0 === strpos( $matched_content, 'do_action' ) ) ? 'action' : 'filter';

		if ( $expected === $type ) {
			return;
		}

		$this->phpcsFile->addError(
			'%s() fires a %s, but "%s" is named as a %s.',
			$report_ptr,
			'TypeMismatch',
			array( $matched_content, $expected, $printable, $type )
		);
	}

	/**
	 * Checks that the name segment is camel case, starting with a lowercase letter.
	 *
	 * A dynamic hook surfaces a value after a colon, as in
	 * "registerPostType:page". Only the part in front of the colon is a name.
	 *
	 * @param string $name       The name segment.
	 * @param string $printable  The whole hook name, for the error message.
	 * @param int    $report_ptr Token to report violations against.
	 *
	 * @return void
	 */
	private function check_name( $name, $printable, $report_ptr ) {
		$colon = strpos( $name, ':' );

		if ( false !== $colon ) {
			$name = substr( $name, 0, $colon );
		}

		/*
		 * With no colon to close it off, a masked name is a prefix of the real one, so
		 * there is nothing to check yet.
		 */
		if ( $this->is_dynamic( $name ) ) {
			return;
		}

		if ( 1 === preg_match( '`^[a-z][a-zA-Z0-9]*$`', $name ) ) {
			return;
		}

		$this->phpcsFile->addError(
			'The name in a hook should be camel case starting with a lowercase letter, such as "beforeSave". Found: "%s" in "%s".',
			$report_ptr,
			'NotCamelCase',
			array( $this->printable( $name ), $printable )
		);
	}

	/**
	 * Reports whether any part of a string is only known at runtime.
	 *
	 * @param string $text Text that has been through the masking.
	 *
	 * @return bool
	 */
	private function is_dynamic( $text ) {
		return false !== strpos( $text, self::DYNAMIC );
	}

	/**
	 * Makes a masked string readable again for an error message.
	 *
	 * @param string $text Text that has been through the masking.
	 *
	 * @return string
	 */
	private function printable( $text ) {
		return str_replace( self::DYNAMIC, '{...}', $text );
	}
}
