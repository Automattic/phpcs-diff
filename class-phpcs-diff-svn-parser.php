<?php

class PHPCS_Diff_SVN_parser {

	// SVN credentials used for checking out individual revisions.
	private $svn_username = ''; // @todo: add your SVN username here
	private $svn_password = ''; // @todo: add your SVN password here

	// Used to store details about the repo the class was initialized with.
	public $repo; // Specific repository - eg.: plugin's name.
	public $repo_url; // SVN repository URL.

	function __construct( $repo ) {

		switch ( $repo ) {

			case 'hello-dolly':
				$this->repo_url = 'https://plugins.svn.wordpress.org/';
				break;

			# Add new repos here. See details at the top of this file.
		}

		$this->repo = $repo;
	}

	public function get_diff( $folder, $end_revision, $start_revision = null, $options = array() ) {
		$summarize			 = false;
		$xml 				 = false;
		$ignore_space_change = false;

		if ( isset( $options['summarize'] ) ) {
			$summarize = (bool) $options['summarize'];

			// xml is only available in summaries
			if ( $summarize && isset( $options['xml'] ) ) {
				$xml = (bool) $options['xml'];
			}
		}

		if ( isset( $options['ignore-space-change'] ) ) {
			$ignore_space_change = (bool) $options['ignore-space-change'];
		}

		$end_revision 	= (int) $end_revision;
		$folder 		= str_replace( '..', '', $folder ); // Prevent moving up a directory

		if ( $start_revision && is_numeric( $start_revision ) ) {
			$start_revision = (int) $start_revision;
		} else {
			// @todo is this really the best way to get the diff if there was no previous revision?
			$start_revision = 1;
		}

		$repo_url = esc_url_raw( trailingslashit( $this->repo_url ) . trailingslashit( $this->repo ) . $folder );

		$diff = shell_exec(
			sprintf( 'svn diff %s --non-interactive --no-auth-cache --username %s --password %s -r %d:%d %s %s %s',
				escapeshellarg( $repo_url ),
				escapeshellarg( $this->svn_username ),
				escapeshellarg( $this->svn_password ),
				(int) $start_revision,
				(int) $end_revision,
				( $summarize ? '--summarize' : '' ),
				( $xml ? '--xml' : '' ),
				( $ignore_space_change ? '-x -b' : '' )
			)
		);

		return $diff;
	}

	/**
	 * Collect information about the diff
	 *
	 * @param string $diff_file full svn .diff file to be parsed for information
	 *
	 * @return array information about the diff
	 */
	public static function parse_diff_for_info( $diff_file ){

		$files = preg_split( '/^Index: (.+)$/m', $diff_file, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		//result is a flat array with alternating 'filename', 'file_contents', 'filename', 'file_cotnents'... let's organize that into an associative array 'file_name'=>'file_contents'
		$diff_files = call_user_func_array( 'array_merge', array_map( function( $pair ) { return array( $pair[0] => $pair[1] ); }, array_chunk( $files, 2) ) );
		$results = array();
		$lines_added = $lines_removed = 0;

		foreach ( $diff_files as $file_name => $file_diff ) {

			//Remove property changes from the file_diff and store it in file_parts[1] if present
			$file_parts = preg_split( '/Property changes on: (?:.+)/', $file_diff, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
			$file_diff = $file_parts[0];
			unset( $file_parts );

			$results[$file_name] = array( 'file_name' => $file_name, 'lines_added' => 0, 'lines_removed' => 0 );

			$results[$file_name]['lines'] = array();

			$diff_lines = explode( PHP_EOL, $file_diff );
			$old_start = $new_start = 0;
			foreach( $diff_lines as $line ) {
				switch( true ) {
					case preg_match( '/^No differences encountered/', $line ):
					case preg_match( '/^$/', $line ):
						break;
					case preg_match( '/^(\-\-\-|\+\+\+)/', $line ):
						if ( "--- {$file_name} (revision 0)" === trim( str_replace( "\t", ' ', $line ) ) ) {
							$results[$file_name]['is_new_file'] = true;
						}
						break;
					case preg_match( '/^@@ [-+]([0-9]+)*,([0-9]+)* [+-]([0-9]+)*,([0-9]+)* @@/', $line, $match ):
						$old_start = $match[1];
						$new_start = $match[3];
						break;
					case preg_match( '/^ (.*)/', $line, $match ):
						$results[$file_name]['lines'][] = array(
							'old_line_number' => $old_start,
							'new_line_number' => $new_start,
							'is_context' => true,
							// 'line' => $match[1], // Might be useful for debug.
						);
						$old_start++; $new_start++;
						break;
					case preg_match( '/^\+(.*)/', $line, $match ):
						$lines_added++;
						$results[$file_name]['lines_added']++;
						$results[$file_name]['lines'][] = array(
							'new_line_number' => $new_start,
							'is_added' => true,
							// 'line' => $match[1], // Might be useful for debug.
						);
						$new_start++;
						break;
					case preg_match( '/^\-(.*)/', $line, $match ):
						$lines_removed++;
						$results[$file_name]['lines_removed']++;
						$results[$file_name]['lines'][] = array(
							'old_line_number' => $old_start,
							'is_removed' => true,
							// 'line' => $match[1], // Might be useful for debug.
						);
						$old_start++;
						break;
					case preg_match( '/^diff -r/', $line ):
						break;
				}
			}

		}
		$diff_info = array(
			'file_diffs' => $results,
		);

		return $diff_info;

	}

	public function run_phpcs_for_file_at_revision( $filename, $revision, $phpcs_command, $standards_location, $phpcs_standard ) {
		$command_string	= sprintf( 'svn cat %s --non-interactive --no-auth-cache --username %s --password %s -r %d | %s --runtime-set installed_paths %s --standard=%s --stdin-path=%s',
			escapeshellarg( esc_url_raw( $this->repo_url . $filename ) ),
			escapeshellarg( $this->svn_username ),
			escapeshellarg( $this->svn_password ),
			absint( $revision ),
			escapeshellcmd( $phpcs_command ),
			escapeshellarg( $standards_location ),
			escapeshellarg( $phpcs_standard ),
			escapeshellarg( $filename )
		);

		return shell_exec( $command_string );
	}

}