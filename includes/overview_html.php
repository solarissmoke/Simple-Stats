<?php
function render_page_html() {
	page_head();
	display_filters();
	display_content();
	page_foot();
}

function display_filters(){
	global $loaded_data, $filters, $is_archive;
	
	echo '<nav id="side">';
	echo '<h1>' . __( 'Filters' ) . '</h2>';
	calendar_widget();

	echo '<div id="filters" class="center"><form>';
	echo '<input type="hidden" id="filter_date" name="filter_date" value="' . get_date_filter( $filters['yr'], $filters['mo'], isset( $filters['dy'] ) ? $filters['dy'] : false ) . '">';

		
	if( !$is_archive ) {
		echo '<h2>' . __( 'Content' ) . '</h2>';
		filter_select( 'resource' );

		echo '<h2>' . __( 'Visitors' ) . '</h2>';
		foreach( array( 'remote_ip', 'browser', 'platform', 'country', 'language' ) as $f )
			filter_select( $f );

		echo '<h2>' . __( 'Referrers' ) . '</h2>';
		foreach( array( 'search_terms', 'domain', 'referrer' ) as $f )
			filter_select( $f );
	}
	
	echo '<input class="hide-if-js" type="submit" value="' . __( 'Apply filters' ) . '">';

	echo '</form></div>';
	echo '</nav>';
}

function display_content(){
	global $date_label, $filters, $ss, $loaded_data, $is_archive, $field_names;
	
	echo '<div id="main">';
	$date_label = htmlspecialchars( date_label( $filters ) );
	
	echo "<h1>$date_label</h1>";
	
	if( $is_archive )
		echo '<p class="center">(' . __('aggregated') . ')</p>';

	if ( !$ss->options['stats_enabled'] )
		echo '<div id="disabled"><p class="center">' . __( 'Simple Stats is currently disabled.' ).'</p></div>';

	table_summary();
	
	if( !empty( $loaded_data['pages'] ) ) {
		echo '<h2>' . __( 'Content' ) . '</h2>';

		chart( isset( $filters['dy'] ) ? 'hours' : 'days' );
		table_total( 'resource', 'wide' );

		echo '<h2>Visitors</h2>';
		
		table_total( 'remote_ip' );
		table_percent( 'browser' );
		table_percent( 'platform' );

		if ( SimpleStats::is_geoip() )
			table_percent( 'country' );
		
		table_percent( 'language' );

		echo '<h2>Referrers</h2>';

		table_total( 'search_terms' );
		table_total( 'domain' );
		table_total( 'referrer', 'wide' );
		visit_source();
	
	}
	else {
		echo '<p class="center">' . __( 'There is no data available for the date/filters you have selected.' );
	}
	echo '</div>';
}

function filter_select( $id ) {
	global $filters, $loaded_data, $field_names;
	
	$title = $field_names[$id];
	// make sure we're looking in the right place
	if( $id == 'resource' ) 
		$data = &$loaded_data['pages'];
	else
		$data = &$loaded_data['visits'][$id];
	
	$data = (array) $data;
	
	$active = isset( $filters[$id] );
	$box = $active ? '<a class="clear-filter hide-if-no-js">&#215;</a> ' : '';
	echo "<p>$box<select name='filter_$id'>";
	echo "<option value='0' class='first'>— $title</option>";
	
	if ( $active ) {
		$new_filters = $filters;
		unset( $new_filters[$id] );
		$unfiltered_data = load_data( $new_filters );
		
		// keep only the bit we need
		if( $id == 'resource' ) 
			$unfiltered_data = $unfiltered_data['pages'];
		else
			$unfiltered_data = $unfiltered_data['visits'][$id];
		
		$x = 0;
		foreach ( array_keys( $unfiltered_data ) as $value ) {
			$selected = ( $value == $filters[$id] ) ? 'selected' : '';
			
			if( $value == '' )
				$label = __( 'Unknown' );
			elseif( $id == 'country' )
				$label = country_name( $value );
			else 
				$label = $value;
			
			$value = htmlspecialchars( $value );
			$label = htmlspecialchars( $label );
			echo "<option value='$value' $selected class='activefilter'>$label</option>";

			$x++;
			if ( $x == 50 ) 
				break;
		}
	} else {
		$x = 0;
		foreach ( array_keys( $data ) as $value ) {
			if( $value == '' )
				$label = __( 'Unknown' );
			elseif( $id == 'country' )
				$label = country_name( $value );
			else 
				$label = $value;
			
			$value = htmlspecialchars( $value );
			$label = htmlspecialchars( $label );
			echo "<option value='$value'>$label</option>";
			$x++;
			if ( $x == 50 ) { break; }
		}
	}	
	echo '</select></p>';
}

