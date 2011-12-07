<?php
function render_page() {
	global $ss;
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

	echo '<table id="paths" class="center wide"><thead>';
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
		
		$prev_time = '';
		foreach ( $hits as $hit ) {
			if ( empty( $hit ) )
				continue;
			
			@list( $time, $resource ) = explode( ' ', $hit, 2 );
			
			// dump the seconds part of the time
			$time = substr( $time, 0, 5 );
			
			$r = htmlspecialchars( $resource );
			
			echo '<tr>';
			echo "<td colspan='2' class='left'><span><a href='$r' title='$r' class='goto' target='_blank'>&rarr;</a> " . filter_link( array( 'resource' => $resource ), $resource ) . "</span>";

			echo ( $time != $prev_time ) ? "<td>$time</td>" : '<td></td>';
			
			if ( empty( $prev_time ) && ! empty( $visit['referrer'] ) ) {
				$r = htmlspecialchars( $visit['referrer'] );
				echo '<td colspan="3" class="right"><a href="'.$r.'" rel="nofollow" class="goto" title="'.$r.'">&rarr;</a> ';
				echo '<span>';
				if ( !empty( $visit['search_terms'] ) ) {
					echo filter_link( array( 'search_terms' => $visit['search_terms'] ), $visit['search_terms'] );
				} else {
					echo filter_link( array( 'domain' => $visit['domain'] ), $visit['domain'] );
				}
				echo '</span></td>';
			} else {
				echo '<td colspan="3">&nbsp;</td>';
			}
			echo '</tr>';
			
			$prev_time = $time;
		}
	}

	echo '</table>';
	echo '<nav class="center wide"><a class="ajax" style="display:block; padding: 5px" id="load-more">— ' . __( 'more' ) . ' —</a></nav>';

	echo '</div>';

	?>
	<script>
	$(document).ready( function(){
		var offset = <?php echo $offset; ?>;
		var loading = false;
		var lm = $("#load-more"), text = lm.text();
		lm.click( function(e){ 
			e.preventDefault();
			offset += <?php echo $page_size; ?>;
			if( loading )
				return;
			loading = true;
			lm.text('\u00A0');	// nbsp
			var spinner = new Spinner({ lines: 10, length: 5, width: 2, radius: 4, color: '#000', speed: 1, trail: 60, shadow: false}).spin(lm[0]);
			$.get('./?p=paths&offset=' + offset, function(data) {
				loading = false;
				lm.empty().text(text);
				var rows = $(data).find('#paths tbody').children();
				if( rows.length )
					$('#paths tbody').append(rows.fadeIn(1000));
				else 
					$("#load-more").text("<?php echo __( 'No more data to show' );?>").parent().delay(2000).fadeOut(2000);
			});
		});
	});
	</script>
	<?php
	page_foot();
}