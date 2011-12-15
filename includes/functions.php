<?php
function page_head() {
	global $ss, $filters, $page, $ajax;
	include( SIMPLE_STATS_PATH.'/includes/_head.php' );
}

function page_foot() {
	global $ss, $ajax, $script_i18n;
	include( SIMPLE_STATS_PATH.'/includes/_foot.php' );
}

function filter_link( $_filters, $text ) {
	global $is_archive;
	
	// avoid super-long referrer strings
	if( strlen( $text ) > 100 )
		$text = substr( $text, 0, 100 ) . '&hellip;';
	
	$text = htmlspecialchars( $text );
	
	// cannot filter archives
	if( $is_archive )
		return $text;
	
	$url = filter_url( $_filters );
	return "<a href='./$url' class='filter'>$text</a>";
}

function get_date_filter( $yr, $mo, $dy = false ) {
	$mo = sprintf( '%02d', $mo );
	$dy = $dy ? sprintf( '%02d', $dy ) : '';
	
	if( !$dy && $yr == date('Y') && $mo == date('m') )
		return '_';
	
	return "$yr-$mo" . ( $dy ? "-$dy" : '' );
}

function filter_url( $_filters ) {
	if ( !is_array( $_filters ) )
		return '';
	
	$shown_first = false;
	$str = '';
	$cleaned_filters = $_filters;
	
	unset( $cleaned_filters['yr'], $cleaned_filters['mo'], $cleaned_filters['dy'] );
	
	$yr = isset( $_filters['yr'] ) ?  $_filters['yr'] : date('Y'); 
	$mo = isset( $_filters['mo'] ) ?  $_filters['mo'] : date('m');
	$dy = isset( $_filters['dy'] ) ? $_filters['dy'] : false;
	$date =  get_date_filter( $yr, $mo, $dy );
	if( $date != '_' )
		$cleaned_filters['date'] = $date;
	
	$sep = '?';
	foreach ( $cleaned_filters as $key => $value ) {
		$str .= $sep . 'filter_'. $key . '=' . rawurlencode( $value );
		$sep = '&amp;';
	}
	
	return $str;
}

function format_number( $_number, $_dp = 1 ) {
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

function needs_authentication() {
	global $ss, $page;
	if( $ss->options['login_required'] == 'all' || ( $page == 'options' && $ss->options['login_required'] == 'config' ) )
		return true;
	return false;
}

function is_logged_in() {
	global $ss;
	return ( isset( $_COOKIE['simple_stats'] ) && $_COOKIE['simple_stats'] == $ss->hash( $ss->options['username'] . $ss->options['password'] ) );
}

function request_login( $origin = false ) {
	header( 'Location: ./?p=login' . ( $origin ? '&origin=' . $origin : '' ), true, 302 );
	exit;
}

function logout() {
	setcookie( 'simple_stats', '', time() + 31536000, '/', '' );
	request_login( 'logout' );
}

function sp2nb( $_str ) {
	return str_replace( ' ', '&nbsp;', $_str );
}

/* i18n - needs work */
function __( $str ) {
	return $str;
}

function scripts_i18n(){
	global $script_i18n;
	$script_i18n['filter_title'] = __( 'Filter results for this item' );
	$script_i18n['link_title'] = __( 'Visit this page' );
	$script_i18n['ext_link_title'] = __( 'Visit this referrer page' );
}