function table_summary() {
	global $loaded_data;
	
	// show empty summary if we have no data for this period
	if( empty( $loaded_data['pages'] ) ) {
		$hits = $visits = $ips = '0';
	}
	else {
		$hits = format_number( array_sum( $loaded_data['pages'] ), 0 );	// total page hits
		$visits = format_number( array_sum( $loaded_data['visits']['remote_ip'] ), 0 );
		$ips = format_number( sizeof( $loaded_data['visits']['remote_ip'] ), 0 );
	}
	
	echo '<table class="wide center"><thead><tr><th colspan="3">Summary<tbody><tr>';
	echo "<td width='33%'>$hits " . __( 'Hits' );
	echo "<td width='33%'>$visits " . __( 'Visits' );
	echo "<td width='33%'>$ips " . __( 'Unique IPs' );
	echo '</table>';
}

function table_total( $id, $format = 'narrow' ) {
	global $filters, $loaded_data, $date_label, $field_names;
	
	// only show the table if the field isn't being filtered
	if( isset( $filters[$id] ) )
		return;
	
	$title = $field_names[$id];
	if( $id == 'resource' )
		$data = &$loaded_data['pages'];
	else
		$data = &$loaded_data['visits'][$id];
	
	$data = (array) $data;	// in case it's empty
	
	arsort( $data );
	
	$new_filters = $filters;
	$max_rows = 50;
	
	$size = sizeof( $data );
	if( isset( $data[''] ) )
		$size --;	// don't count empty values
	
	echo "<div class='tablewrap $format'>";	// we have to do this to allow scrolling :(
	echo "<table><thead><tr><th>$title ($size)<th>" . __( 'Hits' ) . '<tbody>';
	
	$pos = 0;
	foreach ( $data as $key => $hits ) {
		if( $key == '' )
			continue;
		
		$new_filters[$id] = $key;
		
		echo '<tr><td>';
		if ( $id == 'referrer' ) {
			echo '<a class="goto" title="' . __('Visit this referrer page') . '" href="' . htmlspecialchars( $key ) . '" rel="external nofollow noreferrer">&rarr;</a> ';
		}

		echo filter_link( $new_filters, $key );
		echo "<td class='center' title='$date_label'>".format_number( $hits, 0 );
		
		$pos++;
		if ( $pos >= $max_rows ) 
			break;
	}
	
	echo '</table></div>';
}

