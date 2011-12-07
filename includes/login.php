<?php
function render_page() {
	page_head();	
?>
<div id="main">
<h2><?php echo __( 'Login' ); ?></h2>
<form action="./" method="post">
<p class="center"><?php echo __( 'Please login to access Simple Stats' ); ?>
<table>
<tr><th><label for="username"><?php echo __( 'User name' ); ?></label><td><input type="text" name="username" value="" tabindex="1">
<tr><th><label for="password"><?php echo __( 'Password' ); ?></label><td><input type="password" name="password" value="" tabindex="2">
</table>
<p class="center"><input type="submit" value="<?php echo __( 'Submit' ); ?>" tabindex="3">
</form>
</div>
<?php
	page_foot();
}