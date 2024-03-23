jQuery(document).ajaxSend(function(event, xhr, settings) {
  if (settings.url === comment_verify.ajaxurl && settings.data.indexOf('action='+comment_verify.ajax_key) !== -1) {
      var additionalParams = jQuery.param(window.verify_captcha);
      settings.data += '&' + additionalParams ;
  }
});
