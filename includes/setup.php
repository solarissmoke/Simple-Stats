<?php
$options_tbl_fields = array(
	'option'	=> 'VARCHAR(255) NOT NULL',
	'value'		=> 'TEXT NOT NULL'
);

$visits_tbl_fields = array(
	'remote_ip'      => 'VARCHAR(15) NOT NULL DEFAULT ""',
	'country'        => 'CHAR(2) NOT NULL DEFAULT ""',
	'language'       => 'VARCHAR(255) NOT NULL DEFAULT ""',
	'domain'         => 'VARCHAR(255) NOT NULL DEFAULT ""',
	'referrer'       => 'VARCHAR(255) NOT NULL DEFAULT ""',
	'search_terms'   => 'VARCHAR(255) NOT NULL DEFAULT ""',
	'user_agent'     => 'VARCHAR(255) NOT NULL DEFAULT ""',
	'platform'       => 'TINYINT(3) NOT NULL DEFAULT "0"',
	'browser'        => 'TINYINT(3) NOT NULL DEFAULT "0"',
	'version'        => 'VARCHAR(15) NOT NULL DEFAULT ""',
	'date'			=>  'DATE NOT NULL',
	'start_time'     => 'TIME NOT NULL',
	'end_time'       => 'TIME NOT NULL',
	'offset'		=> 'SMALLINT(4) NOT NULL',
	'hits'           => 'INT(10) UNSIGNED NOT NULL',
	'resource'       => 'TEXT'
);

$archive_tbl_fields = array(
	'yr'             => 'SMALLINT(4) UNSIGNED NOT NULL',
	'mo'             => 'TINYINT(2) UNSIGNED NOT NULL',
	'data'          => 'LONGBLOB NOT NULL'
);

function check_table_exists( $_table ) {
	global $ss;
	$result = $ss->query( "DESCRIBE `$_table`" );
	return ( @mysql_num_rows( $result ) > 0 );
}

function check_table_fields_exist( $_table, $_fields ) {
	global $ss;
	
	$missing_fields = array();

	$result = $ss->query( "DESCRIBE `$_table`" );

	if ( $result ) {
		$existing_fields = array();
		while ( $datum = @mysql_fetch_assoc( $result ) ) {
			$existing_fields[ $datum['Field'] ] = $datum;
		}
	
		foreach ( array_keys( $_fields ) as $field_name ) {
			if ( !isset( $existing_fields[$field_name] ) )
				$missing_fields[] = $field_name;
		}
	}
	
	return $missing_fields;
}

$step = -1;

page_head();
?>
<div id="main">
<h2>Setting up Simple Stats</h2>
<?php

$next_action = '';
$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