function table_percent( $id, $format = 'narrow') {
	global $filters, $loaded_data, $date_label, $field_names;
	
	if( isset( $filters[$id] ) )
		return;
	
	if( $id == 'resource' )
		$data = &$loaded_data['pages'];
	else if( $id == 'source' )
		$data = &$loaded_data['visits']['source'];
	else
		$data = &$loaded_data['visits'][$id];
	
	if( empty( $data ) )
		return;
	
	arsort( $data );
	$new_filters = $filters;
	$max_rows = 50;
	
	$size = sizeof( $data );
	$total = array_sum( $data );
	
	echo "<div class='tablewrap $format'>";	// we have to do this to allow scrolling :(
	echo "<table><thead><tr><th>{$field_names[$id]} ($size)<th>%<tbody>";
	
	$pos = 0;
	foreach ( $data as $key => $hits ) {
		$new_filters[$id] = $key;
		
		$pct = $total ? ( $hits / $total * 100 ) : 0;
		
		echo '<tr><td>';
		if ( $id == 'browser'  ) {
			echo '<a class="toggle" title="" id="browser_'.preg_replace( '/[^a-z]/', '', strtolower( $key ) );
			echo '" href="#">+</a> ';
		}
		
		if( $id == 'country' )
			$label = country_name( $key );
		else 
			$label = $key;
		
		echo filter_link( $new_filters, ( $key == '' ) ? __( 'Unknown' ) : $label );
		
		echo '<td class="center" title="'.$date_label.'">'.format_number( $pct );
			
		if ( $id == 'browser' && $key != '' && ( isset( $loaded_data['visits']['version'][$key] ) ) ) {
			foreach ( $loaded_data['visits']['version'][$key] as $key2 => $hits2 ) {
				$pct = ( $total > 0 ) ? $hits2 / $total * 100 : 0;
				echo '<tr class="detail detail_browser_'.preg_replace( '/[^a-z]/', '', strtolower( $key ) ).'">';
				echo '<td>' . htmlspecialchars( $key2 );
				echo '<td class="center">'.format_number( $pct );
			}
		}
		
		$pos++;
		if ( $pos >= $max_rows ) break;
	}
	
	echo '</table></div>';
}

function chart( $what = 'days' ) {
	global $filters, $loaded_data;

	$visits = array();
	$hits = array();
	
	if( $what == 'days' ) {
		$x_max = days_in_month( $filters['mo'], $filters['yr'] );
		
		for( $d = 1; $d <= $x_max; $d++ )
			$visits[$d] = $hits[$d] = 0;
			
		foreach( $loaded_data['visits']['date'] as $ts => $data ) {
			$dn = intval( substr( $ts, -2 ) );
			$visits[ $dn ] += $data['visits'];
			$hits[ $dn ] += $data['hits'];
		}
		
		$per = __( 'day' );
	}
	else if ( $what == 'hours' ) {
		for( $h = 0; $h <= 24; $h++ )
			$visits[$h] = $hits[$h] = 0;

		foreach( $loaded_data['visits']['start_time'] as $ts => $data ) {
			$hn = intval( substr( $ts, 0, 2 ) );
			$visits[ $hn ] += $data['visits'];
			$hits[ $hn ] += $data['hits'];
		}

		$per = __( 'hour' );
	}
	
	$vtitle = htmlspecialchars( __( 'Visits' ) . '/' . $per );
	$htitle = htmlspecialchars( __( 'Hits' ) . '/' . $per );
	
	echo '<div class="hide-if-no-js">';
	echo '<h4 id="chart-title">' . $vtitle . '</h4>';
	echo '<div class="wide" id="chart" style="height: 180px; border:none"></div>';
	echo '<div id="chartopt"><small>' . __( 'Show:' ) . ' <a class="ajax" data:show="h">' . __( 'hits' ) . '</a> | <a class="ajax current" data:show="v">' . __( 'visits' ) . '</a></small></div>';
	echo '</div>';
	
	// send the data as a hidden table which we'll parse using JS
	echo "<table id='chart-data' style='display:none' data:vtitle='$vtitle' data:htitle='$htitle'>";
	foreach( $visits as $x => $y )
		echo "<tr><th>$x<td class='v'>$y<td class='h'>{$hits[$x]}";
	echo '</table>';
}

