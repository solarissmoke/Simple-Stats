<?php
define( 'SIMPLE_STATS_PATH', realpath( dirname( __FILE__ ) ) );
include_once( SIMPLE_STATS_PATH.'/config.php' );
include_once( SIMPLE_STATS_PATH.'/includes/classes.php' );
include_once( SIMPLE_STATS_PATH.'/includes/ua.php' );

class SimpleStatsHit {
	function __construct() {
		$ss = new SimpleStats();
		
		if ( !$ss->is_installed() || !$ss->options['stats_enabled'] || 
			( isset( $_COOKIE['simple_stats'] ) && $_COOKIE['simple_stats'] == $ss->hash( $ss->options['username'] . $ss->options['password'] ) ) )
			return;
		
		$data = array();
		$data['remote_ip'] = substr( $this->determine_remote_ip(), 0, 39 );
		// check whether to ignore this hit
		foreach ( $ss->options['ignored_ips'] as $ip ) {
			if ( strpos( $data['remote_ip'], $ip ) === 0 )
				return;
		}

		$data['resource'] = substr( $ss->utf8_encode( $this->determine_resource() ), 0, 255 );
		
		$ua = new SimpleStatsUA();
		$browser = $ua->parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );
		$data['platform'] = $browser['platform'];
		$data['browser']  = $browser['browser'];
		$data['version']  = substr( $this->parse_version( $browser['version'] ), 0, 15 );
		
		// check whether to ignore this hit
		if ( $data['browser'] == 1 && $ss->options['log_bots'] == false )
			return;
		
		// use DateTime instead of messing with the default timezone which could affect the calling application
		$tz = new DateTimeZone( $ss->options['tz'] );
		$datetime = new DateTime( 'now', $tz );
		$date = $data['date'] = $datetime->format( 'Y-m-d' );
		$time = $datetime->format( 'H:i:s' );
		
		// attempt to update table
		$table = $ss->tables['visits'];
		
