<?php
$ajax = ( isset( $_REQUEST['ajax'] ) && $_REQUEST['ajax'] == 1 );
$is_archive = false;

$field_names = array(
	'remote_ip' => __( 'IP addresses' ),
	'search_terms' => __( 'Search terms' ),
	'domain' => __( 'Source domains' ),
	'referrer' => __( 'Referrers' ),
	'resource' => __( 'Pages' ),
	'country' => __( 'Countries' ),
	'language' => __( 'Languages' ),
	'browser' => __( 'Browsers' ),
	'version' => __( 'Versions' ),
	'platform' => __( 'Operating systems' ),
	'resolution' => __( 'Screen sizes' ),
	'source' => __( 'Visit source' )
);

// set up filters
$filters = array();
$has_filters = false;
if ( isset( $_GET['filter_date'] ) && $_GET['filter_date'] != '0' ) {
	// parse pretty dates of the form yyyy/mm[/dd]
	preg_match( '|(\d{4})-(\d{1,2})(?:-(\d{1,2}))?|', $_GET['filter_date'], $dates );
	$filters['yr'] = $dates[1];
	$filters['mo'] = $dates[2];
	if( isset( $dates[3] ) )
		$filters['dy'] = $dates[3];
}

foreach ( array_keys( $field_names ) as $key ) {
	if ( isset( $_GET["filter_$key"] ) && $_GET["filter_$key"] != '0' ) {
		$has_filters = true;
		$filters[$key] = $_GET["filter_$key"];
	}
}

$filters['yr'] = isset( $filters['yr'] ) ? valid_yr( $filters['yr'] ) : date( 'Y' );
$filters['mo'] = isset( $filters['mo'] ) ? valid_mo( $filters['mo'] ) : date( 'n' );

if ( isset( $filters['dy'] ) )
	$filters['dy'] = valid_dy( $filters['dy'], $filters['mo'], $filters['yr'] );
	
// go
function render_page() {
	global $loaded_data, $filters, $ss;
	aggregate_old_data();
	
	// archives or current data?
	if( intval( $filters['yr'] ) . intval( $filters['mo'] ) <= intval( $ss->options['last_aggregated'] ) )
		$loaded_data = load_archive( $filters );
	else
		$loaded_data = load_data( $filters );
	
	include( SIMPLE_STATS_PATH.'/includes/overview_html.php' );
	render_page_html();
}

function aggregate_old_data(){
	global $ss;
	$after = $ss->options['aggregate_after'];
	
	if( $after == 0 )
		return;
	
	// start from the earliest month to aggregate
	$yr = date('Y');
	$mo = date('n') - $after - 1;
	
	while( $mo < 1 ) {
		$yr --;
		$mo += 12;
	}
	
	if( isset( $ss->options['last_aggregated'] ) && $ss->options['last_aggregated'] && $yr . $mo >= intval( $ss->options['last_aggregated'] ) )
		return;		// we're already up to date
	
	$ss->update_option( 'last_aggregated', $yr . $mo );
	
	$result = $ss->query( "SELECT MIN(`date`) FROM {$ss->tables['visits']} LIMIT 1" );
	if( ! $result )
		return;
		
	$earliest = mysql_fetch_row( $result );
	preg_match( '/^(\d{4})-(\d{2})/', $earliest[0], $matches );
	
	$min_yr = intval( $matches[1] );
	$min_mo = intval( $matches[2] );
		
	// is the earliest data within cutoff range?
	if( gmmktime( 0, 0, 0, $mo, 1, $yr ) < gmmktime( 0, 0, 0, $min_mo, 1, $min_yr ) )
		return;
	
	while( true ) {		
		$data = load_data( array( 'yr' => $yr, 'mo' => $mo ) );
		$data = $ss->esc( gzdeflate( serialize( $data ) ) );
		
		// put into archive
		$ss->query( "INSERT INTO `{$ss->tables['archive']}` (`yr`, `mo`, `data`) VALUES ( '$yr', '$mo', '$data' )" );
		
		$endofmonth = "$yr-$mo-" . days_in_month( $mo, $yr );
		
		// delete raw data
		$ss->query( "DELETE FROM `{$ss->tables['visits']}` WHERE `date` >= '$yr-$mo-01' AND `date` <= '$endofmonth'" );
		
		if( $yr == $min_yr && $mo == $min_mo )
			break;
		
		$mo --;
		if( $mo < 1 ) {
			$yr --;
			$mo += 12;
		}
	}
}

