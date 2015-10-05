SegmentNotes = {
    const : {}, 
    enabled : function() {
        return true ; 
    }
}; 

if ( SegmentNotes.enabled() ) 
(function($,SegmentNotes,undefined) {
    var segmentNotes = {};

    var registerSegments = function( data ) {
        $.each(data.files, function() {
            $.each(this.segments, function() {
                segmentNotes[ this.sid ] = this.notes ;
            });
        });
    };

    var tabHTML = function( sid ) {
        return '' +
        '<li class="tab-switcher-notes" id="segment-' + sid + '-notes">' +
        '   <a tabindex="-1" href="#">Messages<span class="number"></span></a>' +
        '</li>';
    }

    var panelHTML = function( sid ) {
        var segment = UI.Segment.find( sid );
        var panel = $( SegmentNotes.const.tpls.panel );
        panel.find('.tab').attr('id', 'segment-' + sid + '-segment-notes');

        return $('<div>').append( panel ).html();
    }

    $(window).on('afterFooterCreation', function(e, segment) {
        var segment = new UI.Segment( $(segment) ) ;
        var notes = segmentNotes[ segment.absoluteId ] ;

        if ( notes != null ) {
            var root = $(SegmentNotes.const.tpls.notesPanel);
            $('.tab-switcher-notes').show();

            var root  = $('.sub-editor.segment-notes') ;
            root.find('ul').html('');

            $.each(notes, function() {
                var li = $('<li/>').text( this.note );
                root.find('ul').append( li );
            });
        }
        else {
            $('.tab-switcher-notes').hide();
        }
    });

    $(document).on('click', '.tab-switcher-notes', function(e) {
        e.preventDefault();

        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');

        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.segment-notes').show();
    });

    // exports
    $.extend(SegmentNotes, {
        registerSegments : registerSegments,
        tabHTML : tabHTML,
        panelHTML : panelHTML
    }); 


})(jQuery,SegmentNotes);
