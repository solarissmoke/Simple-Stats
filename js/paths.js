$(document).ready( function(){
	$(".hide-if-no-js").show();
	var table = $("#paths");
	var offset = parseInt( table.attr("data:offset") ), page_size = parseInt( table.attr("data:page_size") )
	var loading = false, lm = $("#load-more"), text = lm.text();
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
				$("#load-more").text("No more data to show").parent().delay(1000).fadeOut(2000);
			onUpdate( rows );
		});
	});
	
	function UAShow(){
		var td = $(this);
		if( !td.attr("data:ua-visible") ){
			td.parent().after("<tr class='ua-row right'><td colspan='6'>" + td.attr("title") + "</tr>");
			td.attr("data:ua-visible", "1");
		}
	}

	function onUpdate( context ) {
		context.find("a.filter").attr("title", i18n.filter_title);
		context.find("a.goto").attr("title", i18n.link_title);
		context.find("a.ext").attr("title", i18n.ext_link_title);
	}

	onUpdate( table );
	$('#paths tbody').on( "click", "td.ua[title]", UAShow );
});
	