<?php
	/**
	 * A cached translation override.
	 */

	class Mo_Redis extends Mo {
		private static $redis;

		public function connect() {
			if ( self::$redis )
				return;

			$redis = new Redis;
			$redis->connect( '127.0.0.1' );
			$redis->setOption( Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE );

			self::$redis = &$redis;
		}

		public function import_from_file( $mofile ) {
			if ( $mo = json_decode( self::$redis->get( $mofile ), true ) ) {
				$this->_nplurals = $mo['_nplurals'];
				/** Sigh, yes, each translation is an object in WordPress... */
				array_walk( $mo['entries'], function( &$v ) { $v = new Translation_Entry( $v ); } );
				$this->entries = $mo['entries'];
				$this->headers = $mo['headers'];
				return true;
			}

			if ( !parent::import_from_file( $mofile ) )
				return false;

			// Add to cache
			self::$redis->set( $mofile, json_encode( array(
				'_nplurals' => $this->_nplurals,
				'entries' => $this->entries,
				'headers' => $this->headers
			) ) );

			return true;
		}
	}

	add_filter( 'override_load_textdomain', function( $plugin_override, $domain, $mofile ) {
		if ( !is_readable( $mofile ) )
			return false;

		global $l10n;

		$mo = new Mo_Redis();
		$mo->connect();

		if ( !$mo->import_from_file( $mofile ) ) return false;

		if ( isset( $l10n[$domain] ) )
			$mo->merge_with( $l10n[$domain] );

		$l10n[$domain] = &$mo;

		return true;
	}, 10, 3 );
