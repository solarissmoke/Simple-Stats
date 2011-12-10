<?php 
@header( 'Content-Type: text/html; charset=UTF-8' ); 
$title = $ss->is_installed() ? __( 'Simple Stats' ) . ( $ss->options['site_name'] ? ' ' . __( 'for' ) . ' ' . $ss->options['site_name'] : '' ) : 'Simple Stats';
$title = htmlspecialchars( $title );
?>
<!DOCTYPE html><html>
<head>
	<?php if( ! $ajax ) { ?>
		<meta name="robots" content="noindex,nofollow">
		<title><?php echo $title;?></title><link rel="stylesheet" href="css/main.css">
		<!--[if lt IE 9]><script src="js/html5.js"></script><![endif]-->
		<script src="js/jquery.js"></script>
		<?php 
		if ( $page == 'overview' || $page == 'paths' ) {
			echo '<script src="js/spin.min.js"></script>';
		}
		if ( $page == 'overview' ) {
			echo '<script src="js/jquery.flot.min.js"></script>';
			echo '<script src="js/overview.js"></script>';
		}
	}
	?>
<body id="<?php echo $page; ?>page">
<div id="wrap">
<?php
if( !$ajax ) {
	echo "<header><h1><a href='./'>$title</a></h1>";
	if( $ss->is_installed() ) {
		echo '<nav id="menu"><ul>';
		if ( is_logged_in() )
			echo '<li><a href="?p=logout">Logout</a></li>';

		if( $ss->options['login_required'] != 'all' || is_logged_in() ) {
			echo '<li><a href="./" ' . ( $page == 'overview' ? 'class="current"' : '' ) . '>' . __( 'Overview' ) . '</a>';
			echo '<li><a href="./?p=paths" ' . ( $page == 'paths' ? 'class="current"' : '' ) . '>' . __( 'Latest visits' ) . '</a>';
			echo '<li><a href="./?p=options" ' . ( $page == 'options' ? 'class="current"' : '' ) . '>' . __( 'Configuration' ) . '</a>';
		}

		echo '</ul></nav>';
	}
	echo '</header>';
}