$(document).ready(function() {
	if($('div.form.login').length) {  // in login page
		$('input#manager_models_LoginForm_password').focus();
	}
	
	$('table input[name="checkAll"]').click(function() {
		$('table .confirm input').prop('checked', this.checked);
	});
	
//	$('#mytab').click(function (e) {
//		  $(this).tab('show');
//		 
//		});
	
//	 $('#mytab li:eq(2) a').tab('show');
	
	$('table td.confirm input').click(function() {
		$('table input[name="checkAll"]').prop('checked', !$('table td.confirm input:not(:checked)').length);
	});
	$('table input[name="checkAll"]').prop('checked', !$('table td.confirm input:not(:checked)').length);

	$('.form .row.sticky input:not(.error), .form .row.sticky select:not(.error), .form .row.sticky textarea:not(.error)').each(function(){
		var value;
		if(this.tagName=='SELECT')
			value=this.options[this.selectedIndex].text;
		else if(this.tagName=='TEXTAREA')
			value=$(this).html();
		else
			value=$(this).val();
		if(value=='')
			value='[empty]';
		$(this).before('<div class="value">'+value+'</div>').hide();
	});

	$(document).on('click', '.form .row.sticky .value', function(){
		$(this).hide();
		$(this).next().show().get(0).focus();
	});

	$('.form .form-control').tooltip({
		placement:"bottom"
	});


    $(function(){
        var url = window.location.href;
        $('.list-group a').each(function(){
            var current = $(this).attr('href');
            if(url.indexOf(current)>=0)
            {
                $(this).addClass('active')
            }
        });
    });

//    $("[name='proc_switch']").bootstrapSwitch();

//    $(".proc_switch").bootstrapSwitch();
//	$('.form .row input, .form .row textarea, .form .row select, .with-tooltip').tooltip({
////		position: "center right",
////		offset: [-2, 10]
//	});
});