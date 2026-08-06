# WordPress PHP Standards

PHP Code Sniffer Standards for Newfold WordPress projects.

## Installation

Add this Satis repository to your `composer.json` file:

```json
"repositories": [
  {
    "type": "composer",
    "url": "https://newfold-labs.github.io/satis/"
  }
],
```

Scope the repository to this organisation so it is only consulted for our own
packages:

```json
"repositories": [
  {
    "type": "composer",
    "url": "https://newfold-labs.github.io/satis/",
    "only": ["newfold-labs/*"]
  }
],
```

Run `composer require --dev newfold-labs/wp-php-standards` from your project
root.

The `dealerdirect/phpcodesniffer-composer-installer` plugin registers the
standard with PHPCS on install, so it has to be allowed:

```json
"config": {
  "allow-plugins": {
    "dealerdirect/phpcodesniffer-composer-installer": true
  }
}
```

## Versioning

Releases follow semantic versioning, with the major reserved for changes that can
turn a passing repository red. Pin to a caret range (`^1.2.6`) rather than
`@stable` so a major lands when you choose to take it. See
[CHANGELOG.md](CHANGELOG.md) for the full policy and release history.

## Usage

Run `vendor/bin/phpcs . --standard=Newfold` from your project root to check your code.

Optionally, add a script to your `composer.json` file, so you can just run `composer run lint` to check your code.

```json
"scripts": {
  "lint": [
    "vendor/bin/phpcs . --standard=Newfold"
  ],
  "clean": [
    "vendor/bin/phpcbf . --standard=Newfold"
  ]
}
```

## Custom sniffs

On top of WordPress-Extra, WordPress-Docs and PHPCompatibilityWP, this standard adds:

| Sniff | Checks |
| --- | --- |
| `Newfold.NamingConventions.ValidHookName` | Hooks we fire are named `newfold/[context]/[action\|filter]/[name]`. |
| `Newfold.PHP.NamespaceDeclaration` | Declared namespaces start with `Newfold\WP\{Plugin\|Theme\|Module}\{Name}`. |
| `Newfold.PHP.ForbiddenDoubleColonClass` | No `::class::` chains, which do not parse before PHP 8.3. |
| `Newfold.PHP.ForbiddenUnionType` | No union type declarations, which do not parse on PHP 7. |

`WordPress.NamingConventions.ValidHookName` is excluded, since it expects lowercase
underscore-separated hook names and would report every hook named to our convention.

### Global prefixes

`WordPress.NamingConventions.PrefixAllGlobals` is configured with `nfd_` and `newfold`.
Matching is case insensitive, so those cover `NFD_` and `Newfold` too. Add your own
product prefix on top, so that searching for it finds everything the product owns:

```xml
<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
    <properties>
        <property name="prefixes" type="array">
            <element value="nfd_"/>
            <element value="newfold"/>
            <element value="nfd_performance_module_"/>
        </property>
    </properties>
</rule>
```

### Hook names

Hooks that predate the convention and cannot be renamed can be listed in your ruleset:

```xml
<rule ref="Newfold.NamingConventions.ValidHookName">
    <properties>
        <property name="allowed_hook_names" type="array">
            <element value="nfd_build_url"/>
        </property>
    </properties>
</rule>
```

A hook name without the `newfold/` prefix is reported as a warning, because the hook
naming standard and the PHP standard do not yet agree on what an unprefixed hook name
means. Promote it once your project is on the convention:

```xml
<rule ref="Newfold.NamingConventions.ValidHookName.MissingVendorPrefix">
    <type>error</type>
</rule>
```

## Additional Notes

- Append the `-s` flag to see the internal names of the rules.
- Add `--runtime-set testVersion 8.1-` to check against a different PHP version range.
- Add a custom `phpcs.xml` file to your project to customize the ruleset or your desired configuration.

The standard defaults to `testVersion` `7.4-` and `minimum_supported_wp_version` `6.6`,
which are the floors in the [support matrix](https://newfold-labs.github.io/standards/platform/wordpress/support-matrix.html).
Override them if your project supports a narrower range:

```xml
<?xml version="1.0"?>
<ruleset name="Project Rules">
    <rule ref="Newfold"/>
    <config name="testVersion" value="8.1-"/>
    <config name="minimum_supported_wp_version" value="6.7"/>
</ruleset>
```

### Additional Documentation

- https://github.com/squizlabs/PHP_CodeSniffer
- https://github.com/PHPCompatibility/PHPCompatibilityWP
- https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards

## IDE Integration

Some IDE integrations of PHPCS will fail to register your ruleset since it doesn't live in your project root. In order
to rectify this, place phpcs.xml at your project root:

```xml
<?xml version="1.0"?>
<ruleset name="Project Rules">
    <rule ref="Newfold"/>
</ruleset>
```

### PHPStorm Setup

1. Open up the preferences panel.
2. Go to "Languages & Frameworks" > "PHP" > "Code Sniffer".
3. Ensure the "Configuration" section has "Local" set in the dropdown. Click the "..." button.
4. Set the "PHP Code Sniffer path" to be "{projectRoot}/vendor/bin/phpcs" where "{projectRoot}" is the actual path of
   your project root.
5. Hit "OK".
6. Go to "Editor" > "Inspections" in the preference panel.
7. Click on "PHP Code Sniffer validation" under the "PHP" > "Quality tools" section.
8. Hit the refresh button next to the "Coding Standard" field on the right.
9. Select "Newfold" from the dropdown.
10. Hit "OK" to exit the preferences panel.
