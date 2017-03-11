<?php

class PHPCS_Diff {

	// PHPCS configuration.
	public $phpcs_command = 'phpcs'; // You might need to provde a path to phpcs.phar file.
	public $standards_location = '~/PHP_CodeSniffer/Standards'; // @todo: adjust the path to standards

	public $version_control;

	public $allowed_extensions;

	public $excluded_extensions = array();

	public $lines_mapping;

	private $phpcs_standard = 'WordPress';

	private $nocache = false;

	private $no_diff_to_big = false;

	public function __construct( $version_control, $options = array() ) {

		if ( true === defined( 'PHPCS_DIFF_COMMAND' ) && false === empty( PHPCS_DIFF_COMMAND ) ) {
			$this->phpcs_command = PHPCS_DIFF_COMMAND;
		}
		if ( true === defined( 'PHPCS_DIFF_STANDARDS' ) && false === empty( PHPCS_DIFF_STANDARDS ) ) {
			$this->standards_location = PHPCS_DIFF_STANDARDS;
		}

		if ( true === is_array( $options ) && false === empty( $options ) ) {
			foreach( $options as $option => $value ) {
				$this->$option = $value;
			}
		}

		$this->version_control = $version_control;

		$this->allowed_extensions = array( 'php', 'js' );
	}

	public function set_nocache( $nocache = false ) {
		$this->nocache = (bool)$nocache;
	}

	public function set_no_diff_too_big( $no_diff_to_big = false ) {
		$this->no_diff_to_big = (bool)$no_diff_to_big;
	}

	public function set_phpcs_standard( $standard ) {
		if ( true === in_array( $standard, array( 'WordPress', 'WordPress-VIP', 'WordPressVIPminimum' ), true ) ) {
			$this->phpcs_standard = $standard;
		}
	}

	public function set_excluded_extensions( $excluded_exts ) {
		if ( false === is_array( $excluded_exts) ) {
			$excluded_exts = explode( ',', $excluded_exts );
		}
		$this->excluded_extensions = $excluded_exts;
	}

	public function run( $oldest_rev, $newest_rev ) {

		$oldest_rev = absint( $oldest_rev );
		$newest_rev = absint( $newest_rev );

		$cache_key	  = md5( 'phpcs_' . $this->version_control->repo . $oldest_rev . $newest_rev );
		$cache_group  = 'vip-phpcs';

		if ( true !== $this->nocache ) {
			$found_issues = false; //wp_cache_get( $cache_key, $cache_group );
			if ( false !== $found_issues ) {
				return $found_issues;
			}
		}

		$diff  = trim( $this->version_control->get_diff( $newest_rev, $oldest_rev, array( 'ignore-space-change' => true ) ) );

		$this->stop_the_insanity();

		$diff = str_replace( "\r", "\n", $diff );
		$diff = str_replace( "\r\n", "\n", $diff );
		if ( false === $this->no_diff_to_big && strlen( $diff ) > 25000000 ) {
			$error = new WP_Error( 'diff-too-big', 'The Diff is too big to parse' );
			if ( true !== $this->nocache ) {
				//wp_cache_set( $cache_key, $error, $cache_group, 3*HOUR_IN_SECONDS );
			}
			return $error;
		}
		if ( true === empty( $diff ) ) {
			return new WP_Error( 'diff_parsing_error', 'Error parsing diff.' );
		}

		$diff_info	  = $this->version_control->parse_diff_for_info( $diff );
		$file_diffs   = $diff_info['file_diffs'];

		$found_issues = array();
		$found_issues_count = 0;
		foreach( $file_diffs as $filename => $file_info ) {
			if ( true === array_key_exists( 'lines_added', $file_info ) && $file_info['lines_added'] > 0 ) {
				$lines_mapping = $this->count_lines( $file_info['lines'] );
				if ( false === $lines_mapping ) {
					continue;
				}
				if ( true === array_key_exists( 'is_new_file', $file_info ) && true === $file_info['is_new_file'] ) {
					$is_new_file = true;
				} else {
					$is_new_file = false;
				}
				$processed_file = $this->process_file( $repo . '/' . $filename, $oldest_rev, $newest_rev, $is_new_file );
				if ( false === $processed_file || true === empty( $processed_file ) ) {
					continue;
				}
				$found_issues[$filename] = $processed_file;
				$found_issues_count += count( $processed_file );
			}
		}

		if ( true !== $this->nocache ) {
			//wp_cache_set( $cache_key, $found_issues, $cache_group, 3*HOUR_IN_SECONDS );
		}

		return $found_issues;

	}

