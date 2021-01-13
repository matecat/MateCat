/*
 Component: ui.review
 */
var Review = {
    enabled : function() {
        return (config.enableReview && !!config.isReview);
    },
    type : config.reviewType
};
$.extend( UI, {

    evalOpenableSegment: function ( segment ) {
        if ( ! (
            segment.status === 'NEW' ||
            segment.status === 'DRAFT'
        ) ) return true;

        if ( UI.projectStats && UI.projectStats.TRANSLATED_PERC === 0 ) {
            alertNoTranslatedSegments()
        } else {
            alertNotTranslatedYet( segment.sid );
        }
        return false;
    }
});

var alertNotTranslatedYet = function( sid ) {
    APP.confirm({
        name: 'confirmNotYetTranslated',
        cancelTxt: 'Close',
        callback: 'openNextTranslated',
        okTxt: 'Open next translated segment',
        context: sid,
        msg: UI.alertNotTranslatedMessage
    });
};

var alertNoTranslatedSegments = function(  ) {
    var props = {
        text: 'There are no translated segments to revise in this job.',
        successText: "Ok",
        successCallback: function() {
            APP.ModalWindow.onCloseModal();
        }
    };
    APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Warning");
};


if ( config.enableReview && config.isReview ) {



    (function($, undefined) {

        $.extend(UI, {

            alertNotTranslatedMessage : "This segment is not translated yet.<br /> Only translated segments can be revised.",


            setRevision: function( data ){
                APP.doRequest({
                    data: data,
                    error: function() {
                        OfflineUtils.failedConnection( data, 'setRevision' );
                    },
                    success: function(d) {
                        window.quality_report_btn_component.setState({
                            vote: d.data.overall_quality_class
                        });
                    }
                });
            },
            /**
             * Each revision overwrite this function
             * @param e
             * @param button
             */
            clickOnApprovedButton: function () {
                return false
            }
        });
    })(jQuery);
}
