<?php
function render_page() {
	global $ss;
	scripts_i18n();
	page_head();

	echo '<div id="main">';
	echo '<h2>' . __( 'Latest visitors' ).'</h2>';

	$page_size = 20;
	$offset = isset( $_GET['offset'] ) ?  abs( intval( $_GET['offset'] ) ) : 0;

	$query = "SELECT * FROM `{$ss->tables['visits']}` ORDER BY `date` DESC, `start_time` DESC LIMIT $offset,$page_size";

	$visits = array();
	if ( $result = $ss->query( $query ) ) {
		while ( $assoc = @mysql_fetch_assoc( $result ) ) {
			$visits[] = $assoc;
		}
	}

	echo '<table id="paths" class="center wide" data:offset="' . $offset . '" data:page_size="' . $page_size . '"><thead>';
	echo '<tr><th colspan="2" class="left">' . __( 'IP Address' ) . '/' . __( 'Pages' );
	echo '<th>' . __( 'Time' );
	echo '<th class="nb">' . __( 'Browser' );
	echo '<th class="nb">' . __( 'Operating system' );
	echo '<th class="nb">' . __( 'Country' );
	echo '<tbody>';

	foreach ( $visits as $visit ) {
		$start_ts = $ss->sql2time( $visit['date'] . ' ' . $visit['start_time'] );
	
		$hits = explode( "\n", $visit['resource'] );
		
		$dy_label = strftime( '%d %b', $start_ts );	
		$start_ts = date( 'H:i', $start_ts );

		
		echo '<tr class="visit-header">';
		echo '<td colspan="2" class="left">' . htmlspecialchars( $visit['remote_ip'] );
		echo '<td>'.$dy_label.'</td>';
		echo '<td class="nb">' . htmlspecialchars( $visit['browser'] ) . ' ' . htmlspecialchars( $visit['version'] );
		echo '<td class="nb">' . htmlspecialchars( $visit['platform'] );
		echo '<td class="nb">' . htmlspecialchars( country_name( $visit['country'] ) ) . '</tr>';
		
		if( $ss->options['log_user_agents'] && !empty( $visit['user_agent'] ) ) {
			echo '<tr class="visit-header"><td colspan="6" class="left"><small>' . htmlspecialchars( $visit['user_agent'] ) . '</small>';
		}
		
		$row = 0;
		foreach ( $hits as $hit ) {
			if ( empty( $hit ) )
				continue;
			
			@list( $time, $resource ) = explode( ' ', $hit, 2 );
			
			// dump the seconds part of the time
			$time = substr( $time, 0, 5 );
			
			$r = htmlspecialchars( $resource );
			
			echo '<tr>';
			echo '<td colspan="2" class="left"><a href="' . $r . '" class="goto">&rarr;</a> ' . filter_link( array( 'resource' => $resource ), $resource ) . "<td>$time";
			
			if ( $row == 0 && ! empty( $visit['referrer'] ) ) {
				echo '<td colspan="3" class="right">';
				if ( !empty( $visit['search_terms'] ) )
					echo filter_link( array( 'search_terms' => $visit['search_terms'] ), $visit['search_terms'] );
				else
					echo filter_link( array( 'domain' => $visit['domain'] ), $visit['domain'] );
				echo ' <a href="' . htmlspecialchars( $visit['referrer'] ) . '" rel="external noreferrer" class="goto ext">&rarr;</a>';
			} else {
				echo '<td colspan="3">&nbsp;';
			}
			echo '</tr>';
			
			$row ++;
		}
	}

	echo '</table>';
	echo '<nav class="center wide hide-if-no-js"><a class="ajax" style="display:block; padding: 5px" id="load-more">— ' . __( 'more' ) . ' —</a></nav>';
	echo '</div>';
	page_foot();
}