	private function process_file( $filename, $oldest_rev, $newest_rev, $is_new_file ) {

		$file_extension = pathinfo( $filename, PATHINFO_EXTENSION );

		if ( false === in_array( $file_extension, $this->allowed_extensions, true ) ) {
			return false;
		}

		foreach( $this->excluded_extensions as $excluded_ext ) {
			if ( true === wp_endswith( $filename, $excluded_ext ) ) {
				return false;
			}
		}

		$results_for_newest_rev = $this->run_phpcs_for_file_revision( $filename, $newest_rev );
		if ( true === empty( $results_for_newest_rev ) ) {
			return false;
		}

		if ( true === $is_new_file ) {
			return $this->parse_phpcs_results( $results_for_newest_rev );
		}

		$results_for_oldest_rev = $this->run_phpcs_for_file_revision( $filename, $oldest_rev );
		if ( true === empty( $results_for_oldest_rev ) ) {
			return $this->parse_phpcs_results( $results_for_newest_rev );
		}

		return $this->diff_results_for_two_revs( $results_for_newest_rev, $results_for_oldest_rev );
	}

	// @todo: figure out how to prevent wrong file extension error - it's not that urgent since it is present in both diffs, but still.
	private function run_phpcs_for_file_revision( $filename, $revision ) {
		$cache_key	 = 'phpcs_file_rev_' . md5( $filename . $revision . $this->phpcs_standard );
		$cache_group = 'vip-phpcs';
		if ( true !== $this->nocache ) {
			$result	 = false; //wp_cache_get( $cache_key, $cache_group );
		} else {
			$result  = false;
		}

		if ( false === $result ) {

			$result = $this->version_control->run_phpcs_for_file_at_revision( $filename, $revision, $this->phpcs_command, $this->standards_location, $this->phpcs_standard );

			if ( true !== $this->nocache ) {
				//wp_cache_set( $cache_key, $result, $cache_group, 6*HOUR_IN_SECONDS );
			}
		}
		return $result;
	}

	private function parse_phpcs_results( $phpcs_results ) {
		$issues = array();
		if ( preg_match_all( '/^[\s\t]+(\d+)\s\|[\s\t]+([A-Z]+)[\s|\t]+\|[\s\t]+(.*)$/m', $phpcs_results, $matches, PREG_SET_ORDER ) ) {
			foreach( $matches as $match ) {
				$line = $match[1];
				$issues[$line][] = array(
					'level' => $match[2],
					'message' => $match[3],
				);
			}
		}
		return $issues;
	}

	private function diff_results_for_two_revs( $new_rev_results, $old_rev_results ) {

		$new_rev_results = $this->parse_phpcs_results( $new_rev_results );
		$old_rev_results = $this->parse_phpcs_results( $old_rev_results );

		$lines_mapping = array_reverse( $this->lines_mapping, true );
		foreach( $old_rev_results as $line_no => $line ) {
			$lines_offset = 0;
			foreach( $lines_mapping as $old_line_no => $new_line_no ) {
				if ( $line_no >= $old_line_no ) {
					if ( $old_line_no < $new_line_no ) {
						$lines_offset += ( $new_line_no - $old_line_no );
						break;
					} else if ( $old_line_no > $new_line_no ) {
						$lines_offset += ( $new_line_no - $old_line_no );
						break;
					} else if ( $old_line_no === $new_line_no ) {
						$lines_offset = 0;
						break;
					}
				}
			}

			foreach( $line as $old_issue ) {
				$new_line_no = $line_no + $lines_offset;
				if ( true === array_key_exists( $new_line_no, $new_rev_results ) ) {
					foreach( $new_rev_results[$new_line_no] as $new_issue ) {
						if( $new_issue === $old_issue ) {
							unset( $new_rev_results[$new_line_no] );
						}
					}
				}
			}

		}

		return $new_rev_results;
	}

	private function count_lines( $lines ) {
		$lines_added = $lines_removed = $lines_mapping = array();
		foreach( $lines as $line ) {
			if ( true === $line['is_added'] ) {
				$lines_added[] = intval( $line['new_line_number'] );
			} else if ( true === $line['is_removed'] ) {
				$lines_removed[] = intval( $line['old_line_number'] );
			} else if ( true === $line['is_context'] ) {
				$lines_mapping[ intval( $line['old_line_number'] ) ] = intval( $line['new_line_number'] );
			}
		}
		if ( true === empty( $lines_added ) ) {
			return false;
		}
		$this->lines_mapping = $lines_mapping;
		return true;
	}

	private function stop_the_insanity() {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );
		if ( !is_object( $wp_object_cache ) )
			return;
		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();
		$wp_object_cache->__remoteset(); // important
	}

}
