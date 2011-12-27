<?php
if( !$ajax )
	echo '<footer><a href="http://rayofsolaris.net/code/simple-stats">Simple Stats v' . SimpleStats::version . '</a>' . ( SimpleStats::is_geoip() ? '<small> This product includes GeoLite data created by MaxMind, available from <a href="http://www.maxmind.com/" rel="external">maxmind.com</a>.</small>' : '' ) . '</footer>';
echo '</div>';	// container
if( !empty( $script_i18n ) ) {
	echo '<script>var i18n = ' . json_encode( $script_i18n ) . ';</script>'; 
}