SegmentNotes = {
    const : {}, 
    enabled : function() {
        return true ; 
    }
}; 

if ( SegmentNotes.enabled() ) 
(function($,SegmentNotes,undefined) {

    $(document).on('.tab-switcher-notes', function(e) {
        e.preventDefault(); 
    }); 

    var appendTab = function() {

    }

    // exports
    $.extend(SegmentNotes, {
        appendTab : appendTab
    }); 


})(jQuery,SegmentNotes);
