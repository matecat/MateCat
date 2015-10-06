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
        console.debug('@@ panelHTML called with sid',  sid);

        var segment = UI.Segment.find( sid );
        var notes = segmentNotes[ segment.absoluteId ] ;
        var output = '' ;

        if ( notes != null ) {
            output = buildNotesForm(sid, notes) ;
        }
        return output;
    }

    var tabVisible = function( segment ) {
        return $('.tab-switcher-notes:visible', segment).length > 0;
    }

    var activateTab = function() {
        $('.editor .submenu .active').removeClass('active');
        $('.tab-switcher-notes').addClass('active');

        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.segment-notes').show();
    }

    $(window).on('segmentOpened', function(e) {
        console.debug('segmentOpened', e.segment ) ;
        if ( tabVisible( e.segment ) ) {
            console.debug('@@ segmentOpened activating');
            activateTab();
        }
    });

    $(document).on('createFooter:skipped', function(e, segment) {
        console.debug(' createFooter:skipped');
    });

    $(document).on('createFooter:skipped:cached', function(e, segment) {
        if ( tabVisible( segment ) ) {
            activateTab();
        }
    });

    $(window).on('segmentOpened', function(e) {
        var segment = new UI.Segment( e.segment );

        if ( tabVisible( segment ) && segment.isFooterCreated() ) {
            console.debug('@@ segmentOpened, clicking');
            activateTab();
        }

    });

    $(window).on('getContribution:complete', function(e, segment) {
        console.debug('@@ getContribution:complete', $(segment).attr('id') );

        // IF this event triggers for the current segment it
        // means that the footer was not cached and the loading
        // completed while we were on the active segment.
        if ( tabVisible( segment ) ) {
            console.debug('@@ getContribution, clicking');
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
