<?php
/**
 * Plugin Name: POMOdoro Translation Cache
 * Description: A cached translation override for WordPress.
 * Plugin URI: https://github.com/pressjitsu/pomodoro/
 *
 * Bakes and stows away expensive translation lookups
 *  as PHP hashtables. Fast and beautiful.
 *
 * GPL3
 * Pressjitsu, Inc.
 * https://pressjitsu.com
 */
namespace Pressjitsu\Pomodoro;

add_filter( 'override_load_textdomain', function( $plugin_override, $domain, $mofile ) {
	if ( ! is_readable( $mofile ) )
		return false;

	global $l10n;

	$mo = new MoCache_Translation( $mofile, $domain, $upstream = empty( $l10n[ $domain ] ) ? null : $l10n[ $domain ] );
	$l10n[ $domain ] = $mo;

	return true;
}, 999, 3 );

class MoCache_Translation {
	/**
	 * Private state.
	 */
	private $domain = null;
	private $cache = array();
	private $busted = false;
	private $override = null;
	private $upstream = null;
	private $mofile = null;

	/**
	 * Cache file end marker.
	 */
	private $end = 'POMODORO_END_e867edfb-4a36-4643-8ad4-b95507068e44';

	/**
	 * Construct the main translation cache instance for a domain.
	 *
	 * @param string $mofile The path to the mo file.
	 * @param string $domain The textdomain.
	 * @param Translations $merge The class in the same domain, we have overriden it.
	 */
	public function __construct( $mofile, $domain, $override ) {
		$this->mofile = apply_filters( 'load_textdomain_mofile', $mofile, $domain );
		$this->domain = $domain;
		$this->override = $override;
		$temp_dir = get_temp_dir();

		$filename = md5( serialize( array( get_home_url(), $this->domain, $this->mofile ) ) );
		if ( defined( 'POMODORO_CACHE_DIR' ) && POMODORO_CACHE_DIR && wp_mkdir_p( POMODORO_CACHE_DIR ) ) {
			$temp_dir = POMODORO_CACHE_DIR;
		}
		$cache_file = sprintf( '%s/%s.mocache', untrailingslashit( $temp_dir ), $filename );

		$mtime = filemtime( $this->mofile );

		if ( $file_exists = file_exists( $cache_file ) ) {
			/**
			 * Load cache.
			 *
			 * OPcache will grab the values from memory.
			 */
			include $cache_file;
			$this->cache = &$_cache;

			/**
			 * Mofile has been modified, invalidate it all.
			 */
			if ( ! isset( $_mtime ) || ( isset( $_mtime ) && $_mtime < $mtime ) ) {
				$this->cache = array();
			}
		}

		$_this = &$this;

		register_shutdown_function( function() use ( $cache_file, $_this, $mtime, $domain, $file_exists ) {
			/**
			 * New values have been found. Dump everything into a valid PHP script.
			 */
			if ( $_this->busted || ( empty( $_this->cache ) && ! $file_exists ) ) {
				file_put_contents( "$cache_file.test", sprintf( '<?php $_mtime = %d; $_domain = %s; $_cache = %s; // %s', $mtime, var_export( $domain, true ), var_export( $_this->cache, true ), $this->end ), LOCK_EX );

				// Test the file before committing.
				$fp = fopen( "$cache_file.test", 'rb' );

				fseek( $fp, -strlen( $_this->end ), SEEK_END );
				if ( fgets( $fp ) == $_this->end ) {
					rename( "$cache_file.test", $cache_file );
				} else {
					trigger_error( "pomodoro $cache_file.test cache file missing end marker." );
					unlink( "$cache_file.test" );
				}

				fclose( $fp );
			}
		} );
	}

	private function get_translation( $cache_key, $text, $args ) {
		/**
		 * Check cache first.
		 */
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		/**
		 * Bust it.
		 */
		$this->busted = true;

		$translate_function = count( $args ) > 2 ? 'translate_plural' : 'translate';

		/**
		 * Merge overrides.
		 */
		if ( $this->override ) {
			return $this->cache[ $cache_key ] = call_user_func_array( array( $this->override, $translate_function ), $args );
		}

		/**
		 * Default Mo upstream.
		 */
		if ( ! $this->upstream ) {
			$this->upstream = new \Mo();
			do_action( 'load_textdomain', $this->domain, $this->mofile );
			$this->upstream->import_from_file( $this->mofile );
		}

		return $this->cache[ $cache_key ] = call_user_func_array( array( $this->upstream, $translate_function ), $args );
	}

	/**
	 * The translate() function implementation that WordPress calls.
	 */
	public function translate( $text, $context = null ) {
		return $this->get_translation( $this->cache_key( func_get_args() ), $text, func_get_args() );
	}

	/**
	 * The translate_plural() function implementation that WordPress calls.
	 */
	public function translate_plural( $singular, $plural, $count, $context = null ) {
		$text = ( abs( $count ) == 1 ) ? $singular : $plural;
		return $this->get_translation( $this->cache_key( array( $text, $count, $context ) ), $text, func_get_args() );
	}

	/**
	 * Cache key calculator.
	 */
	private function cache_key( $args ) {
		return md5( serialize( array( $args, $this->domain ) ) );
	}
}
