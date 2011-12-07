<?php
/**
 * Simple Stats: PHP web statistics software. See the license.txt file for copyright and licensing information.
 */

if ( get_magic_quotes_gpc() ) {
	foreach ( array_keys( $_GET ) as $key ) 
		$_GET[$key] = stripslashes( $_GET[$key] );
	foreach ( array_keys( $_POST ) as $key ) 
		$_POST[$key] = stripslashes( $_POST[$key] );
	foreach ( array_keys( $_COOKIE ) as $key )
		$_COOKIE[$key] = stripslashes( $_COOKIE[$key] );
	$_REQUEST = array_merge( $_GET, $_POST );
}

define( 'SIMPLE_STATS_PATH', realpath( dirname( __FILE__ ) ) );

if( file_exists( SIMPLE_STATS_PATH.'/config.php' ) )
	require_once( SIMPLE_STATS_PATH.'/config.php' );
require_once( SIMPLE_STATS_PATH.'/includes/classes.php' );
require_once( SIMPLE_STATS_PATH.'/includes/functions.php' );
include_once( SIMPLE_STATS_PATH.'/includes/countries.php' );

$page = ( isset( $_GET['p'] ) && in_array( $_GET['p'], array( 'paths', 'overview', 'options', 'js', 'login', 'logout' ) ) ) ? $_GET['p'] : 'overview';

$ss = new SimpleStats();

if( !$ss->is_installed() ) {
	// check whether we've just finished setup
	if( isset( $_POST['action'] ) && $_POST['action'] == 'complete_setup' ) {
		$ss->setup_options();
	}
	else {
		$page = 'setup';
		require_once( SIMPLE_STATS_PATH.'/includes/setup.php' );
		exit;
	}
}

date_default_timezone_set( $ss->options['tz'] );

if ( $ss->options['login_required'] ) {
	if ( $page == 'logout' )
		logout();
	if ( $page != 'login' && !is_logged_in() )
		request_login();
}

require_once( SIMPLE_STATS_PATH.'/includes/'.$page.'.php' );

render_page();
