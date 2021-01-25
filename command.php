<?php

use function WP_CLI\Utils\make_progress_bar;
use WP_Rocket\Engine\Cache\WPCache;

/**
 * Manage Revisions
 */
class WPRocket_CLI extends WP_CLI_Command {
	/**
	 * Backward compatibility method
	 *
	 * @return void
	 */
	public function activate() {
		$this->activate_cache();
	}

	/**
	 * Set WP_CACHE constant in wp-config.php to true and update htaccess
	 *
	 * ## OPTIONS
	 *
	 * [--htaccess=<bool>]
	 * : Enable update of the htaccess file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rocket activate-cache
	 *
	 * @subcommand activate-cache
	 */
	public function activate_cache( array $args = [], array $assoc_args = [] ) {
		if ( ! is_plugin_active( 'wp-rocket/wp-rocket.php') ) {
			WP_CLI::error( 'WP Rocket is not enabled.' );
		}

		if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
			$wp_cache = new WPCache( rocket_direct_filesystem() );

			if ( $wp_cache->set_wp_cache_constant( true ) ) {
				if ( rocket_valid_key() ) {
					// Add All WP Rocket rules of the .htaccess file.
					if ( isset( $assoc_args['htaccess'] ) && $assoc_args['htaccess'] ) {
						self::set_apache();

						if ( ! flush_rocket_htaccess() ) {
							WP_CLI::warning( 'Adding WP Rocket rules to the htaccess file failed.');
						}
					}
				}
				// Clean WP Rocket Cache and Minified files.
				$this->clean_wp_rocket_cache( true );

				WP_CLI::success( 'WP Rocket is now enabled, WP_CACHE is set to true.' );
			} else {
				WP_CLI::error( 'Error while setting WP_CACHE constant into wp-config.php!' );
			}
		} else {
			WP_CLI::error( 'WP Rocket is already enabled.' );
		}
	}

	/**
	 * Backward compatibility method
	 *
	 * @return void
	 */
	public function deactivate() {
		$this->deactivate_cache();
	}

	/**
	 * Set WP_CACHE constant in wp-config.php to false and update htaccess
	 *
	 * ## OPTIONS
	 *
	 * [--htaccess=<bool>]
	 * : Enable update of the htaccess file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rocket deactivate-cache
	 *
	 * @subcommand deactivate-cache
	 */
	public function deactivate_cache( array $args = [], array $assoc_args = [] ) {
		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			$wp_cache = new WPCache( rocket_direct_filesystem() );

			if ( $wp_cache->set_wp_cache_constant( false ) ) {
				if ( rocket_valid_key() ) {
					// Remove All WP Rocket rules from the .htaccess file.
					if ( isset( $assoc_args['htaccess'] ) && $assoc_args['htaccess'] ) {
						self::set_apache();

						if ( ! flush_rocket_htaccess( true ) ) {
							WP_CLI::warning( 'Removing WP Rocket rules from the htaccess file failed.');
						}
					}
				}
				// Clean WP Rocket Cache and Minified files.
				$this->clean_wp_rocket_cache( true );

				WP_CLI::success( 'WP Rocket is now disabled, WP_CACHE is set to false.' );
			} else {
				WP_CLI::error( 'Error while setting WP_CACHE constant into wp-config.php!' );
			}
		} else {
			WP_CLI::error( 'WP Rocket is already disabled.' );
		}
	}

	/**
	 * Purge cache files
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<post_id>]
	 * : List posts to purge cache files.
	 *
	 * [--permalink=<permalink>]
	 * : List permalinks to purge cache files. Trumps --post_id.
	 *
	 * [--lang=<lang>]
	 * : List langs to purge cache files. Trumps --post_id & --permalink.
	 *
	 * [--blog_id=<blog_id>]
	 * : List blogs to purge cache files. Trumps --post_id & --permalink & lang.
	 *
	 * [--confirm]
	 * : Automatic 'yes' to any confirmation
	 *
	 * ## EXAMPLES
	 *
	 *     wp rocket clean
	 *     wp rocket clean --post_id=2
	 *     wp rocket clean --post_id=2,4,6,8
	 *     wp rocket clean --permalink=http://example.com
	 *     wp rocket clean --permalink=http://example.com, http://example.com/category/(.*)
	 *	   wp rocket clean --lang=fr
	 *     wp rocket clean --lang=fr,de,en,it
	 *	   wp rocket clean --blog_id=2
	 *     wp rocket clean --blog_id=2,4,6,8
	 *
	 * @subcommand clean
	 */
	public function clean( array $args = [], array $assoc_args = [] ) {
		if ( ! function_exists( 'rocket_clean_domain' ) ) {
			WP_CLI::error( ' The plugin WP-Rocket seems not enabled on this site.' );
		}

		if( ! empty( $assoc_args['blog_id'] ) ) {
			if ( ! defined( 'MULTISITE' ) || ! MULTISITE ) {
				WP_CLI::error( 'This installation doesn\'t support multisite.' );
			}

			$blog_ids = explode( ',' , $assoc_args['blog_id'] );
			$total    = 0;

			$notify = \WP_CLI\Utils\make_progress_bar( 'Delete cache files', count( $blog_ids ) );

			foreach ( $blog_ids as $blog_id ) {
				$blog_id = trim( $blog_id );

				if ( $bloginfo = get_blog_details( (int) $blog_id, false ) ) {

					switch_to_blog( $blog_id );

					rocket_clean_domain();
					WP_CLI::line( 'Cache cleared for "' . esc_url( 'http://' . $bloginfo->domain . $bloginfo->path ) . '".' );

					restore_current_blog();

					$total++;
				} else {
					WP_CLI::line( 'This blog ID "' . $blog_id . '" doesn\'t exist.' );
				}

				$notify->tick();

			}

			$notify->finish();
			WP_CLI::success( 'Cache cleared for ' . $total . ' blog(s).' );

		} else if( ! empty( $assoc_args['lang'] ) ) {
			if( ! rocket_has_i18n() ) {
				WP_CLI::error( 'No WPML, Polylang or qTranslate in this website.' );
			}

			$langs = explode( ',' , $assoc_args['lang'] );
			$langs = array_map( 'trim' , $langs );
			$total = count( $langs );

			$notify = \WP_CLI\Utils\make_progress_bar( 'Delete cache files', $total );

			foreach ( $langs as $lang ) {

				rocket_clean_domain( $lang );
				$notify->tick();

			}

			$notify->finish();
			WP_CLI::success( 'Cache files cleared for ' . $total . ' lang(s).' );

		} else if( ! empty( $assoc_args['permalink'] ) ) {
			$permalinks = explode( ',' , $assoc_args['permalink'] );
			$total      = count( $permalinks );

			$notify = make_progress_bar( 'Delete cache files', $total );

			foreach ( $permalinks as $permalink ) {
				$permalink = trim( $permalink );

				rocket_clean_files( $permalink );
				WP_CLI::line( 'Cache cleared for "' . $permalink . '".' );

				$notify->tick();
			}

			$notify->finish();
			WP_CLI::success( 'Cache files cleared for ' . $total . ' permalink(s).' );

		} else if( ! empty( $assoc_args['post_id'] ) ) {
			$total    = 0;
			$post_ids = explode( ',' , $assoc_args['post_id'] );

			$notify = make_progress_bar( 'Delete cache files', count( $post_ids ) );

			foreach ( $post_ids as $post_id ) {
				$post_id = trim( $post_id );

				if( rocket_clean_post( $post_id ) ) {
					WP_CLI::line( 'Cache cleared for post ID "' . $post_id . '".' );
					$total++;
				} else {
					WP_CLI::line( 'This post ID "' . $post_id . '" is not a valid public post.' );
				}

				$notify->tick();

			}

			if( $total ) {
				$notify->finish();

				if( $total == 1 ) {
					WP_CLI::success( '1 post is cleared.' );
				} else {
					WP_CLI::success( $total . ' posts are cleared.' );
				}

			} else {
				WP_CLI::error( 'No cache files cleared.' );
			}
		} else {
			if ( ! empty( $assoc_args['confirm'] ) && $assoc_args['confirm'] ) {
				WP_CLI::line( 'Deleting all cache files.' );
			} else {
				WP_CLI::confirm( 'Delete all cache files ?' );
			}

			if ( rocket_clean_domain() ) {
				WP_CLI::success( 'All cache files cleared.' );
			}else{
				WP_CLI::error( 'No cache files are cleared.' );
			}
		}

	}

	/**
	 * Run WP Rocket Bot for preload cache files
	 *
	 * ## OPTIONS
	 *
	 * [--sitemap]
	 * : Trigger sitemap-based preloading
	 *
	 * ## EXAMPLES
	 *
	 *     wp rocket preload
	 *     wp rocket preload --sitemap
	 *
	 * @subcommand preload
	 */
	public function preload( array $args = [], array $assoc_args = [] ) {

		if ( ! empty( $assoc_args['sitemap'] ) && $assoc_args['sitemap'] ) {
			WP_CLI::line( 'Triggering sitemap-based preloading.' );
			run_rocket_sitemap_preload();
		} else {
			if ( run_rocket_bot() ) {
				WP_CLI::success( 'Triggering homepage-based preloading.' );
			}else{
				WP_CLI::error( 'Cannot start preload cache, please check preload option is activated.' );
			}
		}

		WP_CLI::success( 'Finished WP Rocket preload cache files.' );
	}

	/**
	 * Regenerate file
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : The file to regenerate. It could be:
	 *	- htaccess
	 *	- advanced-cache
	 *	- config (It's the config file stored in the wp-rocket-config folder)
	 *
	 * [--nginx=<bool>]
	 * : The command should run as if on nginx (setting the $is_nginx global to true)
	 *
	 * ## EXAMPLES
	 *
	 *	   wp rocket regenerate --file=htaccess
	 *     wp rocket regenerate --file=config --nginx=true
	 *
	 * @subcommand regenerate
	 */
	public function regenerate( array $args = [], array $assoc_args = [] ) {
		if( !empty( $assoc_args['file'] ) ) {
			switch( $assoc_args['file'] ) {
				case 'advanced-cache':
					if ( rocket_generate_advanced_cache_file() ) {
						WP_CLI::success( 'The advanced-cache.php file has just been regenerated.' );
					}else{
						WP_CLI::error( 'Cannot generate advanced-cache.php file, please check folder permissions.' );
					}
					break;
				case 'config':
					if ( ! empty( $assoc_args['nginx'] ) && $assoc_args = true ) {
						$GLOBALS['is_nginx'] = true;
					}

					rocket_generate_config_file();
					WP_CLI::success( 'The config file has just been regenerated.' );
					break;
				case 'htaccess':
					$GLOBALS['is_apache'] = true;
					if ( flush_rocket_htaccess() ) {
						WP_CLI::success( 'The .htaccess file has just been regenerated.' );
					}else{
						WP_CLI::error( 'Cannot generate .htaccess file.' );
					}
					break;
				default:
					WP_CLI::error( 'You did not specify a good value for the "file" argument. It should be: advanced-cache, config or htaccess.' );
					break;
			}

		} else {
			WP_CLI::error( 'You did not specify the "file" argument.' );
		}
	}

	/**
	 * Enable / Disable CDN option and set the CDN URL.
	 *
	 * ## OPTIONS
	 *
	 * [--enable=<enable>]
	 * : Option to enable / disable = boolean.
	 *
	 * [--host=<host>]
	 * : CDN host.
	 *
	 * [--zone=<zone>]
	 * : CDN zone -> [ all, images, css_and_js, js, css ]
	 *
	 * ## EXAMPLES
	 *
	 *     wp rocket cdn --enable=false
	 *     wp rocket cdn --enable=true --host=http://cdn.example.com
	 *     wp rocket cdn --enable=true --host=http://cdn.example.com --zone=all
	 *
	 * @subcommand cdn
	 */
	public function cdn( $args = array(), $assoc_args = array() ) {
		if ( empty( $assoc_args['enable'] ) ) {
			WP_CLI::error( 'The "enable" argument must be specified.' );
			return;
		}

		switch( $assoc_args['enable'] ) {
			case 'true':
				update_rocket_option( 'cdn', true );

				if ( ! empty( $assoc_args['host'] ) ) {
					$cdn_cnames = get_rocket_option( 'cdn_cnames', true );
					$cdn_zones  = get_rocket_option( 'cdn_zone', true );
					$cname      = $assoc_args['host'];
					$zone       = ( ! empty( $assoc_args['zone'] ) ? $assoc_args['zone'] : 'all' );
					$exists     = array_search( $cname, $cdn_cnames );
					if ( false === $exists ) {
						// CNAME does not exist. Set it the last element.
						$exists = count( $cdn_cnames );
					}

					$cdn_cnames[ $exists ] = $cname;
					$cdn_zones[ $exists ]  = $zone;

					update_rocket_option( 'cdn_cnames', $cdn_cnames );
					update_rocket_option( 'cdn_zone', $cdn_zones );
					$this->clean_wp_rocket_cache( true );

					WP_CLI::success( 'CDN enabled successfully with CNAME=<' . $cname .'> and zone=<' . $zone . '>' );
					break;
				}

				$this->clean_wp_rocket_cache( true );
				WP_CLI::success( 'CDN enabled successfully without CNAME' );
				break;
			case 'false':
				update_rocket_option( 'cdn', false );
				$this->clean_wp_rocket_cache( true );
				WP_CLI::success( 'CDN disabled successfully!' );
				break;
			default:
			WP_CLI::error( 'The "enable" argument must contain either true or false value.' );
				break;
		}
	}

	/**
	 * Clean WP Rocket domain and additional cache files.
	 *
	 * @param boolean $minify Clean also minify cache files.
	 * @return void
	 */
	private function clean_wp_rocket_cache( $minify = false ) {
		rocket_clean_domain();

		if ( $minify ) {
			// Remove all minify cache files.
			rocket_clean_minify();
			// Generate a new random key for minify cache file.
			update_rocket_option( 'minify_css_key', create_rocket_uniqid() );
			update_rocket_option( 'minify_js_key', create_rocket_uniqid() );
		}
	}

	/**
	 * Set global Apache variable to true
	 *
	 * @return void
	 */
	private static function set_apache() {
		global $is_apache;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$is_apache = true;

		// needed for get_home_path() and .htaccess location
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH;
	}
}

WP_CLI::add_command( 'rocket', 'WPRocket_CLI' );
