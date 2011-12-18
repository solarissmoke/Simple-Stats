<?php
function render_page() {
	global $ss;
	$origin = ( isset( $_GET['origin'] ) && $_GET['origin'] != 'overview' ) ? $_GET['origin'] : '';
	$failed_login = false;
	
	// process login request
	if( isset( $_POST['simple-stats-login'] ) ) {
		if( $_POST['username'] == $ss->options['username'] && $ss->hash( trim( $_POST['password'] ) ) == $ss->options['password'] ) {
			@setcookie( 'simple_stats', $ss->hash( $ss->options['username'] . $ss->options['password'] ), time() + 31536000, '/', '' );
			header( 'Location: ./' . ( $origin ? './?p=' .  $origin : '' ), true, 302 );
			exit;
		}
		else {
			$failed_login = true;
		}
	}
	
	page_head();	
?>
<div id="main">
<?php 
if( $origin == 'logout' ) {
	echo '<p>You have logged out of Simple Stats. You can <a href="./?p=login">login again</a> if you like.';
}
else {
?>
<h2><?php echo __( 'Login' ); ?></h2>
<?php if( $failed_login ) echo '<p class="center">' . __( 'Sorry, the username and password combination that you entered was incorrect.' );?>
<form action="./?p=login&amp;origin=<?php echo $origin;?>" method="post">
<table>
<tr><th><label for="username"><?php echo __( 'User name' ); ?></label><td><input type="text" name="username" value="" tabindex="1">
<tr><th><label for="password"><?php echo __( 'Password' ); ?></label><td><input type="password" name="password" value="" tabindex="2">
</table>
<p class="center"><input type="submit" name="simple-stats-login" value="<?php echo __( 'Submit' ); ?>" tabindex="3">
</form>
<?php 
}
?>
</div>
<?php
	page_foot();
}