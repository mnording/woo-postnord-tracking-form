(function($) {

    $("#postnord-tracking-form-container button").on("click",function(){
       var trackingID = $("#postnord-tracking-form-container input#trackingid").val();
       var orderID = $("#postnord-tracking-form-container input#orderid").val();
        $("#postnord-tracking-response-container").html("<div class='loader'></div>");
        var data = {
            'action': 'get_postnord_tracking',
            'trackingID':trackingID,
            'orderID':orderID
        };
        $.get(
            woocommerce_params.ajax_url, // The AJAX URL
            data,
            function(response){
                $("#postnord-tracking-response-container").html(response);
            }
        );
    })
})( jQuery );