/*
Access Code Feeder JS

*/

var ACF1984S_show_wait = function(do_show){
	if(do_show){
		jQuery("body").css("cursor", "progress");
		jQuery('.feeders-global-loader').show();
	}else{
		jQuery('.feeders-global-loader').hide();
		jQuery("body").css("cursor", "default");
	}
	setTimeout(function(){	jQuery('[data-toggle="tooltip"]').tooltip();  },1000); 
}//ACF1984S_show_wait()
