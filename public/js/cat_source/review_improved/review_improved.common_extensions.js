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
        openIssuesPanel : function(data, openSegment) {
            var segment = (data)? UI.Segment.findEl( data.sid ): data;
            $('body').addClass('review-improved-opened');
            hackIntercomButton( true );
            SearchUtils.closeSearch();

            $('body').addClass('side-tools-opened review-side-panel-opened');
            window.dispatchEvent(new Event('resize'));
            if (data && openSegment) {
                segment.find( UI.targetContainerSelector() ).click();
                window.setTimeout( function ( data ) {
                    var el = UI.Segment.find( data.sid ).el;

                    if ( UI.currentSegmentId != data.sid ) {
                        UI.focusSegment( el );
                    }

                    UI.scrollSegment( el );
                }, 500, data );
            }
        },

        closeIssuesPanel : function() {

            hackIntercomButton( false );
            SegmentActions.closeIssuesPanel();
            $('body').removeClass('side-tools-opened review-side-panel-opened review-improved-opened');
            if ( UI.currentSegment ) {
                setTimeout( function() {
                    UI.scrollSegment( UI.currentSegment );
                }, 100 );
            }
            window.dispatchEvent(new Event('resize'));
        },

        deleteIssue : function( issue, sid, dontShowMessage) {
            var message = '';
            if ( issue.target_text ) {
                message = sprintf(
                    "You are about to delete the issue on string <span style='font-style: italic;'>'%s'</span> " +
                    "posted on %s." ,
                    issue.target_text,
                    moment( issue.created_at ).format('lll')
                );
            } else {
                message = sprintf(
                    "You are about to delete the issue posted on %s." ,
                    moment( issue.created_at ).format('lll')
                );
            }
            if ( !dontShowMessage) {
                APP.confirm({
                    name : 'Confirm issue deletion',
                    callback : 'deleteTranslationIssue',
                    msg: message,
                    okTxt: 'Yes delete this issue',
                    context: JSON.stringify({
                        id_segment : sid,
                        id_issue : issue.id
                    })
                });
            } else {
                UI.deleteTranslationIssue(JSON.stringify({
                    id_segment : sid,
                    id_issue : issue.id
                }));
            }
        },
        setRevision: function( data ){
            APP.doRequest({
                data: data,
                error: function() {
                    UI.failedConnection( data, 'setRevision' );
                },
                success: function(d) {
                    window.quality_report_btn_component.setState({
                        vote: d.data.overall_quality_class
                    });
                }
            });
        },
        /**
         *
         * @param d Data response from the SetCurrentSegment request
         * @param id_segment
         */
        addOriginalTranslation: function (d, id_segment) {
            if (d.original !== '') {
                setTimeout(function (  ) {
                    SegmentActions.addOriginalTranslation(id_segment, UI.getSegmentFileId($('#segment-' + id_segment)), d.original);
                });
            }
        },

        clickOnApprovedButton: function (e, button) {
            // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
            // because of the event is triggered even on download button
            e.preventDefault();
            var sid = UI.currentSegmentId;
            var goToNextNotApproved = ($(button).hasClass('approved')) ? false : true;
            UI.tempDisablingReadonlyAlert = true;
            SegmentActions.removeClassToSegment(sid, 'modified');
            UI.currentSegment.data('modified', false);


            $('.sub-editor.review .error-type').removeClass('error');

            UI.changeStatus(button, 'approved', 0);  // this does < setTranslation

            var original = UI.currentSegment.find('.original-translation').text();

            if (goToNextNotApproved) {
                UI.openNextTranslated();
            } else {
                UI.gotoNextSegment(sid);
            }

            var data = {
                action: 'setRevision',
                job: config.id_job,
                jpassword: config.password,
                segment: sid,
                original: original
            };

            UI.setRevision(data);
        }
    });

    $(document).ready(function() {
        UI.mountPanelComponent();
    });

})(jQuery, window);
}