if ( !file_exists( SIMPLE_STATS_PATH . '/config.php' ) ) {
	$conf_file = str_replace( '\\', '/', SIMPLE_STATS_PATH . '/config-sample.php' );
	echo "<p>Simple Stats needs to be able to connect to your MySQL database. Please make a copy of the file <code>$conf_file</code> with your database connection credentials, and save it as <code>config.php</code>.<p>The sample configuration file contains instructions about what information is required.<p>When you have done this, click the button below.";
	$next_action = 'install_tables';
}
elseif( !$ss->connect() ) {
	echo '<p>Simple Stats was unable to connect to your database. Please verify that your database credentials in the configuration file are correct, and try again';
	$next_action = 'install_tables';
}
elseif( $action == '' ) {
	echo '<p>Simple Stats is ready to set up your database.<p>Click the button below to proceed.';
	$next_action = 'install_tables';
}
elseif( $action == 'install_tables' ) {
	// check tables
	$options_table_exists = check_table_exists( $ss->tables['options'] );
	$visits_table_exists = check_table_exists( $ss->tables['visits'] );
	$archive_table_exists = check_table_exists( $ss->tables['archive'] );
	
	$options_table_missing = ( $options_table_exists ) ? check_table_fields_exist( $ss->tables['options'] , $options_tbl_fields ) : array();
	$visits_table_missing = ( $visits_table_exists ) ? check_table_fields_exist( $ss->tables['visits'], $visits_tbl_fields ) : array();
	$archive_table_missing = ( $archive_table_exists ) ? check_table_fields_exist( $ss->tables['archive'], $archive_tbl_fields ) : array();
	
	$creating = ( !$options_table_exists || !$visits_table_exists || !$archive_table_exists ) ? 'tables' : 'fields';
		
	// try to create the tables
	$options_query = "CREATE TABLE `{$ss->tables['options']}` (";
	$bits = array();
	foreach ( $options_tbl_fields as $field_name => $field_type )
		$options_query .= "\n\t`$field_name` $field_type,";
	$options_query .= "\n\tUNIQUE KEY `option` (`option`)";
	$options_query .= "\n) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

	$visits_query = "CREATE TABLE `{$ss->tables['visits']}` (";
	foreach ( $visits_tbl_fields as $field_name => $field_type )
		$visits_query .= "\n\t`$field_name` $field_type,";
	$visits_query .= "\n\tKEY `date` (`date`)," .
		"\n\tKEY `ua` (`browser`, `platform`)," .
		"\n\tKEY `country` (`country`)" .
	"\n) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin";

	$archive_query = "CREATE TABLE `{$ss->tables['archive']}` (";
	foreach ( $archive_tbl_fields as $field_name => $field_type ) 
		$archive_query .= "\n\t`$field_name` $field_type,";
	$archive_query .= "\n\tUNIQUE KEY `date` (`yr`,`mo`)".
	"\n) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin";
		
	$options_alter_queries = array();
	foreach ( $options_table_missing as $field_name )
		$options_alter_queries[] = "ALTER TABLE `{$ss->tables['options']}` ADD `$field_name` {$options_tbl_fields[$field_name]}";
	
	$visits_alter_queries = array();
	foreach ( $visits_table_missing as $field_name )
		$visits_alter_queries[] = "ALTER TABLE `{$ss->tables['visits']}` ADD `$field_name` {$visits_tbl_fields[$field_name]}";
	
	$archive_alter_queries = array();
	foreach ( $archive_table_missing as $field_name )
		$archive_alter_queries[] = "ALTER TABLE `{$ss->tables['archive']}` ADD `$field_name` {$archive_tbl_fields[$field_name]}";
		
	if ( !$options_table_exists )
		$ss->query( $options_query );

	if ( !$visits_table_exists )
		$ss->query( $visits_query );

	if ( !$archive_table_exists )
		$ss->query( $archive_query );

	foreach ( $options_alter_queries as $options_alter_query )
		$ss->query( $options_alter_query );

	foreach ( $visits_alter_queries as $visits_alter_query )
		$ss->query( $visits_alter_query );

	foreach ( $archive_alter_queries as $archive_alter_query )
		$ss->query( $archive_alter_query );
					
	$options_table_exists = check_table_exists( $ss->tables['options']  );
	$visits_table_exists = check_table_exists( $ss->tables['visits'] );
	$archive_table_exists = check_table_exists( $ss->tables['archive'] );
	$options_table_missing = ( $options_table_exists ) ? check_table_fields_exist( $ss->tables['options'] , $options_tbl_fields ) : array();
	$visits_table_missing = ( $visits_table_exists ) ? check_table_fields_exist( $ss->tables['visits'], $visits_tbl_fields ) : array();
	$archive_table_missing = ( $archive_table_exists ) ? check_table_fields_exist( $ss->tables['archive'], $archive_tbl_fields ) : array();
		
	if ( $options_table_exists && $visits_table_exists && $archive_table_exists &&
		 empty( $options_table_missing ) && empty( $visits_table_missing ) && empty( $archive_table_missing ) ) {
		echo '<p>All required '.$creating.' have been created. Click the button below to continue to the next step.</p>'."\n";
	} 
	else {
		echo '<p>Simple Stats was unable to create the '.$creating.'. This is most likely because the MySQL user does not have permission to create '.$creating.'.<p>You will need to create the '.$creating.' yourself, by executing the following queries.';
		
		if ( !$options_table_exists )
			echo '<p>To create the options table:</p><pre>'.htmlspecialchars( $options_query ).';</pre>';
		if ( !$visits_table_exists )
			echo '<p>To create the visits table:</p><pre>'.htmlspecialchars( $visits_query ).';</pre>';
		if ( !$archive_table_exists )
			echo '<p>To create the archive table:</p><pre>'.htmlspecialchars( $archive_query ).';</pre>';

		if ( !empty( $options_table_missing ) ) {
			echo '<p>To alter the options table:</p><pre>';
			foreach ( $options_alter_queries as $options_alter_query )
				echo htmlspecialchars( $options_alter_query ).";\n";
			echo '</pre>';
		}
		if ( !empty( $visits_table_missing ) ) {
			echo '<p>To alter the visits table:</p><pre>';
			foreach ( $visits_alter_queries as $visits_alter_query ) {
				echo htmlspecialchars( $visits_alter_query ).";\n";
			}
			echo '</pre>';
		}
		if ( !empty( $archive_table_missing ) ) {
			echo '<p>To alter the archive table:</p><pre>';
			foreach ( $archive_alter_queries as $archive_alter_query ) {
				echo htmlspecialchars( $archive_alter_query ).";\n";
			}
			echo '</pre>';
		}	
	}
}

///////////////////////////////////////////////////////////// 'Next step' button

if ( $next_action ) {
	echo '<form method="post" action="?p=setup">';
	echo '<p>';
	if ( $next_action ) {
		echo "<input type='hidden' name='action' value='$next_action'>";
	}
	echo '<input type="submit" value="Next step" /></p>';
	echo '</form>';
} else {
	echo '<form action="./?p=options" method="post">';
	echo '<input type="hidden" name="action" value="complete_setup">';
	echo '<p><input type="submit" value="Finish" /></p>'."\n";
	echo '</form>'."\n";
}

?>
</div>

</div>
<?php

page_foot();