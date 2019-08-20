/*
 Component: ui.header
 */

$.extend( UI, {
    initHeader : function() {
        if($('#action-download').length){
            $('#action-download').dropdown();
        }
        if($('#action-three-dots').length){
            $('#action-three-dots').dropdown();
        }
    }
});
