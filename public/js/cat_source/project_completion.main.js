ProjectCompletion = {
    enabled : function() {
        return config.projectCompletionFeature ;
    }
}

if ( ProjectCompletion.enabled() ) {
(function($,config,ProjectCompletion,undefined) {

    var lastStats = null;
    var sendLabel = 'SEND';
    var sentLabel = 'SENT' ;
    var sendingLabel = 'SENDING';

    var clickableStatusClass = 'translated';

    var button ;

    $(function() {
        button = $('#markAsCompleteButton');
    });

    var disableButtonToSentState = function() {
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

    var evalSendButtonStatus = function() {
        // assume a translation was edited, button should be clickable again
        button.removeClass('isMarkableAsComplete isMarkedComplete');
        button.addClass('notMarkedComplete');

        if ( isClickableStatus(  ) ) {
            button.addClass('isMarkableAsComplete');
            button.removeAttr('disabled');
        } else {
            button.attr('disabled', 'disabled');
        }

        button.val( sendLabel );
    }

    var isClickableStatus = function() {
        return translationStatus( lastStats ) == clickableStatusClass ;
    }

    $(document).on('click', '#markAsCompleteButton', function(ev) {
        ev.preventDefault();

        if ( button.hasClass('isMarkableAsComplete')) {
            submitProjectCompletion();
        }
    });

    $(document).on('setTranslation:success', function(ev, data) {
        console.log('setTranslation:success');

        lastStats = data.stats ;
        evalSendButtonStatus();
    });


})(jQuery, window.config, window.ProjectCompletion);
}
