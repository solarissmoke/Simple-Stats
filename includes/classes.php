<?php
class SimpleStats {
	private $connection;
	private $debug = false;
	private $installed = false;
	public $tables = array();
	public $options = array();
	public $tz;
	const version = '1.0.1';
	const db_version = 5;
	
	function __construct() {
		if( !defined( 'SIMPLE_STATS_DB_PREFIX' ) )
			return;	// config file missing
		
		$this->tables['options'] = SIMPLE_STATS_DB_PREFIX . '_options';
		$this->tables['visits'] =  SIMPLE_STATS_DB_PREFIX . '_visits';
		$this->tables['archive'] =  SIMPLE_STATS_DB_PREFIX . '_archive';
		
		$this->connect();
		$this->options = $this->load_options();
		
		// upgrade check
		if( $this->installed && ( !isset( $this->options['db_version'] ) || $this->options['db_version'] < self::db_version ) ) {
			$this->upgrade();
			$this->setup_options();
		}
		
		if( $this->installed && defined( 'SIMPLE_STATS_PASSWORD_RESET' ) && SIMPLE_STATS_PASSWORD_RESET ) {
			$this->update_option( 'password', '' );
			$this->update_option( 'login_required', false );
			$this->options = $this->load_options();
		}
	}
	
	function is_installed() {
		return $this->installed;
	}
	
	function connect() {
		if( !$this->connection = mysql_connect( SIMPLE_STATS_DB_SERVER, SIMPLE_STATS_DB_USER, SIMPLE_STATS_DB_PASS, true ) ) {
			$this->log_error();
			return false;
		}
		
		if( ! mysql_select_db( SIMPLE_STATS_DB, $this->connection ) ) {
			$this->log_error();
			return false;
		}
		
		@mysql_query( 'SET NAMES utf8', $this->connection );
		return true;
	}
	
	function close(){
		mysql_close( $this->connection );
	}
	
	private function load_options(){
		$options = array();
		$result = $this->query( "SELECT * FROM `{$this->tables['options']}`" );
		while( $row = @mysql_fetch_assoc( $result ) ) {
			$options[$row['option']] = unserialize( $row['value'] );
		}
		
		$this->installed = isset( $options['stats_enabled'] );	// first run?
		
		return $options;
	}
	
	private function upgrade() {
		$v = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;
		$visits_table = $this->tables['visits'];
		if( $v && $v < 2 ) {
			// upgrade from db version 1 to 2 - platform and browser columns have changed to integer values
			$ua = new SimpleStatsUA();
			foreach( $ua->get_all_browser_names() as $id => $name ) {
				$this->query( "UPDATE `$visits_table` SET `browser` = '$id' WHERE `browser` = '$name'" );
			}
			$this->query( "UPDATE `$visits_table` SET `browser` = '1' WHERE `browser` = 'Crawler'" );
			$this->query( "UPDATE `$visits_table` SET `browser` = CEIL(`browser`)" );	// fixes any we missed
			$this->query( "ALTER TABLE `$visits_table` MODIFY `browser` TINYINT UNSIGNED NOT NULL DEFAULT '0'" );
			
			foreach( $ua->get_all_platform_names() as $id => $name ) {
				$this->query( "UPDATE `$visits_table` SET `platform` = '$id' WHERE `platform` = '$name'" );
			}
			$this->query( "UPDATE `$visits_table` SET `platform` = CEIL(`platform`)" );
			$this->query( "ALTER TABLE `$visits_table` MODIFY `platform` TINYINT UNSIGNED NOT NULL DEFAULT '0'" );
			
			$this->query( "ALTER TABLE `$visits_table` ADD KEY `ua`(`browser`, `platform`)" );
			$this->query( "ALTER TABLE `$visits_table` ADD KEY `country`(`country`)" );
		}
		if( $v && $v < 3 ) {
			// save password as hash
			if( !empty( $this->options['password'] ) )
				$this->update_option( 'password', $this->hash( trim( $this->options['password'] ) ) );
		}
		if( $v && $v < 4 ) {
			// bump referrer field size
			$this->query( "ALTER TABLE `$visits_table` MODIFY `referrer` VARCHAR(512) NOT NULL DEFAULT ''" );
		}
		if( $v && $v < 5 ) {
			// bump ip field size to allow ipv6 addresses
			$this->query( "ALTER TABLE `$visits_table` MODIFY `remote_ip` VARCHAR(39) NOT NULL DEFAULT ''" );
		}
	}
	
