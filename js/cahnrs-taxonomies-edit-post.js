jQuery(document).ready(function($){

	$("#cahnrs_unitchecklist > li > label:has(checkbox:checked)").css('font-weight','normal');

	$( '#cahnrs_unitchecklist > li > label' ).on( 'click', function(e) {
		e.preventDefault();
		$(this).parent( 'li' ).toggleClass( 'open' ).children( 'ul' ).toggle();
	});

});