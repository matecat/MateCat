ProjectCompletion = {
    enabled : function() {
        return config.project_completion_feature_enabled;
    }
}

if ( ProjectCompletion.enabled() ) {
(function($,config,ProjectCompletion,UI,undefined) {
    var sendLabel = 'Mark as complete';
    var sentLabel = 'Marked as complete' ;
    var sendingLabel = 'Marking';

    var button, reviseNotification ;

    $(function() {
        button = $('#markAsCompleteButton');
    });

    var previousButtonState ;

    var revertButtonState = function() {
        button.addClass("isMarkableAsComplete");
        button.addClass("notMarkedComplete");
        button.removeClass('isMarkedComplete');
        button.removeAttr('disabled');

        button.val( previousButtonState ) ;
        previousButtonState = null ;
    }

    var disableButtonToSentState = function() {
        button.removeClass("isMarkableAsComplete");
        button.removeClass("notMarkedComplete");
        button.addClass('isMarkedComplete');
        button.attr('disabled', 'disabled');

        button.val( sentLabel );
    }

    var markAsCompleteSubmit = function() {

        var data = {
            action   : 'Features_ProjectCompletion_SetChunkCompleted',
            id_job   : config.id_job,
            password : config.password
        }

        previousButtonState = button.val() ;
        button.val( sendingLabel );

        var request = APP.doRequest( {
            data    : data
       });

        request.done( function( data ) {
            // check for errors in 200 response.
            if ( data.errors.length > 0 ) {
                APP.alert({
                    msg: 'An error occurred while marking this job as complete. Please contact support at ' +
                    '<a href="support@matecat.com">support@matecat.com</a>.'
                });
                console.log( data.errors );
                revertButtonState();
            }
            else {
                disableButtonToSentState();

                config.job_completion_current_phase = ( config.isReview ? 'translate' : 'revise' ) ;
                config.job_marked_complete = true;
                config.last_completion_event_id = data.data.event.id ;

                UI.render( false );

            }
        });
    }

    var evalSendButtonStatus = function( stats ) {
        // assume a translation was edited, button should be clickable again
        button.removeClass('isMarkableAsComplete isMarkedComplete');
        button.addClass('notMarkedComplete');

        if ( UI.isMarkedAsCompleteClickable( stats ) ) {
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

            return config.job_completion_current_phase == 'revise' &&
                stats.DRAFT <= 0 &&
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
            return config.job_completion_current_phase == 'translate' &&
                parseInt( stats.DRAFT ) == 0 && parseInt( stats.REJECTED ) == 0 ;
        }
    }


    var clickMarkAsCompleteForReview = function() {
        APP.confirm({
            callback: 'markAsCompleteSubmit',
            msg: 'You are about to mark this job as completed. ' +
            'This will allow translators to edit the job again. ' +
            'Are you sure you want to mark the job as complete?'
        });
    };

    var clickMarkAsCompleteForTranslate = function() {
        APP.confirm({
            callback: 'markAsCompleteSubmit',
            msg: 'You are about to mark this job as completed. ' +
            'This will allow reviewers to start revision. After this confirm, ' +
            'the job will no longer become editable again until the review is over. ' +
            'Are you sure you want to mark the job as complete?'
        });
    };


    var translateAndReadonly = function() {
        return !config.isReview && config.job_completion_current_phase == 'revise' ;
    };

    var messageForClickOnReadonly = function( section ) {
        if ( UI.translateAndReadonly()) {
            return 'This job is currently under review. Segments are in read-only mode.';
        }
        else {
            return original_messageForClickOnReadonly() ;
        }
    };

    var isReadonlySegment = function( segment ) {
        return translateAndReadonly() || original_isReadonlySegment( segment ) ;
    }

    var original_isReadonlySegment = UI.isReadonlySegment ;
    var original_messageForClickOnReadonly = UI.messageForClickOnReadonly ;

    var original_handleClickOnReadOnly = UI.handleClickOnReadOnly ;

    var translateWarningMessage ;

    var handleClickOnReadOnly = function() {
        if ( !config.isReview && config.job_completion_current_phase == 'revise' ) {
            if ( !translateWarningMessage || translateWarningMessage.dismissed ) {
                showTranslateWarningMessage();
            }
        }
        else {
            original_handleClickOnReadOnly.apply( undefined, arguments );
        }
    }

    $.extend( UI, {
        // This is necessary because of the way APP.popup works
        markAsCompleteSubmit      : markAsCompleteSubmit,
        isReadonlySegment         : isReadonlySegment,
        messageForClickOnReadonly : messageForClickOnReadonly,
        handleClickOnReadOnly     : handleClickOnReadOnly,
        isMarkedAsCompleteClickable: isClickableStatus,
        translateAndReadonly      : translateAndReadonly
    });


    var showTranslateWarningMessage = function() {

        var message = "This job is currently under review. Segments are in read-only mode." ;

        if ( config.chunk_completion_undoable && config.last_completion_event_id ) {
            message = message + " To undo this action <a href=\"javascript:void(0);\" id=\"showTranslateWarningMessageUndoLink\" >click here</a>.";
        }

        translateWarningMessage = window.intercomErrorNotification = APP.addNotification({
            autoDismiss: false,
            dismissable: true,
            position : "tc",
            text : message,
            title : "Warning",
            type : "warning",
            allowHtml : true
        });
    };

    var checkCompletionOnReady = function (  ) {
        translateAndReadonly() && showTranslateWarningMessage();
        evalReviseNotice();
    };

    $(document).on( 'click', '#showTranslateWarningMessageUndoLink', function(e) {
        e.preventDefault();

        $.ajax({
            type: 'DELETE',
            url: sprintf('/api/app/jobs/%s/%s/completion-events/%s', config.id_job, config.password, config.last_completion_event_id )
        }).done( function() {
            UI.reloadPage();
        }).fail( function() {
            console.error('Error undoing completion event');
        });
    });

    var evalReviseNotice = function() {
        if ( config.isReview && config.job_completion_current_phase == 'translate' && config.job_marked_complete == 0 ) {
            warningNotification = APP.addNotification({
                type: 'warning',
                title: 'Warning',
                text: 'Translator/post-editor did not mark this job as complete yet. Please wait for vendor phase to complete before making any change.',
                dismissable : false,
                autoDismiss : false
            });
        }
    };

    $(document).on('click', '#markAsCompleteButton', function(ev) {
        ev.preventDefault();
        if ( !button.hasClass('isMarkableAsComplete') ) {
            return;
        }
        $(document).trigger('sidepanel:close');
        if ( config.isReview ) {
            clickMarkAsCompleteForReview();
        } else {
            clickMarkAsCompleteForTranslate();
        }

    });

    $(document).on('setTranslation:success', function(ev, data) {
        evalSendButtonStatus( data.stats );
    });

    $(document).ready(function() {
        checkCompletionOnReady()
    });


})(jQuery, window.config, window.ProjectCompletion, UI);
}
