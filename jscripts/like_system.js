jQuery.noConflict();

jQuery(document).ready(function($) {
	$('.btn_like').on('click', function(event) {
		event.preventDefault();
		var _href = $(this).attr('href');
	});
});
