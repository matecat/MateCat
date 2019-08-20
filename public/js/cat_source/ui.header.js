/*
 Component: ui.header
 */

$.extend( UI, {
    initHeaderDropDown : function() {
        if($('#action-download').length){
            $('#action-download').dropdown('set selected', 'draft');
        }
        if($('#action-QR').length){
            $('#action-QR').dropdown()
        }
        if($('#action-QA').length){
            $('#action-QA').dropdown()
        }
        if($('#action-comments').length){
            $('#action-comments').dropdown()
        }
        if($('#action-settings').length){
            $('#action-settings').dropdown()
        }
        if($('#action-three-dots').length){
            $('#action-three-dots').dropdown('set selected', 'revise' );
        }
    }
});
