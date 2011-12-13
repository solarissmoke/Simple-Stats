<?php if( !$ajax ) echo '<footer><a href="http://rayofsolaris.net/code/simple-stats">Simple Stats v' . SimpleStats::version . '</a>.<small> This product includes GeoLite data created by MaxMind, available from <a href="http://www.maxmind.com/" rel="external">maxmind.com</a>.</small></footer>';?>
</div><!--/container-->
<?php
if( !empty( $script_i18n ) ) {
	echo '<script>var i18n = ' . json_encode( $script_i18n ) . ';</script>'; 
}