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
        if($('#user-menu-dropdown').length){
            $('#user-menu-dropdown').dropdown();
        }
    }
});
