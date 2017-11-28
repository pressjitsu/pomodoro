<?php
	/**
	 * A cached translation override.
	 */
	add_filter( 'override_load_textdomain', function( $plugin_override, $domain, $mofile ) {
		if ( ! is_readable( $mofile ) )
			return false;

		global $l10n;

		$l10n[ $domain ] = new class( $mofile, $domain ) {
			private $domain = null;
			private $_cache = array();
			private $busted = false;
			private $upstream = null;
			private $mofile = null;

			public function __construct( $mofile, $domain ) {
				$this->domain = $domain;
				$this->mofile = $mofile;

				$cache_file = sprintf( '/tmp/%s.mocache', md5( serialize( func_get_args() ) ) );

				if ( file_exists( $cache_file ) ) {
					include $cache_file;
					$this->_cache = &$_cache;
				}

				register_shutdown_function( function() use ( $cache_file ) {
					/** Dump all known strings to file and have opcache pick it up. */
					if ( $this->busted ) {
						file_put_contents( $cache_file, sprintf( '<?php $_cache = %s;', var_export( $this->_cache, true ) ), LOCK_EX );
					}
				} );
			}

			public function translate( $text, $context = null ) {
				$cache_key = $this->cache_key( func_get_args() );

				if ( isset( $this->_cache[ $cache_key ] ) )
					return $this->_cache[ $cache_key ];

				$this->busted = true;

				if ( ! $this->upstream ) {
					$this->upstream = new Mo();
					$this->upstream->import_from_file( $this->mofile );
				}

				return $this->_cache[ $cache_key ] = $this->upstream->translate( $text, $context );
			}

			public function translate_plural( $singular, $plural, $count, $context = null ) {
				$cache_key = $this->cache_key( func_get_args() );

				if ( isset( $this->_cache[ $cache_key ] ) )
					return $this->_cache[ $cache_key ];

				$this->busted = true;

				if ( ! $this->upstream ) {
					$this->upstream = new Mo();
					$this->upstream->import_from_file( $this->mofile );
				}


				return $this->_cache[ $cache_key ] = $translation = $this->upstream->translate_plural( $singular, $plural, $count, $context );
			}

			private function cache_key( $args ) {
				return md5( serialize( array( $args, $this->domain ) ) );
			}
		};

		return true;
	}, 10, 3 );
