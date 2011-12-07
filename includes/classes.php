<?php
class SimpleStats {
	private $connection;
	private $debug = true;
	private $installed = false;
	public $tables = array();
	public $options = array();
	public $tz;
	
	function __construct() {
		if( !defined( 'SIMPLE_STATS_DB_PREFIX' ) )
			return;	// config file missing
		
		$this->tables['options'] = SIMPLE_STATS_DB_PREFIX . '_options';
		$this->tables['visits'] =  SIMPLE_STATS_DB_PREFIX . '_visits';
		$this->tables['archive'] =  SIMPLE_STATS_DB_PREFIX . '_archive';
		
		$this->connect();
		$this->options = $this->load_options();
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
		
		$this->installed = isset( $options['version'] );	// first run?
		
		return $options;
	}
	
	function setup_options() {
		$defaults = array(
			'stats_enabled' => true,
			'site_name' => '',
			'login_required' => false,
			'username' => '',
			'password' => '',
			'tz' => 'UTC',
			'lang' => 'en-gb',
			'log_user_agents' => false,
			'log_bots' => false,
			'ignored_ips' => array(),
			'aggregate_after' => 0,
			'last_aggregated' => '0',
			'salt' => sha1( rand() . date('Ymj') . 'simple-stats' . $_SERVER['SERVER_NAME'] ),
			'version' => '1.0'
		);
		
		$options = $this->load_options();
		
		foreach( $defaults as $k => $v ) {
			if( !isset( $options[$k] ) ) {
				$options[$k] = $v;
				$this->add_option( $k, $v );
			}
		}
		
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
	
	static function sql2time( $str ) {
		return strtotime( $str . ' +0000' );
	}
	
	static function determine_language() {
		$lang_choice = '';
		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			// Capture up to the first delimiter (comma found in Safari)
			preg_match( "/([^,;]*)/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $langs );
			$lang_choice = $langs[0];
		}
		return strtolower( $lang_choice );
	}
	
	static function parse_version( $_raw_version, $_parts=2 ) {
		$version_numbers = explode( '.', $_raw_version );
		$value = '';

		for ( $x=0; $x<$_parts; $x++ ) {
			if ( sizeof( $version_numbers ) > $x ) {
				if ( $value != '' ) {
					$value .= '.';
				}
				$value .= $version_numbers[$x];
			}
		}

		return $value;
	}
	
	static function is_geoip() {
		return ( file_exists( SIMPLE_STATS_PATH .'/geoip/geoip.php' ) && file_exists( SIMPLE_STATS_PATH.'/geoip/GeoIP.dat' ) );
	}
	
	function hash( $str ) {
		return sha1( $str . $this->options['salt'] );
	}
}