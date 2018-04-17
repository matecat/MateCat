if ( ReviewImproved.enabled() ) {
(function($, root, undefined) {

    var prev_getStatusForAutoSave = UI.getStatusForAutoSave ;

    $.extend(UI, {
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
