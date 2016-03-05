if ( ReviewImproved.enabled() ) {
(function($, root, undefined) {

    var prev_getStatusForAutoSave = UI.getStatusForAutoSave ;

    $.extend(UI, {
        get showPostRevisionStatuses() {
            return true;
        },

        getStatusForAutoSave : function( segment ) {
            var status = prev_getStatusForAutoSave( segment );

            if (segment.hasClass('status-fixed')) {
                status = 'fixed';
            }
            else if (segment.hasClass('status-rebutted')) {
                status = 'fixed' ;
            }
            return status;
        },
    });
})(jQuery, window);
}
