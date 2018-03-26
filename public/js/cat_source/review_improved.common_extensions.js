if ( ReviewImproved.enabled() ) {
(function($, root, undefined) {

    var prev_getStatusForAutoSave = UI.getStatusForAutoSave ;

    SegmentNotes.buildNotesForm = function(sid, notes) {
        var regExpUrl = /((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\/$(~,!)?\w_]*)#?(?:[\w]*))?)/gmi;
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
            var note = this.note.replace(regExpUrl, function ( match, text ) {
                return '<a href="'+ text +'" target="_blank">' + text + '</a>';
            });
            var text = $('<span />').html( note );

            li .append( label ) .append( text ) ;

            root.find('ul').append( li );
        });

        return $('<div>').append( panel ).html();
    };

    $.extend(UI, {
        get showPostRevisionStatuses() {
            return true;
        },

        /**
         * getStatusForAutoSave
         *
         * XXX: Overriding this here does not make sens anymore when fixed and
         * rebutted states will enter MateCat's core.
         *
         * @param segment
         * @returns {*}
         */
        getStatusForAutoSave : function( segment ) {
            var status = prev_getStatusForAutoSave( segment );

            if (segment.hasClass('status-fixed')) {
                status = 'fixed';
            }
            else if (segment.hasClass('status-rebutted')) {
                status = 'rebutted' ;
            }
            return status;
        },

        getSegmentVersionsIssuesHandler: function (event) {
            // TODO Uniform behavior of ReviewExtended and ReviewImproved
            let sid = event.segment.absId;
            let fid = UI.getSegmentFileId(event.segment.el);
            let versions = [];
            SegmentActions.addTranslationIssuesToSegment(fid, sid, versions);
        },
        submitComment : function(id_segment, id_issue, data) {
            return ReviewImproved.submitComment(id_segment, id_issue, data)
        },
    });
})(jQuery, window);
}
