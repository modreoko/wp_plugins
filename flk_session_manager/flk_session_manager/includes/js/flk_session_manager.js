jQuery(function($) {

    $('a.session_attendance_dialog').each( function(){
        var $info = $(this).next('div');
        
        $info.dialog({
            'title'         : 'Attendance sheets',    
            'dialogClass'   : 'wp-dialog',           
            'modal'         : true,
            'autoOpen'      : false, 
            'closeOnEscape' : true,      
            'buttons'       : {
                "Close": function() {
                    $(this).dialog('close');
                }
            }
        });

        $(this).click(function(event) {
            event.preventDefault();
            $info.dialog('open');
        });
    });


    $('.session_options input').on('click', function(){
        alert( $(this).val() )
    })


});