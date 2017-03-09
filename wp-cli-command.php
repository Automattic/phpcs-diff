<?php

/**
 * PHPCS Diff WP CLI command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */

class PHPCS_Diff_CLI_Command extends WP_CLI_Command {

	/**
	 * Run PHPCS against committed revisions
	 *
	 * ## OPTIONS
	 * --repo=<repo>
	 * : The VIP theme to run a scan on. This can also be a top level plugins directory.
	 *
	 * --start_revision=<end-revision>
	 * : The revision to start at.
	 *
	 * --end_revision=<start-revision>
	 * : The end revision to test.
	 *
	 * [--format=<format>]
	 * : Specify the output format. Allowed values are: table(default) and markdown
	 *
	 * [--standard=<standard>]
	 * : Specify the standard which should be used. Possible values: WordPress, WordPress-VIP (default), WordPressVIPminimum
	 *
	 * [--nocache]
	 * : If present the cached value won't be used and the cache won't be updated
	 *
	 * [--ignore-diff-too-big]
	 * : If present, the command will try to get results even for diffs whic hare too big for the Deploy Queue
	 *
	 * [--excluded-exts=<excluded-exts>]
	 * : Ignore specified extensions. Use comma for separating multiple extensions
	 *
	 * ## EXAMPLES
	 * wp phpcs-diff --repo="hello-dolly" --start_revision=99998 --end_revision=100000
	 *
	 * @subcommand phpcs-diff
	 * @synopsis --repo=<repo> --start_revision=<start-revision> --end_revision=<end-revision> [--standard=<standard>] [--format=<format>] [--nocache] [--ignore-diff-too-big] [--excluded-exts=<excluded-exts>]
	 */
	public function __invoke( $args, $assoc_args ) {

		require_once( __DIR__ . 'class-phpcs-diff.php' );

		$repo = sanitize_title( $assoc_args['theme'] );
		$start_revision = absint( $assoc_args['start_revision'] );
		$end_revision = absint( $assoc_args['end_revision'] );
		if ( true === array_key_exists( 'format', $assoc_args ) && true === in_array( $assoc_args['format'], array( 'table', 'markdown' ), true ) ) {
			$format = $assoc_args['format'];
		} else {
			$format = 'table';
		}
		if ( true === array_key_exists( 'excluded-exts', $assoc_args ) && false === empty( $assoc_args['excluded-exts'] ) ) {
			$excluded_exts = array_map( 'sanitize_text_field', explode( ',', $assoc_args['excluded-exts'] ) );
		}

		$phpcs = new VIP_Phpcs();
		if ( true === array_key_exists( 'ignore-diff-too-big', $assoc_args ) ) {
			$phpcs->set_no_diff_too_big( true );
		}
		if ( true === array_key_exists( 'nocache', $assoc_args ) ) {
			$phpcs->set_nocache( true );
		}
		if ( true === array_key_exists( 'standard', $assoc_args )
		     && true === in_array( sanitize_text_field( $assoc_args['standard'] ), array( 'WordPress', 'WordPress-VIP', 'WordPressVIPminimum' ), true ) )
		{
			$phpcs->set_phpcs_standard( sanitize_text_field( $assoc_args['standard'] ) );
		}
		if ( true === isset( $excluded_exts ) && false === empty( $excluded_exts ) && true === is_array( $excluded_exts ) ) {
			$phpcs->set_excluded_extensions( $excluded_exts );
		}
		$found_issues = $phpcs->run( $repo, $start_revision, $end_revision );

		if ( is_wp_error( $found_issues ) ) {
			WP_CLI::error( $found_issues->get_error_message(), true );
		} else if ( true === is_array( $found_issues ) && true === empty( $found_issues ) ) {
			WP_CLI::line( 'There are no PHPCS issues in the diff. Yay!' );
			return;
		}

		switch( $format ) {
			case 'markdown':
				$blockers = $warnings = $notes = array();
				foreach ( $found_issues as $filename => $issues ) {
					foreach ( $issues as $line => $line_issues ) {
						foreach( $line_issues as $issue ) {
							if ( 'ERROR' === $issue['level'] ) {
								$blockers[] = '* ' . $filename . "#L" . $line . ' : ' . ltrim( $issue['message'], '[ x]' );
							} else if ( 'WARNING' === $issue['level'] ) {
								$warnings[] = '* ' . $filename . "#L" . $line . ' : ' . ltrim( $issue['message'], '[ x]' );
							} else if ( 'NOTE' === $issue['level'] ) {
								$notes[] = '* ' . $filename . "#L" . $line . ' : ' . ltrim( $issue['message'], '[ x]' );
							}
						}
					}
				}
				if ( false === empty( $blockers ) ) {
					WP_CLI::line( '### Blockers' );
					foreach ( $blockers as $blocker ) {
						WP_CLI::line( $blocker );
					}
				}
				if ( false === empty( $warnings ) ) {
					WP_CLI::line( '### Warnings' );
					foreach ( $warnings as $warning ) {
						WP_CLI::line( $warning );
					}
				}
				if ( false === empty( $notes ) ) {
					WP_CLI::line( '### Notes' );
					foreach( $notes as $note ) {
						WP_CLI::line( $note );
					}
				}
				break;
			case 'table':
			default:
				foreach( $found_issues as $filename => $issues ) {
					$issue_display = array();
					WP_CLI::line( 'File: ' . $filename . ' (' . count( $issues ) . ')' );
					foreach( $issues as $line => $line_issues ) {
						foreach( $line_issues as $issue ) {
							$issue_display[] = array(
								'line' => $line,
								'level' => str_replace( array( 'ERROR', 'WARNING' ), array( 'Blocker', 'Warning' ), $issue['level'] ),
								'message' => ltrim( $issue['message'], '[ x]' ),
							);
						}
					}
					WP_CLI\Utils\format_items( 'table', $issue_display, array( 'line', 'level', 'message' ) );
				}
		}

	}
}

WP_CLI::add_command( 'phpcs-diff', 'PHPCS_Diff_CLI_Command' );