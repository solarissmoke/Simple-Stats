<?php
function checked( $v1, $v2 = true ) {
	return ( $v1 == $v2 ) ? ' checked ' : '';
}

function selected( $v1, $v2 = true ) {
	return ( $v1 == $v2 ) ? ' selected ' : '';
}

function tz_options( $selected_zone ) {
	$opts = array();
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

	if( isset( $_POST['update_options'] ) ) {
		foreach( array( 'stats_enabled', 'log_user_agents', 'log_bots' ) as $bool )
			$options[$bool] = isset( $_POST[$bool] );
		
		foreach( array( 'site_name', 'username', 'tz', 'lang' ) as $text )
			$options[$text] = $_POST[$text];

		$pw = trim( $_POST['password'] );
		if( $pw )	// password has been set/changed
			$options['password'] = $ss->hash( $pw );
			
		$options['aggregate_after'] = intval( $_POST['aggregate_after'] );
		
		// set login option
		$options['login_required'] = false;
		if( isset( $_POST['login_required'] ) )
			$options['login_required'] = 'all';
		elseif( isset( $_POST['login_required_config'] ) )
			$options['login_required'] = 'config';
			
		$ips = explode( "\n", str_replace( "\r\n", "\n", $_POST['ignored_ips'] ) );
		$options['ignored_ips'] = array();
		// we don't check the validity of IPs
		foreach( $ips as $ip ) {
			$ip = trim( $ip );
			if( $ip )
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
	
	echo '<form action="?p=options" method="post" id="options-form">';
	
	if( defined( 'SIMPLE_STATS_PASSWORD_RESET' ) && SIMPLE_STATS_PASSWORD_RESET ) 
		echo '<p style="color:red">' . __( 'Your Simple Stats username and password have been reset because you defined the constant <code>SIMPLE_STATS_PASSWORD_RESET</code> in your <code>config.php</code> file. Please remove that definition in order to set new login credentials.' );
	
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
		'login_required_config' => array(
			__( 'Require login to edit configuration' ) . '<br><small>' . __( 'Visits from users logged in to Simple Stats will not be recorded.' ) . '</small>',
			'<input type="checkbox" id="login_required_config" name="login_required_config" value="1" ' . checked( $options['login_required'] ) . '>'
		),
		'login_required' => array(
			__( 'Require login to view statistics' ),
			'<input type="checkbox" id="login_required" name="login_required" value="1" ' . checked( $options['login_required'], 'all' ) . '>'
		),
		'username' => array(
			__( 'Username for Simple Stats login' ),
			'<input type="text" id="username" name="username" value="' . htmlspecialchars( $options['username'] ) . '">'
		),
		'password' => array(
			__( 'Password for Simple Stats login' ) . ( $options['password'] ? ' <small class="hide-if-js">' . __( '(saved, click to change)' ) . '</small>' : '' ),
			'<input type="password" id="password" name="password" value="">' . ( $options['password'] ? '<input type="hidden" id="saved_password_exists" value="1">' : '' )
		),
		'tz' => array(
			__( 'Time zone' ),
			'<select id="tz" name="tz">' . tz_options( $options['tz'] ) . '</select>'
		),
		'lang' => array(
			__( 'Language' ) . '<br><small>' . __( 'Please enter your language identifier in the form "en-gb".' ) . '</small>',
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
			__( 'Ignore these IP addresses:' . '<br><small>' . __( 'Please enter one IP address per line.' ) . '</small>' ),
			'<textarea id="ignored_ips" name="ignored_ips">' . implode( "\n", $options['ignored_ips'] ) . '</textarea>'
		),
		'aggregate_after' => array(
			__( 'Aggregate data after:' ) . '<br><small>' . __( 'Aggregrating data will significantly reduce the size of the database, but will mean that you can only view summarised data for each month.' ) . '</small>',
			'<select type="checkbox" id="aggregate_after" name="aggregate_after">'.
			'<option value="0"' . selected($options['aggregate_after'], 0) . '>' . __('never aggregate data') . '</option>'.
			'<option value="3"' . selected($options['aggregate_after'], 3) . '>3 ' . __('months') . '</option>'.
			'<option value="6"' . selected($options['aggregate_after'], 6) . '>6 ' . __('months') . '</option>'.
			'<option value="9"' . selected($options['aggregate_after'], 9) . '>9 ' . __('months') . '</option>'.
			'<option value="12"' . selected($options['aggregate_after'], 12) . '>12 ' . __('months') . '</option>'.
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
	
	echo '</form></div>'; //main
	?>
	<script>
	$(document).ready( function(){
		$(".hide-if-js").hide();

		var needs_password = false;
		$("#login_required, #login_required_config").change( function(){
			var uprow = $("#username, #password").closest("tr");
			if( $("#login_required").is(":checked") || $("#login_required_config").is(":checked") ) {
				uprow.fadeIn();
				needs_password = true;
			}
			else {
				uprow.fadeOut();
				needs_password = false;
			}
		});
		$("#login_required, #login_required_config").change();

		var pw = $("#password"), is_saved = $("#saved_password_exists").val();
		if( is_saved ) {
			var ph = $( '<a><?php echo htmlspecialchars( __( '(saved, click to change)' ) );?></a>' );
			pw.hide().after( ph );
			ph.click( function(){
				ph.hide();
				pw.show().focus();
			});
			pw.blur( function() {
				if( !pw.val() ) {
					pw.hide();
					ph.show();
				}
			});
		};
		
		$("#options-form").submit( function(e){
			if( needs_password && ( ! $("#username").val() || ( ! pw.val() && ! is_saved ) ) ) {
				e.preventDefault();
				alert( "<?php echo htmlspecialchars( __( 'You have not supplied a username and/or password. Please enter a username and password to enable restricted access.' ) );?>" );
			}
		});
	});
	</script>
	<?php
	page_foot(); 
}

