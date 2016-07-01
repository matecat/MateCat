ProjectCompletion = {
    enabled : function() {
        return config.project_completion_feature_enabled;
    }
}

if ( ProjectCompletion.enabled() ) {
(function($,config,ProjectCompletion,undefined) {
    var sendLabel = 'Mark as complete';
    var sentLabel = 'Marked as complete' ;
    var sendingLabel = 'Marking';

    var button ;

    $(function() {
        button = $('#markAsCompleteButton');
    });

    var disableButtonToSentState = function() {
        button.removeClass("isMarkableAsComplete");
        button.removeClass("notMarkedComplete");
        button.addClass('isMarkedComplete');
        button.attr('disabled', 'disabled');
        button.val( sentLabel );
    }

    var submitProjectCompletion = function() {
        console.log( 'submitProjectCompletion');

        var data = {
            action   : 'Features_ProjectCompletion_SetChunkCompleted',
            id_job   : config.id_job,
            password : config.password
        }

        var success = function( resp ) {
            disableButtonToSentState();
        }

        var error = function( error ) {
            console.log(error);
        }

        button.val( sendingLabel );

        var request = APP.doRequest( {
            data : data ,
            success : success,
            error : error
        });
    }

    var evalSendButtonStatus = function( stats ) {
        // assume a translation was edited, button should be clickable again
        button.removeClass('isMarkableAsComplete isMarkedComplete');
        button.addClass('notMarkedComplete');

        if ( isClickableStatus( stats ) ) {
            button.addClass('isMarkableAsComplete');
            button.removeAttr('disabled');
        } else {
            button.attr('disabled', 'disabled');
        }

        button.val( sendLabel );
    }

    function isClickableStatus( stats ) {
        if (config.isReview) {
            /**
             * Review step
             *
             * In this case the job is markable as complete when 'DRAFT' count is 0
             * and 'TRANSLATED' is < 0 and 'APPROVED' + 'REJECTED' > 0.
             */

            return stats.DRAFT <= 0 &&
                ( stats.APPROVED + stats.REJECTED ) > 0 ;
        }
        else {
            /**
             * Translation step
             *
             * This condition covers the case in which the project is pretranslated.
             * When a project is pretranslated, the 'translated' count can be 0 or
             * less.
             */
            return parseInt( stats.DRAFT ) == 0 && parseInt( stats.REJECTED ) == 0 ;
        }
    }

    $(document).on('click', '#markAsCompleteButton', function(ev) {
        ev.preventDefault();

        if ( button.hasClass('isMarkableAsComplete')) {
            submitProjectCompletion();
        }
    });

    $(document).on('setTranslation:success', function(ev, data) {
        evalSendButtonStatus( data.stats );
    });


})(jQuery, window.config, window.ProjectCompletion);
}