	function setup_options() {
		$defaults = array(
			'stats_enabled' => true,
			'site_name' => '',
			'login_required' => false,
			'username' => '',
			'password' => '',
			'tz' => date_default_timezone_get(),
			'lang' => 'en-gb',
			'log_user_agents' => false,
			'log_bots' => false,
			'ignored_ips' => array(),
			'aggregate_after' => 0,
			'last_aggregated' => '0',
			'salt' => sha1( rand() . date('Ymj') . 'simple-stats' . $_SERVER['SERVER_NAME'] ),
			'db_version' => self::db_version
		);
		
		$options = $this->load_options();
		
		foreach( $defaults as $k => $v ) {
			if( !isset( $options[$k] ) ) {
				$options[$k] = $v;
				$this->add_option( $k, $v );
			}
		}
		
		$this->update_option( 'db_version', self::db_version );
		
		$this->options = $this->load_options();	// reload
	}
	
	function add_option( $option, $value ) {
		$value = $this->esc( serialize( $value ) );
		$this->query( "INSERT INTO `{$this->tables['options']}` ( `option`, `value` ) VALUES ( '$option', '$value' )" );
	}
	
	function update_option( $option, $value ) {
		$value = $this->esc( serialize( $value ) );
		$rows = $this->query( "UPDATE `{$this->tables['options']}` SET `value` = '$value' WHERE `option` = '$option'" );
	}

	private function log_error( $err = false ){
		if( $this->debug )
			error_log( $err ? $err : mysql_error() );
	}
	
	function query( $query ) {
		//error_log( $query );
		$result = mysql_query( $query, $this->connection );

		if ( $result === false ) {
			$this->log_error( $query );
			$this->log_error( mysql_error() );
			return false;
		}
		
		if ( preg_match( '/^\s*(insert|delete|update|replace) /i', $query ) )
			return mysql_affected_rows( $this->connection );

		return $result;
	}
	
	function esc( $str ) {
		return mysql_real_escape_string( $str, $this->connection );
	}
	
	static function utf8_encode( $_str ) {
		$encoding = mb_detect_encoding( $_str );
		if ( $encoding == false || strtoupper( $encoding ) == 'UTF-8' || strtoupper( $encoding ) == 'ASCII' )
			return $_str;

		return iconv( $encoding, 'UTF-8', $_str );
	}
	
	static function determine_language() {
		$lang_choice = '';
		if ( !empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			// Capture up to the first delimiter (comma found in Safari)
			preg_match( "/([^,;]*)/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $langs );
			$lang_choice = $langs[0];
		}
		return strtolower( $lang_choice );
	}
	
	static function parse_version( $_raw_version, $_parts = 2 ) {
		$value = implode( '.', array_slice( explode( '.', $_raw_version ), 0, $_parts ) );
		// skip trailing zeros - most browsers have rapid release cycles now
		if( substr( $value, -2 ) == '.0' )
			$value = substr_replace( $value, '', -2 );
		return $value;
	}
	
	static function is_geoip() {
		return ( file_exists( SIMPLE_STATS_PATH .'/geoip/geoip.php' ) && file_exists( SIMPLE_STATS_PATH.'/geoip/GeoIP.dat' ) );
	}
	
	function hash( $str ) {
		return sha1( $str . $this->options['salt'] );
	}
}