		if ( $ss->options['log_user_agents'] )
			$data['user_agent'] = $ss->esc( substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) );
		
		$resource = $ss->esc( $time . ' ' .$data['resource'] );
		$ip = $ss->esc( $data['remote_ip'] );
		
		$query = "UPDATE `$table` SET hits = hits + 1, resource = CONCAT( resource, '$resource', '\\n' ), `end_time` = '$time' WHERE `date` = '$date' AND remote_ip = '$ip'";
		if ( $ss->options['log_user_agents'] ) {
			$query .= " AND user_agent = '{$data['user_agent']}'";
		} else {
			foreach ( array( 'browser', 'version', 'platform' ) as $key ) {
				$v = $ss->esc( $data[$key] );
				$query .= " AND $key = '$v'";
			}
		}
	
		$query .= " AND TIMEDIFF( '$time', start_time ) < '00:30:00' LIMIT 1";
		
		$rows = $ss->query( $query );

		if ( $rows == 0 ) {
			// this information is only needed for new visitors
			$data['country']  = $this->determine_country( $data['remote_ip'] ); // always 2 chars, no need to truncate
			$data['language'] = substr( $this->determine_language(), 0, 255 );
			$data['referrer'] = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
			$url = parse_url( $data['referrer'] );
			$data['referrer'] = substr( $ss->utf8_encode( $data['referrer'] ), 0, 511 );
			$data['domain']   = isset( $url['host'] ) ? substr( preg_replace( '/^www\./', '', $url['host'] ), 0, 255 ) : '';
			$data['search_terms'] = substr( $ss->utf8_encode( $this->determine_search_terms( $url ) ), 0, 255 );
			// this isn't actually used at present, but storing local timestamps without a GMT reference is asking for trouble
			$data['offset'] = $datetime->getOffset() / 60;	// store in minutes

			$query = "INSERT INTO `$table` ( ";
			foreach ( array_keys( $data ) as $key ) {
				if ( $key == 'resource' ) 
					continue;
				$query .= "$key, ";
			}

			$query .= 'hits, resource, start_time, end_time ) VALUES ( ';
			foreach ( $data as $key => $value ) {
				$value = $ss->esc( $value );
				if ( $key == 'resource' )
					continue;
				$query .= "'$value', ";
			}
			$query .= "'1', CONCAT( '$resource', '\\n' ), '$time', '$time' )";

			$ss->query( $query);
		}
		
		$ss->close();
		
	}
	
	/**
	 * Try to work out the original client IP address.
	 */
	private function determine_remote_ip() {
		// headers to look for, in order of priority
		$headers_to_check = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST' );

		foreach( $headers_to_check as $header ) {
			if( empty( $_SERVER[$header] ) )
				continue;

			$ips = explode( ',', $_SERVER[$header] );
			foreach( $ips as $ip ) {
				$ip = trim( $ip );
				if( $ip && ! preg_match( '/^(127\.|10\.|172\.1[0-6]\.|172\.2[0-0]\.|172\.3[0-1]\.|192\.168\.)/', $ip ) )	// we don't want private network IPs
					return $ip;
			}
		}
		
		return $_SERVER['REMOTE_ADDR'];
	}
	
	/**
	 * Try to work out the requested resource.
	 */
	private function determine_resource() {
		if( isset( $_SERVER['REQUEST_URI'] ) )
			return $_SERVER['REQUEST_URI'];
		elseif( isset( $_SERVER['SCRIPT_NAME'] ) )
			return $_SERVER['SCRIPT_NAME'] . ( empty( $_SERVER['QUERY_STRING'] ) ? '' : '?' . $_SERVER['QUERY_STRING'] );
		elseif( isset( $_SERVER['PHP_SELF'] ) )
			return $_SERVER['PHP_SELF'] . ( empty( $_SERVER['QUERY_STRING'] ) ? '' : '?' . $_SERVER['QUERY_STRING'] );
		return '';
	}

	/**
	 * Determines the visitor's country based on their IP address.
	 * You can supply your own GeoIP information (two-letter country code) by
	 * definining a constant SIMPLE_STATS_GEOIP_COUNTRY containing this value.
	 */
	private function determine_country( $_ip ) {
		if( defined( 'SIMPLE_STATS_GEOIP_COUNTRY' ) && strlen( SIMPLE_STATS_GEOIP_COUNTRY ) <= 2 )
			return SIMPLE_STATS_GEOIP_COUNTRY;

		if ( SimpleStats::is_geoip() ) {
			if( ! function_exists( 'geoip_open' ) && ! class_exists( 'GeoIP' ) )		// it's possible the user has another instance running
				include_once( SIMPLE_STATS_PATH.'/geoip/geoip.php' );
			$gi = geoip_open( SIMPLE_STATS_PATH.'/geoip/GeoIP.dat', GEOIP_STANDARD );
			$result = geoip_country_code_by_addr( $gi, $_ip );
			geoip_close( $gi );
			return $result;
		}
		
		return '';
	}

	private function determine_language() {
		// Capture up to the first delimiter (comma found in Safari)
		if ( !empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && preg_match( "/([^,;]*)/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $langs ) )
			return strtolower( $langs[0] );
		return '';
	}
	
	/**
	 * Detects referrals from search engines and tries to determine the search terms.
	 */
	private function determine_search_terms( $_url ) {
		if( empty( $_url['host'] ) || empty( $_url['query'] ) )
			return;

		$sniffs = array( // host string, query portion containing search terms, parameterised url to decode
			array( 'images.google', 'q', 'prev' ),
			array( 'yahoo.', 'p' ),
			array( 'yandex.', 'text' ),
			array( 'rambler.', 'words' ),
			// generic
			array( '.', 'q' ),
			array( '.', 'query' )
		);

		$search_terms = '';

		foreach ( $sniffs as $sniff ) {
			if ( strpos( strtolower( $_url['host'] ), $sniff[0] ) !== false ) {
				parse_str( $_url['query'], $q );

				if ( isset( $sniff[2] ) && isset( $q[$sniff[2]] ) ) {
					$decoded_url = parse_url( $q[ $sniff[2] ] );
					if ( isset( $decoded_url['query'] ) )
						parse_str( $decoded_url['query'], $q );
				}

				if ( isset( $q[ $sniff[1] ] ) ) {
					$search_terms = trim( stripslashes( $q[ $sniff[1] ] ) );
					break;
				}
			}
		}

		return $search_terms;
	}

	private function parse_version( $version, $parts = 2 ) {
		$value = implode( '.', array_slice( explode( '.', $version ), 0, $parts ) );
		// skip trailing zeros - most browsers have rapid release cycles now
		if( substr( $value, -2 ) == '.0' )
			$value = substr_replace( $value, '', -2 );
		return $value;
	}
}

new SimpleStatsHit();
