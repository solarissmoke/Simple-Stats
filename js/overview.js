$(document).ready( function(){
	function activateFilter(field, value) {
		var sel = $('select[name="'+field+'"]');
		sel.closest('p').addClass('activefilter');
		sel.val(value).trigger('change');
	}
	
	function handleHashChange() {
		var href = location.href.substr(0, location.href.indexOf('#'));
		href += (href.indexOf('?') > -1) ? '&' : '?';
		href += 'ajax=1&';
		href += location.hash.substr(1);

		$.get(href, function(data) {
			$('#main').html($(data).find("#main").html());
			$('#side').html($(data).find("#side").html());
			
			$("#filters .clear-filter").click( function(e){
				e.preventDefault();
				$(this).parent().find(":input").val("0").change();
			});
			
			overviewRefresh();
		}, "html");
	}
	
	function overviewRefresh() {
		// js/no-js
		$(".hide-if-js").hide();
		$(".hide-if-no-js").show();
		
		// toggle links
		$('a.toggle').click( function(e) {
			e.preventDefault();
			var a = $(this);
			a.toggleClass("unfolded");
			if( a.hasClass("unfolded") )
				a.css("border-color", "#000 #FFF #FFF #FFF");
			else
				a.css("border-color", "#FFF #FFF #FFF #000"); 
				
			var id = a.attr('id');
			if (id != '')
				$('tr.detail_'+id).toggle();
		});
		
		// filter links
		$('#main a[href^="./?filter_"], table.calendar a').click(function(e) {
			e.preventDefault();
			location.hash = $(this).attr("href").replace(/\.\/\??/, "" );
		});
		
		// handle filters changing
		$('#filters :input').change( function() {
			$("#filters .clear-filter").remove();	// remove old x's
			
			var hash = '';
			var separator = '#';
			$('#filters :input').each(function() {
				var i = $(this), name = i.attr("name"), val = i.val(), p = $(this).parent();
				if ( !name )
					return;
				
				if ( val != "0" ) {
					hash += separator;
					hash += name + '=' + encodeURIComponent( val );
					separator = '&';
					if( i.is("select") )
						p.prepend("<a class='clear-filter'>&#215;</a> ");
				}
			});
			
			location.hash = hash;
		});
		
		// make the filter section pretty
		var s = $("#side").css("padding-bottom", "10px"), m = $("#main"), diff = m.innerHeight() - s.innerHeight();
		if( diff )
			s.css("padding-bottom", ( 10 + diff ) + "px");
			
		// ajax activity indicator
		var ajax = $('<div id="ajaxindicator"></div>');
		ajax.css({position: "absolute", top: "20px", right: "20px"});
		$('#overviewpage #main').prepend(ajax);
		
		// show/hide ajax activity indicator
		$(document).ajaxStart(function() { 
			var spinner = new Spinner({ lines: 10, length: 5, width: 2, radius: 4, color: '#000', speed: 1, trail: 60, shadow: false}).spin(ajax[0]);
		}).ajaxStop(function() { 
			$('#ajaxindicator').empty();
		});
		
		// charts
		var lineChartOptions = {
			series: {
				lines: { show: true },
				points: { show: true }
			},
			legend: { show: false },
			grid: { hoverable: true },
			xaxis: { tickSize: 1, tickDecimals: 0 },
		};
		
		var cdata = $("#chart-data");
		if( cdata.length ) {
			var data_visits = [], data_hits = [];
			
			$("tr", cdata).each( function(i,row){
				var r = $(row);
				var x = parseInt( $("th", r).text() );
				var bits = $("td", r);
				data_visits.push( [x, parseInt( $(bits[0]).text() )] );
				data_hits.push( [x, parseInt( $(bits[1]).text() )] );
			});
			
			var chart = $.plot( $("#chart"), [data_visits], lineChartOptions );
			
			$("#chartopt a").click( function(e){
				e.preventDefault();
				var type = $(this).attr("data:show");
				chart = $.plot( $("#chart"), [ type == "h" ? data_hits : data_visits ], lineChartOptions );
				$("#chart-title").text( $("#chart-data").attr("data:" + type + "title") );
				$("#chartopt a").removeClass("current");
				$(this).addClass("current");
			});
			
			var previousPoint = null;
			$("#chart").bind("plothover", function (event, pos, item) {
				if (item) {
					if (previousPoint != item.dataIndex) {
						previousPoint = item.dataIndex;
						
						$("#tooltip").remove();
						var y = item.datapoint[1];
						
						$('<div id="tooltip">' + y + '</div>').css( {
							position: 'absolute',
							display: 'none',
							"border-radius": "5px",
							"box-shadow": "#CCC 2px 2px 5px",
							"font-weight": "bold",
							top: item.pageY,
							left: item.pageX + 20,
							border: '1px solid #CCC',
							padding: '5px 10px',
							'background-color': '#FFF'
						}).appendTo("body").fadeIn(200);
					}
				}
				else {
					$("#tooltip").remove();
					previousPoint = null;            
				}
			});
		}
	}
	
	// handle window.onhashchange
	$(window).bind('hashchange', function() {
		handleHashChange();
	});

	if( location.hash )
		handleHashChange();

	overviewRefresh();
});
