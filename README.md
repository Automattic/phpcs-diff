# PHPCS Diff

The purpose of this project is to provide a mean of running [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) (aka PHPCS) checks on top of file(s) stored in a version control system and reporting issues introduced only in specific revision(s).

Reporting only new issues for specific revision migth be important in case the PHPCS is being introduced later in the development cycle and there are no resources for cleaning up all existing issues.

# Installation

## Pre-requisities

Along a working [WordPress](wordpress.org) installation you'll need a [WP CLI](wp-cli.org) installed since you can interact with the plugin via WP CLI only for now.

You also will need to have the [PHP CodeSniffer installed on your server](https://github.com/squizlabs/PHP_CodeSniffer#installation).

## Installation steps for this project

Checkout this repository to your plugins directory and activate the plugin via standard WordPress administration.

# Configuration

In order to be able to properly use this plugin you'll have to add some constants to your wp-config.php file.

## PHPCS

If default values for running PHPCS command does not match your environment (see https://github.com/Automattic/phpcs-diff/blob/master/class-phpcs-diff.php#L5 ), you need to override those via constants located in wp-config.php of your WordPress installation:

```php
define( 'PHPCS_DIFF_COMMAND', 'phpcs' );
define( 'PHPCS_DIFF_STANDARDS', 'path/to/phpcs/standards' );
```

Alternatively, if you are using the PHPCS_Diff class outside of this plugin, you can pass those in the `(array) $options` param to class' constructor from the WP CLI command - https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L64

```php
new PHPCS_Diff( new PHPCS_Diff_SVN( $repo ), array( 'phpcs_command' => 'my_phpcs_command', 'standards_location' => 'my/standards/location' ) );
```

## SVN

### Credentials

You need to provide the plugin SVN credentials. This can be done using following constants put into wp-config.php file of your WordPress installation:

```php
define( 'PHPCS_DIFF_SVN_USERNAME', 'my_svn_username' );
define( 'PHPCS_DIFF_SVN_PASSWORD', 'my_svn_password' );
```

Alternatively, if you are using the `PHPCS_Diff` and `PHPCS_Diff_SVN` classes outside of this plugin, you can pass those via the `(array) $options` param to class' constructor from the WP CLI command - https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L64

```php
new PHPCS_Diff_SVN( $repo, array( 'svn_username' => 'my_username', 'svn_password' => 'my_password' ) );
```

### Repository

You'll have to either register your repository in the `PHPCS_Diff_SVN`'s constructor ( [example](https://github.com/Automattic/phpcs-diff/blob/master/class-phpcs-diff-svn.php#L25,L27) ) or pass your own repository to the constructor via `(array) $options` param from the WP CLI command - https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L64

```php
new PHPCS_Diff_SVN( $repo, array( 'repo_url' => 'https://plugins.svn.wordpress.org/hello-dolly' ) );
```

# Running the WP CLI command

Example command run:

```bash
wp phpcs-diff --repo="hello-dolly" --start_revision=99998 --end_revision=100000
```

For more params of the command, please, see the code directly: https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L12

# TODO:

- [ ] Create Git version control backend
- [ ] Make the WP CLI command version control backend agnostic
