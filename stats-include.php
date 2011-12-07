<?php
define( 'SIMPLE_STATS_PATH', realpath( dirname( __FILE__ ) ) );
include_once( SIMPLE_STATS_PATH.'/config.php' );
include_once( SIMPLE_STATS_PATH.'/includes/classes.php' );

class SimpleStatsHit {
	function __construct() {
		$ss = new SimpleStats();
		
		if ( !$ss->is_installed() || !$ss->options['stats_enabled'] || 
			( isset( $_COOKIE['simple_stats'] ) && $_COOKIE['simple_stats'] == $ss->hash( $ss->options['username'] . $ss->options['password'] ) ) )
			return;
		
		$data = array();
		$data['remote_ip'] = mb_substr( $this->_determine_remote_ip(), 0, 15 );
		// check whether to ignore this hit
		foreach ( $ss->options['ignored_ips'] as $ip ) {
			if ( mb_strpos( $data['remote_ip'], $ip ) === 0 )
				return;
		}
		
		$data['referrer'] = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
		$url = parse_url( $data['referrer'] );
		$data['referrer'] = mb_substr( $ss->utf8_encode( $data['referrer'] ), 0, 255 );
		
		$data['country']  = $this->_determine_country( $data['remote_ip'] ); // always 2 chars, no need to truncate
		$data['language'] = mb_substr( SimpleStats::determine_language(), 0, 255 );
		$data['domain']   = isset( $url['host'] ) ? mb_eregi_replace( '^www.', '', $url['host'] ) : '';
		$data['domain']   = mb_substr( $data['domain'], 0, 255 );
		
		$data['search_terms'] = mb_substr( $ss->utf8_encode( $this->_determine_search_terms( $url ) ), 0, 255 );

		if ( isset( $_SERVER['REQUEST_URI'] ) )
			$data['resource'] = $_SERVER['REQUEST_URI'];
		elseif ( isset( $_SERVER['SCRIPT_NAME'] ) && isset( $_SERVER['QUERY_STRING'] ) )
			$data['resource'] = $_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING'];
		elseif ( isset( $_SERVER['SCRIPT_NAME'] ) )
			$data['resource'] = $_SERVER['SCRIPT_NAME'];
		elseif ( isset( $_SERVER['PHP_SELF'] ) && isset( $_SERVER['QUERY_STRING'] ) )
			$data['resource'] = $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
		elseif ( isset( $_SERVER['PHP_SELF'] ) )
			$data['resource'] = $_SERVER['PHP_SELF'];
		else
			$data['resource'] = '';

		$data['resource'] = mb_substr( $ss->utf8_encode( $data['resource'] ), 0, 255 );
		
		$browser = $this->_parse_user_agent( $_SERVER['HTTP_USER_AGENT'] );
		$data['platform'] = mb_substr( $browser['platform'], 0, 50 );
		$data['browser']  = mb_substr( $browser['browser'], 0, 50 );
		$data['version']  = mb_substr( SimpleStats::parse_version( $browser['version'] ), 0, 15 );
		
		// check whether to ignore this hit
		if ( $data['browser'] == 'Crawler' && $ss->options['log_bots'] == false )
			return;
		
		$t = time();
		
		// use DateTime instead of messing with the default timezone which could affect the calling application
		$tz = new DateTimeZone( $ss->options['tz'] );
		$datetime = new DateTime( 'now', $tz );
		
		$date = $data['date'] = $datetime->format( 'Y-m-d' );
		$time = $datetime->format( 'H:i:s' );
		
		// this isn't actually used at present, but storing local timestamps without a GMT reference is asking for trouble
		$data['offset'] = $datetime->getOffset() / 60;	// store in minutes
		
		$domain_array = explode( '-', $data['domain'] );
		if ( sizeof( $domain_array ) > 2 )
			return;
		
		if ( mb_strlen( $data['domain'] ) >= 25 &&
		     ( !isset( $_SERVER['SERVER_NAME'] ) ||
		       $data['domain'] != mb_eregi_replace( '^www.', '', $_SERVER['SERVER_NAME'] ) ) )
			return;
		
		// attempt to update table
		$table = $ss->tables['visits'];
		
		if ( $ss->options['log_user_agents'] )
			$data['user_agent'] = $ss->esc( mb_substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) );
		
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
	 * Determines the visitor’s IP address.
	 */
	function _determine_remote_ip() {
		$remote_addr = $_SERVER['REMOTE_ADDR'];
		if ( ( $remote_addr == '127.0.0.1' || $remote_addr == '::1' || $remote_addr == $_SERVER['SERVER_ADDR'] ) &&
		     isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && $_SERVER['HTTP_X_FORWARDED_FOR'] ) {
			// There may be multiple comma-separated IPs for the X-Forwarded-For header
			// if the traffic is passing through more than one explicit proxy. Take the
			// last one as being valid. This is arbitrary, but there is no way to know
			// which IP relates to the client computer. We pick the first client IP as
			// this is the client closest to our upstream proxy.
			$remote_addrs = explode( ', ', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$remote_addr = $remote_addrs[0];
		}
		
		return $remote_addr;
	}
	
	/**
	 * Determines the visitor’s country based on their IP address.
	 */
	function _determine_country( $_ip ) {
		if ( SimpleStats::is_geoip() ) {
			include_once( SIMPLE_STATS_PATH.'/geoip/geoip.php' );
			$gi = geoip_open( SIMPLE_STATS_PATH.'/geoip/GeoIP.dat', GEOIP_STANDARD );
			return geoip_country_code_by_addr( $gi, $_ip );
			geoip_close( $gi );
		}
		
		return '';
	}
	
	/**
	 * Detects referrals from search engines and tries to determine the search terms.
	 */
	function _determine_search_terms( $_url ) {
		if ( !is_array( $_url ) )
			$_url = parse_url( $_url );
		
		$search_terms = '';
		
		if ( isset( $_url['host'] ) && isset( $_url['query'] ) ) {
			$sniffs = array( // host regexp, query portion containing search terms, parameterised url to decode
				array( "/images\.google\./i", 'q', 'prev' ),
				array( "/google\./i", 'q' ),
				array( "/\.bing\./i", 'q' ),
				array( "/alltheweb\./i", 'q' ),
				array( "/yahoo\./i", 'p' ),
				array( "/search\.aol\./i", 'query' ),
				array( "/search\.cs\./i", 'query' ),
				array( "/search\.netscape\./i", 'query' ),
				array( "/hotbot\./i", 'query' ),
				array( "/search\.msn\./i", 'q' ),
				array( "/altavista\./i", 'q' ),
				array( "/web\.ask\./i", 'q' ),
				array( "/search\.wanadoo\./i", 'q' ),
				array( "/www\.bbc\./i", 'q' ),
				array( "/tesco\.net/i", 'q' ),
				array( "/yandex\./i", 'text' ),
				array( "/rambler\./i", 'words' ),
				array( "/aport\./i", 'r' ),
				array( "/.*/", 'query' ),
				array( "/.*/", 'q' )
			);
			
			foreach ( $sniffs as $sniff ) {
				if ( preg_match( $sniff[0], $_url['host'] ) ) {
					parse_str( $_url['query'], $q );
					
					if ( isset( $sniff[2] ) && isset( $q[$sniff[2]] ) ) {
						$decoded_url = parse_url( $q[ $sniff[2] ] );
						if ( isset( $decoded_url['query'] ) ) {
							parse_str( $decoded_url['query'], $q );
						}
					}
					
					if ( isset( $q[ $sniff[1] ] ) ) {
						$search_terms = trim( stripslashes( $q[ $sniff[1] ] ) );
						break;
					}
				}
			}
		}
		
		return $search_terms;
	}
	
	/**
	 * Attempts to identify the browser info from its user agent string.
	 */
	function _parse_user_agent( $_ua ) {
		$browser = $version = $platform = '';
		
		$platforms = array(	// name => string (or if empty, use the name )
			'Windows' => 'Win',
			'iPod' => '',
			'iPad' => '',
			'iPhone' => '',
			'Android' => '',
			'Symbian' => 'Symbian',
			'Symbian' => 'SymbOS',
			'Nintendo Wii' => '',
			'PlayStation Portable' => '',
			'Macintosh' => 'Mac',
			'Linux' => '',
			'FreeBSD' => '',
			'i-mode' => 'DoCoMo'
		);
		
		foreach( $platforms as $name => $str ) {
			if( empty( $str ) )
				$str = $name;
			if( strpos( $_ua, $str ) !== false ) {
				$platform = $name;
				break;
			}
		}
		
		$bots = array(
			'charlotte', 'crawl', 'bot', 'bloglines', 'dtaagent', 'feedfetcher', 'ia_archiver', 'java', 'larbin', 'mediapartners', 'metaspinner', 'searchmonkey', 'slurp', 'spider', 'teoma', 'ultraseek', 'waypath', 'yacy', 'yandex', 'scoutjet' );
			
		foreach( $bots as $str ) {
			if( stripos( $_ua, $str ) !== false ) {
				$browser = 'Crawler';
				break;
			}
		}
		
		$sniffs = array( // string, name for display, version regexp, version match, platform (optional)
			array( 'Opera Mini', 'Opera Mini', 'Opera Mini( |/)([\d\.]+)', 2 ),
			array( 'Opera', 'Opera', 'Version/([\d\.]+)', 1 ),
			array( 'Opera', 'Opera', 'Opera( |/)([\d\.]+)', 2 ),
			array( 'MSIE', 'Internet Explorer', 'MSIE ([\d\.]+)', 1 ),
			array( 'Epiphany', 'Epiphany', 'Epiphany/([\d\.]+)',  1 ),
			array( 'Fennec', 'Fennec', 'Fennec/([\d\.]+)',  1 ),
			array( 'Firefox', 'Firefox', 'Firefox/([\d\.]+)',  1 ),
			array( 'Iceweasel', 'Iceweasel', 'Iceweasel/([\d\.]+)',  1 ),
			array( 'Minefield', 'Minefield', 'Minefield/([\d\.]+)',  1 ),
			array( 'Minimo', 'Minimo', 'Minimo/([\d\.]+)',  1 ),
			array( 'Flock', 'Flock', 'Flock/([\d\.]+)',  1 ),
			array( 'Firebird', 'Firebird', 'Firebird/([\d\.]+)', 1 ),
			array( 'Phoenix', 'Phoenix', 'Phoenix/([\d\.]+)', 1 ),
			array( 'Camino', 'Camino', 'Camino/([\d\.]+)', 1 ),
			array( 'Flock', 'Flock', 'Flock/([\d\.]+)',  1 ),
			array( 'Chimera', 'Chimera', 'Chimera/([\d\.]+)', 1 ),
			array( 'Thunderbird', 'Thunderbird', 'Thunderbird/([\d\.]+)',  1 ),
			array( 'Netscape', 'Netscape', 'Netscape[0-9]?/([\d\.]+)', 1 ),
			array( 'OmniWeb', 'OmniWeb', 'OmniWeb/([\d\.]+)', 1 ),
			array( 'Iron', 'Iron', 'Iron/([\d\.]+)', 1 ),
			array( 'Chrome', 'Chrome', 'Chrome/([\d\.]+)', 1 ),
			array( 'Chromium', 'Chromium', 'Chromium/([\d\.]+)', 1 ),
			array( 'Safari', 'Safari', 'Version/([\d\.]+)', 1 ),
			array( 'Safari', 'Safari', 'Safari/([\d\.]+)', 1 ),
			array( 'iCab', 'iCab', 'iCab/([\d\.]+)', 1 ),
			array( 'Konqueror', 'Konqueror', 'Konqueror/([\d\.]+)', 1, 'Linux' ),
			array( 'Midori', 'Midori', 'Midori/([\d\.]+)',  1 ),
			array( 'DoCoMo', 'DoCoMo', 'DoCoMo/([\d\.]+)', 1 ),
			array( 'Lynx', 'Lynx', 'Lynx/([\d\.]+)', 1 ),
			array( 'Links', 'Links', '\(([\d\.]+)', 1 ),
			array( 'W3C_Validator', 'W3C Validator', 'W3C_Validator/([\d\.]+)', 1 ),
			array( 'ApacheBench', 'Apache Bench tool (ab)', 'ApacheBench/(.*)$', 1 ),
			array( 'lwp-request', 'libwww Perl library', 'lwp-request/(.*)$', 1 ),
			array( 'w3m', 'w3m', 'w3m/([\d\.]+)', 1 ),
			array( 'Wget', 'Wget', 'Wget/([\d\.]+)', 1 ),
			array( 'WordPress', 'WordPress', 'WordPress/([\d\.]+)', 1 )
		);
			
		if( !$browser ) {
			foreach ( $sniffs as $sniff ) {
				if ( strpos( $_ua, $sniff[0] ) !== false ) {
					$browser = $sniff[1];
					preg_match( '!' . $sniff[2] . '!', $_ua, $b );
					if ( sizeof( $b ) > $sniff[3] ) {
						$version = $b[ $sniff[3] ];
						if ( sizeof( $sniff ) == 5 ) 
							$platform = $sniff[4];
						break;
					}
				}
			}
		}
		
		
		// old Netscape and Mozilla - can probably get rid of this
		if ( !$browser ) {
			if ( strpos( $_ua, 'Mozilla/4' ) !== false && stripos( $_ua, 'compatible' ) === false ) {
				$browser = 'Netscape';
				preg_match( 'Mozilla/([\d\.]+)', $_ua, $b );
				$version = $b[1];
			} elseif ( ( strpos( $_ua, 'Mozilla/5' ) !== false && stripos( $_ua, 'compatible' ) === false ) || strpos( $_ua, 'Gecko' ) !== false ) {
				$browser = 'Mozilla';
				preg_match( 'rv(:| )([\d\.]+)', $_ua, $b );
				$version = $b[2];
			}
		}
		
		return compact( 'browser', 'version', 'platform' );
	}
	
}

new SimpleStatsHit();
