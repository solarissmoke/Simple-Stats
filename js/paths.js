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

	var tooltip = $("<div id='ua_tooltip' style='padding: 10px; box-shadow: #CCC 2px 2px 5px; border: 1px solid #CCC; border-radius: 5px; background: #FFF'></div>");
	var tooltipwrap = $("<div id='ua_tooltip_wrap' style='padding: 20px; position: absolute; z-index: 10'></div>").append( tooltip );
	tooltipwrap.mouseleave( function(){
		if( tooltipwrap.is(":visible") )
			tooltipwrap.hide();
	});
	$("body").append(tooltipwrap);

	function UAHelper(){
		var td = $(this), ua = td.attr("title");
		if( !ua )
			return;

		var offset = td.offset(), size = ua.length < 200 ? ua.length : 200;
		tooltip.html( "<input type='text' style='min-width:250px; font: 10px Courier, sans-serif' value='" + ua + "' size='" + size + "'>" );
		tooltipwrap.fadeIn().css( {top: offset.top + td.innerHeight() - 20 + "px", left: offset.left + ( td.width() - tooltip.width() ) / 2 - 20 + "px"} );
	}

	function onUpdate( context ) {
		context.find("a.filter").attr("title", i18n.filter_title);
		context.find("a.goto").attr("title", i18n.link_title);
		context.find("a.ext").attr("title", i18n.ext_link_title);
		context.find("td.ua").mouseover( UAHelper );
	}
	onUpdate( table );
});
	