function load_archive( $_filters ) {
	global $ss, $is_archive;
	$is_archive = true;
	$yr = $_filters['yr'];
	$mo = $_filters['mo'];
	$result = $ss->query( "SELECT `data` FROM `{$ss->tables['archive']}` WHERE `yr` = '$yr' AND `mo` = '$mo'" );
	if( !$result )
		return array( 'pages' => array(), 'visits' => array() );
		
	$data = mysql_fetch_row( $result );
	return unserialize( gzinflate( $data[0] ) );
}

function load_data( $_filters ) {
	global $ss, $field_names;
	
	$fields = array_keys( $field_names );
	
	$yr = intval( $_filters['yr'] );
	$mo = intval( $_filters['mo'] );
	$dy = isset( $_filters['dy'] ) ? intval( $_filters['dy'] ) : false;
	
	// work out date range
	$d0 = $dy ? $dy : 1;
	$dn = $dy ? $dy : days_in_month( $mo, $yr );
	
	$start_ts = gmmktime( 0, 0, 0, $mo, $d0, $yr );
	$end_ts = gmmktime( 0, 0, 0, $mo, $dn, $yr );
	$start_date = gmdate( 'Y-m-d', $start_ts );
	$end_date = gmdate( 'Y-m-d', $end_ts );
	
	$date_query = ( $start_date == $end_date ) ? "`date` = '$start_date'" : "`date` >= '$start_date' AND `date` <= '$end_date'";	
	
	$query = "SELECT * FROM `{$ss->tables['visits']}` WHERE $date_query";
	
	foreach ( $fields as $key ) {
		if( !isset( $_filters[$key] ) )
			continue;
			
		$v = $ss->esc( $_filters[$key] );
		// resource is tricky
		if( $key == 'resource' )
			$query .= " AND `$key` LIKE '% $v%'";
		else
			$query .= " AND `$key` = '$v'";
	}
	
	$result = $ss->query( $query );

	// we also need date/time data for visits
	$extra_fields = isset( $_filters['dy'] ) ? array( 'start_time' ) : array( 'date' );
	return parse_data( $result, array_merge( $fields, $extra_fields ), $_filters );
}

function parse_data( $_result, $_fields, $_filters ) {
	global $ss;
	
	$visits = $pages = array();
	
	$source = array( 'search_terms' => 0, 'referrer' => 0, 'direct' => 0 );
	
	while ( $row = @mysql_fetch_assoc( $_result ) ) {
		// extract individual page info
		$resources = explode( "\n", $row['resource'] );

		foreach( $resources as $r ) {
			if( empty( $r ) )
				continue;
				
			list( $time, $resource ) = explode( ' ', $r, 2 );
			$resource = trim( $resource ); 
			
			// if filtering by page then ignore everything else but that page
			if( isset( $_filters['resource'] ) && $resource != $_filters['resource'] )
				continue;

			if( isset( $pages[$resource] ) )
				$pages[$resource] ++;
			else
				$pages[$resource] = 1;
		}
		
		if ( isset( $row['search_terms'] ) && isset( $row['referrer'] ) ) {
			if ( ! empty( $row['search_terms'] ) )
				$source['search_terms']++;
			elseif ( ! empty( $row['referrer'] ) )
				$source['referrer']++;
			else
				$source['direct']++;
		}
		
		if( array_sum( $source ) )
			$visits['source'] = $source;
		
		// add up info for other fields, with a few tweaks
		foreach ( $_fields as $field ) {
			if ( !isset( $row[$field] ) || $field == 'resource' )	// resource has been dealt with already
				continue;
			
			$value = $row[$field];
			
			if( $field == 'date' || $field == 'start_time' ) {	// save both hits as well as visits
				if ( isset( $visits[$field][$value] ) ) {
					$visits[$field][$value]['visits'] ++;
					$visits[$field][$value]['hits'] += $row['hits'];
				} else {
					$visits[$field][$value] = array( 'hits' => $row['hits'], 'visits' => 1);
				}
			}
			
			// these items don't have an "Unknown" category
			if( in_array( $field, array( 'search_terms', 'referrer', 'domain' ) ) && empty( $value ) )
				continue;
			
			// store version as Browser => array( version => hits )
			if ( $field == 'version' ) {
				$browser = $row['browser'];
				if ( !isset( $visits[$field][$browser] ) ) 
					$visits[$field][$browser] = array();
				
				if ( isset( $visits[$field][$browser][$value] ) )
					$visits[$field][$browser][$value] ++;
				else
					$visits[$field][$browser][$value] = 1;
				continue;
			}

			if ( isset( $visits[$field][$value] ) ) {
				$visits[$field][$value] ++;
			} else {
				$visits[$field][$value] = 1;
			}
		}
	}

	return array( 'visits' => $visits, 'pages' => $pages );;
}

