<?php
class SimpleStatsI18n {
		
	function __construct() {
		
	}
	
	function label( $_field, $_key ) {
		if ( isset( $this->data['labels'][$_field.'.'.$_key] ) ) 
			return $this->data['labels'][$_field.'.'.$_key];
			
		if ( $_key == '' ) 
			return $this->data['core']['indeterminable'];
			
		if ( $_field == 'language' && mb_strlen( $_key ) == 5 ) {
			$language = mb_strtolower( mb_substr( $_key, 0, 2 ) );
			$country = mb_strtoupper( mb_substr( $_key, 3, 2 ) );
			
			if ( isset( $this->data['labels']['language.'.$language] ) ) {
				if ( isset( $this->data['labels']['country.'.$country] ) ) {
					return sprintf(
						$this->data['core']['language_country'],
					    $this->data['labels']['language.'.$language],
					    $this->data['labels']['country.'.$country] );
				}	
				return $this->data['labels']['language.'.$language];
			}
			return $_key;
		}
		return $_key;
	}
	
	function _( $_category, $_field, $_str='' ) {
		
	}
	
	function hsc( $_category, $_field, $_str='' ) {
		
	}
}
