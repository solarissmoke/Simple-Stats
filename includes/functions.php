<?php

function page_head() {
	global $ss, $filters, $page, $ajax;
	include( SIMPLE_STATS_PATH.'/includes/_head.php' );
}

function page_foot() {
	global $ss, $ajax;
	include( SIMPLE_STATS_PATH.'/includes/_foot.php' );
}

function filter_link( $_filters, $text ) {
	global $is_archive;
	$text = htmlspecialchars( $text );
	
	// avoid super-long referrer strings
	if( strlen( $text ) > 100 )
		$text = mb_substr( $text, 0, 100 ) . '&hellip;';
	
	// cannot filter archives
	if( $is_archive )
		return $text;
	
	$url = filter_url( $_filters );
	return "<a href='./$url' title='$text'>$text</a>";
}

function filter_url( $_filters, $_first_separator='?' ) {
	if ( !is_array( $_filters ) )
		return '';
	
	$shown_first = false;
	$str = '';
	$cleaned_filters = $_filters;
	
	if ( isset( $_filters['yr'] ) && $_filters['yr'] == date( 'Y' ) ) {
		unset( $cleaned_filters['yr'] );
		if ( isset( $_filters['mo'] ) && $_filters['mo'] == date( 'n' ) )
			unset( $cleaned_filters['mo'] );
	}
	
	foreach ( $cleaned_filters as $key => $value ) {
		if ( $shown_first ) {
			$str .= '&amp;';
		} else {
			$str .= $_first_separator;
			$shown_first = true;
		}
		$str .= 'filter_'. $key .'='.rawurlencode( $value );
	}
	
	return $str;
}

function format_number( $_number, $_dp=1 ) {
	$decimal = __( '.', 'decimal_point' );
	$thousands = __( 'core', 'thousands_separator' );
	$str = number_format( $_number, $_dp, $decimal, $thousands );
	if ( $str == '0'.$decimal.'0' && $_dp == 1 ) {
		$str2 = number_format( $_number, 2, $decimal, $thousands );
		if ( $str2 != '0'.$decimal.'00' ) {
			return $str2;
		}
	}
	return $str;
}

/**
 * Detects whether a user is logged in
 */
function is_logged_in() {
	global $ss;
	if( isset( $_POST['simple-stats-login'] ) && isset( $_POST['username'] ) && isset( $_POST['password'] ) ) {
		// process login request
		if( $_POST['username'] == $ss->options['username'] && $_POST['password'] == $ss->options['password'] ) {
			@setcookie( 'simple_stats', $ss->hash( $ss->options['username'] . $ss->options['password'] ), time() + 31536000, '/', '' );
			return true;
		}
	}

	return ( isset( $_COOKIE['simple_stats'] ) && $_COOKIE['simple_stats'] == $ss->hash( $ss->options['username'] . $ss->options['password'] ) );
}

function request_login() {
	header( 'Status: 302 Moved Temporarily' );
	header( 'Location: ./?p=login' );
	exit;
}

function logout() {
	setcookie( 'simple_stats', '', time() + 31536000, '/', '' );
	request_login();
}

function sp2nb( $_str ) {
	return str_replace( ' ', '&nbsp;', $_str );
}

function is_filtered( $field ) {
	global $filters;
	return isset( $filters[$field] );
	// helper function to determine if a filter is active
}

/* i18n - needs work */
function __( $str ) {
	return $str;
}
