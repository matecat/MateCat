if ( SegmentNotes.enabled() )
(function($,SegmentNotes) {

    var tpls = {
        notesPanel : '' +
            '<div class="tab sub-editor segment-notes" id="" >' +
            '	<div class="overflow">' +
            '       <div class="segment-notes-container">  ' +
            '       	<div class="segment-notes-panel-header">Notes</div>' +
            '           <div class="segment-notes-panel-body"><ul></ul></div> ' +
            '       </div> ' +
            '	</div>' +
            '</div>'
    };

    $.extend(SegmentNotes.const, {
        get tpls() {
            return tpls ;
        }
    });
})(jQuery,SegmentNotes); 
