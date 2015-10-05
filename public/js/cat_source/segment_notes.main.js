SegmentNotes = {
    const : {}, 
    enabled : function() {
        return true ; 
    }
}; 

if ( SegmentNotes.enabled() ) 
(function($,SegmentNotes,undefined) {
    window.segmentNotes = {};

    var registerSegments = function( data ) {
        $.each(data.files, function() {
            $.each(this.segments, function() {
                segmentNotes[ this.sid ] = this.notes ;
            });
        });
    };

    var tabHTML = function( sid ) {
        var segment = UI.Segment.find( sid );
        var notes = segmentNotes[ segment.absoluteId ] ;
        var output = '';

        if ( notes != null ) {
            output = '' +
            '<li class="tab-switcher-notes" id="segment-' + sid + '-notes">' +
            '   <a tabindex="-1" href="#">Messages<span class="number"></span></a>' +
            '</li>';
        }

        return output ;
    }

    var buildNotesForm = function(sid, notes) {
        var panel = $('<div class="tab sub-editor segment-notes" id="" >' +
            '	<div class="overflow">' +
            '       <div class="segment-notes-container">  ' +
            '       	<div class="segment-notes-panel-header">Notes</div>' +
            '           <div class="segment-notes-panel-body"><ul></ul></div> ' +
            '       </div> ' +
            '	</div>' +
            '</div>') ;

        panel.find('.tab').attr('id', 'segment-' + sid + '-segment-notes');

        var root  = $(panel);
        $.each(notes, function() {
            var li = $('<li/>').text( this.note );
            root.find('ul').append( li );
        });

        return $('<div>').append( panel ).html();
    }

    var panelHTML = function( sid ) {
        console.log('@@ panelHTML called with sid',  sid);

        var segment = UI.Segment.find( sid );
        var notes = segmentNotes[ segment.absoluteId ] ;
        var output = '' ;

        if ( notes != null ) {
            output = buildNotesForm(sid, notes) ;
        }
        return output;
    }

    var activateTab = function() {
        if ( $('.tab-switcher-notes:visible').length == 0 ) {
            console.log('@@ !! no tab visible');
            return ;
        }

        $('.editor .submenu .active').removeClass('active');
        $('.tab-switcher-notes').addClass('active');

        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.segment-notes').show();
    }

    $(window).on('segmentOpened', function(e) {
        var segment = new UI.Segment( e.segment );

        if ( segment.isFooterCreated() ) {
            console.log('@@ segmentOpened, clicking');
            activateTab();
        }

    });

    $(window).on('afterFooterCreation', function(e, segment) {
        console.log('@@', segment, UI.currentSegment, segment === UI.currentSegment[0] );

        // IF this event triggers for the current segment it
        // means that the footer was not cached and the loading
        // completed while we were on the active segment.
        if ( segment === UI.currentSegment[0] ) {
            console.log('@@ afterFooterCreation, clicking');
            activateTab();
        }
    });

    $(document).on('click', '.tab-switcher-notes', function(e) {
        e.preventDefault();
        activateTab();
    });

    // exports
    $.extend(SegmentNotes, {
        registerSegments : registerSegments,
        tabHTML : tabHTML,
        panelHTML : panelHTML
    }); 


})(jQuery,SegmentNotes);
