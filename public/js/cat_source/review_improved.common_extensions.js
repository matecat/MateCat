if ( ReviewImproved.enabled() ) {
(function($, root, undefined) {

    var prev_getStatusForAutoSave = UI.getStatusForAutoSave ;
    /**
     * Split segment feature is not compatible with ReviewImproved.
     */
    window.config.splitSegmentEnabled = false;

    $.extend(UI, {

        mountPanelComponent : function() {
            UI.issuesMountPoint =   $('[data-mount=review-side-panel]')[0];
            ReactDOM.render(
                React.createElement( ReviewSidePanel, {
                    closePanel: this.closeIssuesPanel,
                    reviewType: Review.type,
                    isReview: config.isReview
                } ),
                UI.issuesMountPoint );
        },

        unmountPanelComponent : function() {
            ReactDOM.unmountComponentAtNode( UI.issuesMountPoint );
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
            var sid = event.segment.absId;
            var fid = UI.getSegmentFileId(event.segment.el);
            var versions = [];
            SegmentActions.addTranslationIssuesToSegment(fid, sid, versions);
        },
        submitComment : function(id_segment, id_issue, data) {
            return ReviewImproved.submitComment(id_segment, id_issue, data)
        },
    });

    $(document).ready(function() {
        UI.mountPanelComponent();
    });

})(jQuery, window);
}
