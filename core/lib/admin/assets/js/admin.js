$(function(){
	
	
	
	$('.js-sidebar-icon').on('click',function(){
		$('.js-sidebar-icon').removeClass('active')
		$(this).addClass('active')
		$('.js-sidebar-area').hide()
		$('.js-sidebar-area[data-id="' + $(this).data('id') + '"]').show()
	});
	$($('.js-sidebar-icon')[0]).click();
})