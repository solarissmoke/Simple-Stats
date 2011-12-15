<?php
class SimpleStatsUA {
	private $browsers, $platforms, $browser_details, $platform_details;
	
	function __construct() {
		$this->default_version_regex = '/([\d\.]+)';	// insert the browser string in front of this
 
		// Browsers: ID => 'String' array( 'string' (req), 'display_name' (opt), 'regex' (opt, see default), 'platform' (opt)
		// These IDs must never change!
		$this->browsers = array(
			// 0 is reserved for unknown;
			// 1 is reserved for bots;
			2 => 'Firefox',
			3 => 'MSIE',
			4 => 'Chrome',
			5 => 'Opera',
			6 => 'Opera Mini',
			7 => 'Safari',
			8 => 'Epiphany',
			9 => 'Fennec',
			10 => 'Iceweasel',
			11 => 'Minefield',
			12 => 'Minimo',
			13 => 'Flock',
			14 => 'Firebird',
			15 => 'Phoenix',
			16 => 'Camino',
			17 => 'Chimera',
			18 => 'Thunderbird',
			19 => 'Netscape',
			20 => 'OmniWeb',
			21 => 'Iron',
			22 => 'Chromium',
			23 => 'iCab',
			24 => 'Konqueror',
			25 => 'Midori',
			26 => 'DoCoMo',
			27 => 'Lynx',
			28 => 'Links',
			29 => 'lwp-request',
			30 => 'w3m',
			31 => 'Wget'
		);
		
		// additional details have to be specified for some browsers. Options are:
		// display_name, if different from string; regex - if different from default version regex; platform (overrides platform sniffing)
		$this->browser_details = array(
			3 => array( 'display_name' => 'Internet Explorer', 'regex' => 'MSIE ([\d\.]+)' ),
			5 => array( 'regex' => '(?:Opera|Version)(?: |/)([\d\.]+)' ),
			6 => array( 'regex' => 'Opera Mini(?: |/)([\d\.]+)' ),
			7 => array( 'regex' => '(?:Safari|Version)/([\d\.]+)' ),
			19 => array( 'regex' => 'Netscape[0-9]?/([\d\.]+)' ),
			24 => array( 'platform' => 'Linux' ),
			28 => array( 'regex' => '\(([\d\.]+)' ),
			29 => array( 'display_name' => 'libwww Perl library', 'regex' => 'lwp-request/(.*)$' )
		);
 
		// Platforms: ID => array( 'string' (req), 'display_name' (opt),
		// These IDs must never change!
		$this->platforms = array(
			// 0 is reserved for unknown;
			1 => 'Win',
			2 => 'Linux',
			3 => 'Mac',
			4 => 'Android',
			5 => 'Symbian',
			6 => 'iPod',
			7 => 'iPad',
			8 => 'iPhone',
			9 => 'FreeBSD',
			10 => 'i-mode',
			11 => 'Nintendo Wii',
			12 => 'PlayStation Portable'
		);
		
		$this->platform_details = array(
			1 => array( 'display_name' => 'Windows' ),
			3 => array( 'display_name' => 'Macintosh' ),
			10 => array( 'display_name' => 'DoCoMo' )
		);
	}
	
	function get_all_browser_names() {
		$result = array();
		foreach( array_keys( $this->browsers ) as $id )
			$result[$id] = $this->browser_name_from_id( $id );

		return $result;
	}
	
	function get_all_platform_names() {
		$result = array();
		foreach( array_keys( $this->platforms ) as $id )
			$result[$id] = $this->platform_name_from_id( $id );

		return $result;
	}
	
	function parse_user_agent( $_ua ) {
		$default_version_regex = '/([\d\.]+)';
		$result = array( 'browser' => '', 'version' => '', 'platform' => '' );
		
		$bots = array( 'charlotte', 'crawl', 'bot', 'bloglines', 'dtaagent', 'feedfetcher', 'ia_archiver', 'java', 'larbin', 'mediapartners', 'metaspinner', 'searchmonkey', 'slurp', 'spider', 'teoma', 'ultraseek', 'waypath', 'yacy', 'yandex', 'scoutjet', 'harvester', 'facebookexternal', 'mail.ru/' );
		
		foreach( $bots as $str ) {
			if( stripos( $_ua, $str ) !== false ) {
				$result['browser'] = 1;
				return $result;	// no need to bother with the rest
			}
		}
		
		foreach( $this->platforms as $id => $name ) {
			if( strpos( $_ua, $name ) !== false ) {
				$result['platform'] = $id;
				break;
			}
		}
		
		foreach ( $this->browsers as $id => $name ) {
			$details = isset( $this->browser_details[$id] ) ? $this->browser_details[$id] : array();
			if ( strpos( $_ua, $name ) !== false ) {
				$result['browser'] = $id;
				$regex = isset( $details['regex'] ) ? $details['regex'] : $name . $default_version_regex;
				preg_match( '!' . $regex . '!', $_ua, $b );
				if ( $b ) {
					$result['version'] = $b[1];
					if ( isset( $details['platform'] ) ) 
						$result['platform'] = $details['platform'];
					break;
				}
			}
		}
		
		return $result;	// an array of ( 'browser' (ID), 'version', 'platform' (ID) )
	}
	
	function browser_name_from_id( $id ) {
		$id = intval( $id );
		if( isset( $this->browsers[$id] ) )
			return isset( $this->browser_details[$id]['display_name'] ) ? $this->browser_details[$id]['display_name'] : $this->browsers[$id];
		
		return false;
	}
	
	function platform_name_from_id( $id ) {
		$id = intval( $id );
		if( isset( $this->platforms[$id] ) )
			return isset( $this->platform_details[$id]['display_name'] ) ? $this->platform_details[$id]['display_name'] : $this->platforms[$id];
		
		return false;
	}
}