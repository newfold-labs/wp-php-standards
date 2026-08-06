<?php
/**
 * Fixture for the ValidHookName sniff.
 *
 * @package Newfold
 */

// Valid.
do_action( 'newfold/staging/action/beforeSave' );
do_action( 'newfold/help-center/action/onRenderTemplate:my-template' );
apply_filters( 'newfold/settings/filter/getTemplate', $value );
apply_filters_ref_array( 'newfold/settings/filter/isValid', array( $value ) );
do_action_ref_array( 'newfold/settings/action/afterSave', array( $value ) );

// Valid, assembled at runtime.
do_action( "newfold/{$context}/action/onSave" );
apply_filters( 'newfold/settings/filter/registerPostType:' . $post_type, $value );
do_action( $hook_name );

// Deprecated hooks keep the name they were deprecated under.
do_action_deprecated( 'bh_old_hook', array(), '1.0.0' );
apply_filters_deprecated( 'bh_old_filter', array( $value ), '1.0.0' );

// Missing the vendor prefix.
do_action( 'nfd_module_loaded' );
apply_filters( 'bluehost/settings/filter/getTemplate', $value );

// Wrong number of segments.
do_action( 'newfold/settings/beforeSave' );
do_action( 'newfold/settings/action/nested/beforeSave' );
do_action( "newfold/{$context}/action/nested/beforeSave" );

// Neither an action nor a filter.
do_action( 'newfold/settings/event/beforeSave' );

// Named as the opposite of what the call fires.
do_action( 'newfold/settings/filter/getTemplate' );
apply_filters( 'newfold/settings/action/onSave', $value );

// Context is not a lowercase slug.
do_action( 'newfold/helpCenter/action/onSave' );
do_action( 'newfold/Help_Center/action/onSave' );

// Name is not camel case.
do_action( 'newfold/settings/action/before_save' );
apply_filters( 'newfold/settings/filter/GetTemplate', $value );
do_action( 'newfold/settings/action/before-save:page' );

// Not a call to the global function.
$obj->do_action( 'nope' );
Thing::apply_filters( 'nope' );

// The vendor itself is only known at runtime, so nothing can be judged.
do_action( "{$vendor}/settings/action/onSave" );

// Passed by name rather than by position.
do_action( hook_name: 'newfold/settings/action/before_save' );
apply_filters( hook_name: 'newfold/settings/filter/getTemplate', value: $value );
