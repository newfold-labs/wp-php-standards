<?php
/**
 * Fixture for the prefixes configured on WordPress.NamingConventions.PrefixAllGlobals.
 *
 * Everything here is in the global namespace, which is the only place the sniff
 * looks. Code inside our own namespace is not prefixed.
 *
 * @package Newfold
 */

function nfd_get_platform() {}

function NFD_Get_Thing() {}

function newfold_register_modules() {}

function Newfold_Register_Two() {}

function get_platform() {}

define( 'NFD_PLATFORM_BRAND', 'bluehost' );

define( 'PLATFORM_BRAND', 'bluehost' );

class NFD_Platform {}

class Platform {}

$GLOBALS['nfd_state'] = 1;

$GLOBALS['state'] = 1;
