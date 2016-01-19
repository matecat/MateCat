SegmentNotes = {
    const : {}, 
    enabled : function() {
        return true ; 
    }
}; 

if ( SegmentNotes.enabled() ) 
(function($,SegmentNotes,undefined) {

    window.segmentNotes = {};

    window.UI.SegmentFooter.registerTab({
        code : 'notes',
        label : 'Messages',
        tab_class : 'segment-notes',
        activation_priority : 60,
        tab_position : 50,
        is_enabled : function( footer ) {
            var notes = window.segmentNotes[ footer.segment.absoluteId ] ;
            return notes != null;
        },
        tab_markup : function( footer ) {
            return this.label ;
        },
        content_markup : function( footer ) {
            return SegmentNotes.panelHTML( footer.segment );
        },
        is_hidden : function( footer ) {
            return false;
        }
    });


    var registerSegments = function( data ) {
        $.each(data.files, function() {
            $.each(this.segments, function() {
                segmentNotes[ this.sid ] = this.notes ;
            });
        });
    };

    var buildNotesForm = function(sid, notes) {
        var panel = $('' +
            '	<div class="overflow">' +
            '       <div class="segment-notes-container">  ' +
            '           <div class="segment-notes-panel-body">' +
            '             <ul class="graysmall"></ul> ' +
            '           </div>' +
            '       </div> ' +
            '   </div>');

        panel.find('.tab').attr('id', 'segment-' + sid + '-segment-notes');

        var root  = $(panel);
        $.each(notes, function() {
            var li = $('<li/>');
            var label = $('<span class="note-label">Note: </span>');
            var text = $('<span />').html( this.note );

            li .append( label ) .append( text ) ;

            root.find('ul').append( li );
        });

        return $('<div>').append( panel ).html();
    }

    var panelHTML = function( segment ) {
        var notes = segmentNotes[ segment.absoluteId ] ;
        var output = '' ;

        if ( notes != null ) {
            output = buildNotesForm(segment.absoluteId, notes) ;
        }
        return output;
    }

    var tabVisible = function( segment ) {
        return $('.tab-switcher-notes:visible', segment).length > 0;
    }

    // exports
    $.extend(SegmentNotes, {
        registerSegments : registerSegments,
        panelHTML : panelHTML
    }); 


})(jQuery,SegmentNotes);
