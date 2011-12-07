<?php
function checked( $v1, $v2 = true ) {
	if( $v1 == $v2 )
		return ' checked ';
	return '';
}

function selected( $v1, $v2 = true ) {
	if( $v1 == $v2 )
		return ' selected ';
	return '';
}

function tz_options( $selected_zone ) {
	$opts = array();

	if ( empty( $selected_zone ) )
		$opts[] = '<option selected value="">Select a timezone</option>';

	$selected = '';
	if ( 'UTC' === $selected_zone )
		$selected = 'selected ';
	$opts[] = "<option $selected value='UTC'></option>";
	foreach ( timezone_identifiers_list() as $i ) {
		$selected = ( $i === $selected_zone ) ? 'selected ' : '';
		$opts[] = "<option $selected value='$i'>$i</option>";
	}

	return implode( '', $opts );
}



function render_page() {
	global $ss;
	$options = $ss->options;
	
	if( isset($_POST['update_options'] ) ) {
		foreach( array( 'stats_enabled', 'login_required', 'log_user_agents', 'log_bots' ) as $bool )
			$options[$bool] = isset( $_POST[$bool] );
		
		foreach( array( 'site_name', 'username', 'password', 'tz', 'lang' ) as $text )
			$options[$text] = $_POST[$text];
			
		$options['aggregate_after'] = intval( $_POST['aggregate_after'] );
			
		$ips = explode( "\n", str_replace( "\r\n", "\n", $_POST['ignored_ips'] ) );
		$options['ignored_ips'] = array();
		foreach( $ips as $ip ) {
			$ip = trim( $ip );
			if( preg_match( '/^\d+\.\d+\.\d+\.\d+$/', $ip ) )
				$options['ignored_ips'][] = $ip;
		}
		
		foreach( $options as $option => $value )
			$ss->update_option( $option, $value );
	}

	page_head();
	echo '<div id="main">';
	echo '<h2>' . __( 'Configuration' ) . '</h2>';
	echo '<p> ' . __( 'In order for Simple Stats to record hits on your site, you need to include the <code>stats-include.php</code> in your site\' code. Insert the following code where it will be run on every page load:' );
	echo ' <code>' . htmlspecialchars( '<?php @include_once( \'' . str_replace( '\\', '/', SIMPLE_STATS_PATH ) . '/stats-include.php\' ); ?>' ) .'</code>';
	
	echo '<form action="?p=options" method="post">';
	
	$fields = array(
		// option_name => array( label, input )
		'stats_enabled' => array( 
			__( 'Enable Simple Stats' ), 
			'<input type="checkbox" id="stats_enabled" name="stats_enabled" value="1" ' . checked( $options['stats_enabled'] ) . '>'
		),
		'site_name' => array(
			__( 'Site name' ),
			'<input type="text" id="site_name" name="site_name" value="' . htmlspecialchars( $options['site_name'] ) . '">'
		),
		'login_required' => array(
			__( 'Require login to view statistics' ) . '<br><small>' . __( 'Visits from users logged in to Simple Stats will not be recorded.' ) . '</small>',
			'<input type="checkbox" id="login_required" name="login_required" value="1" ' . checked( $options['login_required'] ) . '>'
		),
		'username' => array(
			__( 'Username for Simple Stats login' ),
			'<input type="text" id="username" name="username" value="' . htmlspecialchars( $options['username'] ) . '">'
		),
		'password' => array(
			__( 'Password for Simple Stats login' ),
			'<input type="password" id="password" name="password" value="' . htmlspecialchars( $options['password'] ) . '">'
		),
		'tz' => array(
			__( 'Time zone' ),
			'<select id="tz" name="tz">' . tz_options( $options['tz'] ) . '</select>'
		),
		'lang' => array(
			__( 'Language' ) . '<br><small>' . __( 'Please enter your language identifier in the form "en-gb"' ) . '</small>',
			'<input type="text" id="lang" name="lang" maxlength="5" size="5" value="' . $options['lang'] . '">'
		),
		'log_user_agents' => array(
			__( 'Log full user agent string' ) . '<br><small>' . __( 'Operating system, browser and version will always be recorded. Selecting this option will considerably increase the size of the Simple Stats database.' ) . '</small>', 
			'<input type="checkbox" id="log_user_agents" name="log_user_agents" value="1" ' . checked( $options['log_user_agents'] ) . '>'
		),
		'log_bots' => array(
			__( 'Log visits from robots' ),
			'<input type="checkbox" id="log_bots" name="log_bots" value="1" ' . checked( $options['log_bots'] ) . '>'
		),
		'ignored_ips' => array( 
			__( 'Ignore these IP addresses:' ),
			'<textarea id="ignored_ips" name="ignored_ips">' . implode( "\n", $options['ignored_ips'] ) . '</textarea>'
		),
		'aggregate_after' => array(
			__( 'Aggregate data after:' ) . '<br><small>' . __( 'Aggregrating data will significantly reduce the sixe of the database, but will mean that you can only view summarised data for each month.' ) . '</small>',
			'<select type="checkbox" id="aggregate_after" name="aggregate_after">'.
			'<option value="0"' . selected($options['aggregate_after'], 0) . '>' . __('never aggregate data') . '</option>'.
			'<option value="3"' . selected($options['aggregate_after'], 3) . '>3 ' . __('months') . '</option>'.
			'<option value="6"' . selected($options['aggregate_after'], 6) . '>6 ' . __('months') . '</option>'.
			'<option value="6"' . selected($options['aggregate_after'], 9) . '>9 ' . __('months') . '</option>'.
			'<option value="6"' . selected($options['aggregate_after'], 12) . '>12 ' . __('months') . '</option>'.
			'</select>'
		),
	);
	
	echo '<table class="wide"><tbody>';
	
	foreach( $fields as $option => $v ) {
		$name =  $v[0];
		$input = $v[1];
		echo "<tr><th class='left'><label for='$option'>$name</label><td class='center'>$input";
	}
	echo '</table>';
	
	echo '<p class="center"><input type="submit" name="update_options" value="' . __( 'Update options' ) . '">';
	
	echo '</div>'; //main
	?>
	<script>
	$(document).ready( function(){
		$("#login_required").change( function(){
			var up = $("#username, #password, #ignore_logged_in").closest("tr");
			if( $(this).is(":checked") )
				up.fadeIn();
			else 
				up.fadeOut();
		});
		$("#login_required").change();
	});
	</script>
	<?php
	page_foot(); 
}

