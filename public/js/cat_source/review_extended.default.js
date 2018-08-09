
ReviewExtended = {
    enabled : function() {
        return Review.type === 'extended' ;
    },
    type : config.reviewType
};

if ( ReviewExtended.enabled() ) {


    (function (ReviewExtended, $,undefined) {
        var originalClickOnApprovedButton = UI.clickOnApprovedButton;

        $.extend(UI, {
            clickOnApprovedButton: function (e, button) {
                e.preventDefault();
                var sid = UI.currentSegmentId;
                if ( UI.segmentIsModified(sid)) {
                    SegmentActions.openIssuesPanel({ sid: sid });
                    SegmentActions.showIssuesMessage(sid);
                    return;
                }
                originalClickOnApprovedButton.apply(this, [e , button]);
            },

        });
    })(Review, jQuery);
}