function visit_source() {
	global $filters, $loaded_data, $field_names;
	
	if( isset( $filters['domain'] ) || isset( $filters['search_terms'] ) || isset( $filters['referrer'] ) )
		return;
	
	$sources = &$loaded_data['visits']['source'];
	$total = array_sum( $sources );
	
	$data = array(
		__( 'Direct' ) => format_number( 100 * $sources['direct'] / $total ),
		__( 'Referral' ) => format_number( 100 * $sources['referrer'] / $total ),
		__( 'Search' ) => format_number( 100 * $sources['search_terms'] / $total )
	);
	
	arsort( $data );
	
	echo '<div class="tablewrap narrow">';
	echo '<table><thead><tr><th>' . $field_names['source'] . '<th>%';
	foreach( $data as $k => $v )
		echo '<tr><td>' . htmlspecialchars( $k ) . '<td class="center">' . $v;
	echo '</table></div>';
}

function calendar_widget() {
	global $filters, $is_archive;
	
	$start_offset = gmdate( 'w', gmmktime( 12, 0, 0, $filters['mo'], 1, $filters['yr'] ) );
	$days_in_month = days_in_month( $filters['mo'], $filters['yr'] );
	$table = array();
	for ( $d = 1; $d <= $days_in_month; $d++ ) {
		$this_w = intval( floor( ( $d + $start_offset - 1 ) / 7 ) );
		$target_w = $this_w /*% 5*/;
		if ( !isset( $table[$target_w] ) ) {
			$table[ $target_w ] = array();
			for ( $x = 0; $x < 7; $x++ )
				$table[ $target_w ][ $x ] = 0;
		}
		$table[ $target_w ][ $d + $start_offset - 1 - ( $this_w * 7 ) ] = $d;
	}
	
	$prev = prev_period( $filters, true );
	$prev_link = '<a href="./'.filter_url( $prev ).'" title="'.date_label( $prev, false ).'">&larr;</a>';
	
	if ( $filters['yr'] < date( 'Y' ) || $filters['mo'] < date( 'n' ) ) {
		$next = next_period( $filters, true );
		$next_link = '<a href="./'.filter_url( $next ).'" title="'.date_label( $next, false ).'">&rarr;</a>';
	} else {
		$next_link = '';
	}
	
	echo '<table class="calendar center"><thead>';
	echo '<tr>';
	echo "<th>$prev_link";
	echo '<th colspan="5"><a class="thismonth" href="./' . filter_url( next_period( $prev ) ) . '" title="' . date_label( $filters, false ).'">' . date_label( $filters, false ) . '</a>';
	echo "<th>$next_link";
	
	if( $is_archive ) {
		echo '</table>';
		return;
	}
	
	echo '<tbody>';
	echo '<tr>';
	foreach ( array( __( 'Sunday' ), __( 'Monday' ), __( 'Tuesday' ), __( 'Wednesday' ), __( 'Thursday' ), __( 'Friday' ), __( 'Saturday' ) ) as $day ) {
		$day = htmlspecialchars( $day );
		$d = substr( $day, 0, 1 );
		echo "<th title='$day'>$d";
	}
	
	$actual_dy = intval( date( 'd' ) );
	$actual_mo = intval( date( 'm' ) );
	$actual_yr = intval( date( 'Y' ) );
	
	$dy_filters = $filters;
	for ( $w = 0; $w < sizeof( $table ); $w++ ) {
		echo '<tr>';
		for ( $d = 0; $d < 7; $d++ ) {
			if ( $table[$w][$d] == 0 )
				echo '<td>';
			elseif ( isset( $filters['dy'] ) && $filters['dy'] == $table[$w][$d] )
				echo '<td class="selected">';
			else
				echo '<td class="dy">';

			if ( $table[$w][$d] > 0 ) {
				if ( $filters['yr'] == $actual_yr && $filters['mo'] == $actual_mo && $table[$w][$d] > $actual_dy ) {
					echo '<a class="future">' . $table[$w][$d] . '</a>';
				} else {
					$dy_filters['dy'] = $table[$w][$d];
					echo '<a href="./'.filter_url( $dy_filters ).'" title="';
					echo date_label( $filters, $table[$w][$d] ).'">'.$table[$w][$d].'</a>';
				}
			}
		}
	}
	echo '</table>';
}

