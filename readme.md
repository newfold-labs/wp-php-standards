# WordPress PHP Standards

PHP Code Sniffer Standards for Bluehost WordPress projects.

## Installation

Run `composer require bluehost/wp-php-standards` from your project root.

Add any extra PHP Code Sniffer configuration options to your composer.json file as follows and then run `composer install`:

```json
    "extra": {
        "phpcs-config": {
            "default_standard": "Bluehost",
            "testVersion": "5.2-"
        }
    }
```

## Usage

Run `vendor/bin/phpcs .` from the project root to run checks.

See the [PHP Code Sniffer wiki](https://github.com/squizlabs/PHP_CodeSniffer/wiki) for more details.

## IDE Integration
Some IDE integrations of PHPCS  will fail to register your ruleset since it doesn't live in your project root. In order to rectify this, place phpcs.xml at your project root:

```xml
<?xml version="1.0"?>
<ruleset name="Project Rules">
	<rule ref="Bluehost" />
</ruleset>
```

### PHPStorm Setup

1. Open up the preferences panel.
2. Go to "Languages & Frameworks" > "PHP" > "Code Sniffer".
3. Ensure the "Configuration" section has "Local" set in the dropdown. Click the "..." button.
4. Set the "PHP Code Sniffer path" to be "{projectRoot}/vendor/bin/phpcs" where "{projectRoot}" is the actual path of your project root.
5. Hit "OK".
6. Go to "Editor" > "Inspections" in the preference panel.
7. Click on "PHP Code Sniffer validation" under the "PHP" > "Quality tools" section.
8. Hit the refresh button next to the "Coding Standard" field on the right.
9. Select "Bluehost" from the dropdown.
10. Hit "OK" to exit the preferences panel.
