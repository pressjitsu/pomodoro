<?php
/**
 * A cached translation override for WordPress.
 *
 * Bakes and stows away expensive translation lookups
 *  as PHP hashtables. Fast and beautiful.
 *
 * GPL3
 * Pressjitsu, Inc.
 * https://pressjitsu.com
 */

add_filter( 'override_load_textdomain', function( $plugin_override, $domain, $mofile ) {
	if ( ! is_readable( $mofile ) )
		return false;

	global $l10n;

	/**
	 * Override the domain handler.
	 */
	$l10n[ $domain ] = new MoCache_Translation( $mofile, $domain );
	return true;
}, 10, 3 );

class MoCache_Translation {
	/**
	 * Private state.
	 */
	private $domain = null;
	private $cache = array();
	private $busted = false;
	private $upstream = null;
	private $mofile = null;

	public function __construct( $mofile, $domain ) {
		$this->domain = $domain;
		$this->mofile = $mofile;

		/**
		 * Cache file.
		 */
		$cache_file = sprintf( '%s/%s.mocache', untrailingslashit( sys_get_temp_dir() ), md5( serialize( func_get_args() ) ) );

		if ( file_exists( $cache_file ) ) {
			/**
			 * Load cache.
			 *
			 * OPcache will grab the values from memory.
			 */
			include $cache_file;
			$this->cache = &$_cache;
		}

		$_this = &$this;

		register_shutdown_function( function() use ( $cache_file, $_this ) {
			/**
			 * New values have been found. Dump everything into a valid PHP script.
			 */
			if ( $this->busted ) {
				file_put_contents( $cache_file, sprintf( '<?php $_cache = %s;', var_export( $_this->cache, true ) ), LOCK_EX );
			}
		} );
	}

	private function get_translation( $cache_key ) {
		/**
		 * Check cache first.
		 */
		if ( isset( $this->cache[ $cache_key ] ) )
			return $this->cache[ $cache_key ];

		/**
		 * Invalidate cache for domain.
		 */
		$this->busted = true;

		/**
		 * Load and setup a proxy Mo reader.
		 */
		if ( ! $this->upstream ) {
			$this->upstream = new Mo();
			$this->upstream->import_from_file( $this->mofile );
		}
	}

	/**
	 * The translate() function implementation that WordPress calls.
	 */
	public function translate( $text, $context = null ) {
		if ( is_null( $translation = $this->get_translation( $cache_key = $this->cache_key( func_get_args() ) ) ) ) {
			return $this->cache[ $cache_key ] = $this->upstream->translate( $text, $context );
		}
		return $translation;
	}

	/**
	 * The translate_plural() function implementation that WordPress calls.
	 */
	public function translate_plural( $singular, $plural, $count, $context = null ) {
		if ( is_null( $translation = $this->get_translation( $cache_key = $this->cache_key( func_get_args() ) ) ) ) {
			return $this->cache[ $cache_key ] = $this->upstream->translate_plural( $singular, $plural, $count, $context );
		}
		return $translation;
	}

	/**
	 * Cache key calculator.
	 */
	private function cache_key( $args ) {
		return md5( serialize( array( $args, $this->domain ) ) );
	}
}

