# PHPCS Diff

The purpose of this project is to provide a mean of running PHPCS checks on top of file(s) stored in a version control system and reporting issues introduced only in specific revision(s).

Reporting only new issues for specific revision migth be important in case the PHPCS is being introduced later in the development cycle and there are no resources for cleaning up all existing issues.

# Installation

This project is now totally dependant on a WordPress installation and as it is now, it's meant to be installed as a [WP CLI](wp-cli.org) command.

Checkout this project to your theme or a plugin and include the `wp-cli-command.php` file for introducing a new command to your WP CLI.

You might want to conditionally include the file only in case the WordPress is loaded as part of WP CLI:

```
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( './wp-cli-command.php' );
}
```

# Configuration

This project still requires some manual updates to the code in order to make it working.

## SVN

You either need to hardcode SVN credentials to `PHPCS_Diff_SVN` class ( related code: https://github.com/Automattic/phpcs-diff/blob/master/class-phpcs-diff-svn.php#L5 ) or pass those via the `(array) $options` param to class' constructor from the WP CLI command - https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L64

## PHPCS

If default values for running PHPCS command does not match your environment (see https://github.com/Automattic/phpcs-diff/blob/master/class-phpcs-diff.php#L5 ), you either need to hardcode them to the `PHPCS_Diff` class, or pass those in the `(array) $options` param to class' constructor from the WP CLI command - https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L64

# Running the command

Example command run:

```
wp phpcs-diff --repo="hello-dolly" --start_revision=99998 --end_revision=100000
```

For more params of the command, please, see the code directly: https://github.com/Automattic/phpcs-diff/blob/master/wp-cli-command.php#L12

# TODO:

- [ ] Make configuration of the environment (phpcs command, standard's location, more repositories registration and svn credentials) easier than manually modificating the code
- [ ] Create GitHub version control backend
- [ ] Make the WP CLI command version control backend agnostic

