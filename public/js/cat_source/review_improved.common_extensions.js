if ( ReviewImproved.enabled() ) {
(function($, root, undefined) {

    var prev_getStatusForAutoSave = UI.getStatusForAutoSave ;

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
        autoCopySuggestionEnabled: function () {
            return false;
        },
    });
})(jQuery, window);
}
