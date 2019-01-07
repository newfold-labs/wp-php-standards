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

## IDE Integration
Some IDE integrations of PHPCS  will fail to register your ruleset since it doesn't live in your project root. In order to rectify this, place phpcs.xml at your project root:

```xml
<?xml version="1.0"?>
<ruleset name="Project Rules">
	<rule ref="Bluehost" />
</ruleset>
```
