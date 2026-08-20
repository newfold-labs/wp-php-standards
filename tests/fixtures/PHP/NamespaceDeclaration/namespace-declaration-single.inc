<?php
/**
 * Fixture for the NamespaceDeclaration sniff, in the form real files use.
 *
 * @package Newfold
 */

namespace NewfoldLabs\WP\Module\Staging;

use NewfoldLabs\WP\Module\Staging\Data\Repository;

// The namespace operator, which is not a declaration and has nothing to check.
$result = namespace\get_environment();