function valid_dy( $_dy, $_mo, $_yr ) {
	$dy = max( 1, min( date( 'j', gmmktime( 12, 0, 0, $_mo + 1, 0, $_yr ) ), intval( $_dy ) ) );
	if ( $_yr == date( 'Y' ) && $_mo == date( 'n' ) )
		$dy = min( date( 'j' ), $dy );

	return $dy;
}

function valid_mo( $_mo ) {
	return max( 1, min( 12, intval( $_mo ) ) );
}

function valid_yr( $_yr ) {
	return max( 1970, min( 3000, intval( $_yr ) ) );
}

function days_in_month( $_mo, $_yr ) {
	return date( 'j', mktime( 12, 0, 0, $_mo + 1, 0, $_yr ) );
}

function date_label( $_array, $_dy_override = null ) {
	$yr = $_array['yr'];
	$mo = isset( $_array['mo'] ) ? $_array['mo'] : null;
	$dy = isset( $_array['dy'] ) ? $_array['dy'] : null;
	if ( $_dy_override === false )
		$dy = null;
	elseif ( $_dy_override > 0 )
		$dy = valid_dy( $_dy_override, $mo, $yr );
	
	if ( $dy != null && $mo != null )
		return gmstrftime( '%a %d %b %Y', gmmktime( 12, 0, 0, $mo, $dy, $yr ) );
	
	if ( $mo != null )
		return gmstrftime( '%b %Y', gmmktime( 12, 0, 0, $mo, 1, $yr ) );
	
	return $yr;
}

function prev_period( $_query_fields, $_ignore_dy = false ) {
	$prev_fields = $_query_fields;
	
	if ( $_ignore_dy )
		unset( $prev_fields['dy'] );

	if ( !$_ignore_dy && isset( $_query_fields['dy'] ) && isset( $_query_fields['mo'] ) && isset( $_query_fields['yr'] ) ) {
		$prev_ts = gmmktime( 12, 0, 0, $_query_fields['mo'], $_query_fields['dy'] - 1, $_query_fields['yr'] );
		$prev_fields['dy'] = date( 'j', $prev_ts );
		$prev_fields['mo'] = date( 'n', $prev_ts );
		$prev_fields['yr'] = date( 'Y', $prev_ts );
	} elseif ( isset( $_query_fields['mo'] ) && isset( $_query_fields['yr'] ) ) {
		$prev_ts = gmmktime( 12, 0, 0, $_query_fields['mo'] - 1, 1, $_query_fields['yr'] );
		$prev_fields['mo'] = date( 'n', $prev_ts );
		$prev_fields['yr'] = date( 'Y', $prev_ts );
	} elseif ( isset( $_query_fields['yr'] ) ) {
		$prev_fields['yr'] = $_query_fields['yr'] - 1;
	}
	
	return $prev_fields;
}

function next_period( $_query_fields, $_ignore_dy = false ) {
	$next_fields = $_query_fields;
	
	if ( $_ignore_dy )
		unset( $next_fields['dy'] );

	if ( !$_ignore_dy && isset( $_query_fields['dy'] ) && isset( $_query_fields['mo'] ) && isset( $_query_fields['yr'] ) ) {
		$next_ts = gmmktime( 12, 0, 0, $_query_fields['mo'], $_query_fields['dy'] + 1, $_query_fields['yr'] );
		$next_fields['dy'] = date( 'j', $next_ts );
		$next_fields['mo'] = date( 'n', $next_ts );
		$next_fields['yr'] = date( 'Y', $next_ts );
	} elseif ( isset( $_query_fields['mo'] ) && isset( $_query_fields['yr'] ) ) {
		$next_ts = gmmktime( 12, 0, 0, $_query_fields['mo'] + 1, 1, $_query_fields['yr'] );
		$next_fields['mo'] = date( 'n', $next_ts );
		$next_fields['yr'] = date( 'Y', $next_ts );
	} elseif ( isset( $_query_fields['yr'] ) ) {
		$next_fields['yr'] = $_query_fields['yr'] + 1;
	}

	return $next_fields;
}