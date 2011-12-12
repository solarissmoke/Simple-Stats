$(document).ready( function(){
	$(".hide-if-no-js").show();
	var offset = parseInt( $("#paths").attr("data:offset") );
	var page_size = parseInt( $("#paths").attr("data:page_size") )
	var loading = false;
	var lm = $("#load-more"), text = lm.text();
	lm.click( function(e){ 
		e.preventDefault();
		offset += page_size;
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
	