<?php
	/**
	 * A cached translation override.
	 */

	class Mo_OPcache extends Mo {
		public function import_from_file( $mofile ) {
			if ( ! function_exists( 'opcache_compile_file' ) ) {
				return parent::import_from_file( $mofile );
			}

			$mocache = sprintf( '/tmp/%s.mocache', md5( $mofile ) );
			if ( file_exists( $mocache ) ) {
				include $mocache; /** OPcache, come forth! */
				$this->_nplurals = &$mo->_nplurals;
				$this->entries = &$mo->entries;
				$this->headers = &$mo->headers;
				return true;
			}

			if ( ! parent::import_from_file( $mofile ) )
				return false;

			/** Hope OPcache picks this up. */
			$state = str_replace( 'Translation_Entry::__set_state', 'new Translation_Entry', var_export( $this, true ) );
			file_put_contents( $mocache, sprintf( '<?php $mo = %s;', $state ), LOCK_EX );

			return true;
		}

		public static function __set_state( $state ) {
			return new self( $state );
		}
	}

	add_filter( 'override_load_textdomain', function( $plugin_override, $domain, $mofile ) {
		if ( !is_readable( $mofile ) )
			return false;

		global $l10n;

		$mo = new Mo_OPcache();

		if ( !$mo->import_from_file( $mofile ) ) return false;

		if ( isset( $l10n[$domain] ) )
			$mo->merge_with( $l10n[$domain] );

		$l10n[$domain] = &$mo;

		return true;
	}, 10, 3 );
