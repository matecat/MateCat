import {sprintf} from 'sprintf-js'
import {setChunkComplete} from './es6/api/setChunkComplete'

var ProjectCompletion = {
  enabled: function () {
    return config.project_completion_feature_enabled
  },
}

if (ProjectCompletion.enabled()) {
  ;(function ($, config, ProjectCompletion, UI) {
    var sendLabel = 'Mark as complete'
    var sentLabel = 'Marked as complete'
    var sendingLabel = 'Marking'

    var button

    $(function () {
      button = $('#markAsCompleteButton')
    })

    var previousButtonState

    var revertButtonState = function () {
      button.addClass('isMarkableAsComplete')
      button.addClass('notMarkedComplete')
      button.removeClass('isMarkedComplete')
      button.removeAttr('disabled')

      button.val(previousButtonState)
      previousButtonState = null
    }

    var disableButtonToSentState = function () {
      button.removeClass('isMarkableAsComplete')
      button.removeClass('notMarkedComplete')
      button.addClass('isMarkedComplete')
      button.attr('disabled', 'disabled')

      button.val(sentLabel)
    }

    var markAsCompleteSubmit = function () {
      previousButtonState = button.val()
      button.val(sendingLabel)

      setChunkComplete({
        action: 'Features_ProjectCompletion_SetChunkCompleted',
        id_job: config.id_job,
        password: config.password,
        current_password: config.currentPassword,
      })
        .then(function (data) {
          // check for errors in 200 response.

          disableButtonToSentState()

          config.job_completion_current_phase = config.isReview
            ? 'translate'
            : 'revise'
          config.job_marked_complete = true
          config.last_completion_event_id = data.data.event.id

          UI.render(false)
        })
        .catch((errors) => {
          APP.alert({
            msg:
              'An error occurred while marking this job as complete. Please contact support at ' +
              '<a href="support@matecat.com">support@matecat.com</a>.',
          })
          console.log(errors)
          revertButtonState()
        })
    }

    var evalSendButtonStatus = function (stats) {
      // assume a translation was edited, button should be clickable again
      button.removeClass('isMarkableAsComplete isMarkedComplete')
      button.addClass('notMarkedComplete')

      if (UI.isMarkedAsCompleteClickable(stats)) {
        button.addClass('isMarkableAsComplete')
        button.removeAttr('disabled')
      } else {
        button.attr('disabled', 'disabled')
      }

      button.val(sendLabel)
    }

    function isClickableStatus(stats) {
      if (config.isReview) {
        /**
         * Review step
         *
         * In this case the job is markable as complete when 'DRAFT' count is 0
         * and 'TRANSLATED' is < 0 and 'APPROVED' + 'REJECTED' > 0.
         */

        return (
          config.job_completion_current_phase == 'revise' &&
          stats.DRAFT <= 0 &&
          stats.APPROVED + stats.REJECTED > 0
        )
      } else {
        /**
         * Translation step
         *
         * This condition covers the case in which the project is pretranslated.
         * When a project is pretranslated, the 'translated' count can be 0 or
         * less.
         */
        return (
          config.job_completion_current_phase == 'translate' &&
          parseInt(stats.DRAFT) == 0 &&
          parseInt(stats.REJECTED) == 0
        )
      }
    }

    var clickMarkAsCompleteForReview = function () {
      APP.confirm({
        callback: 'markAsCompleteSubmit',
        msg:
          'You are about to mark this job as completed. ' +
          'This will allow translators to edit the job again. ' +
          'Are you sure you want to mark the job as complete?',
      })
    }

    var clickMarkAsCompleteForTranslate = function () {
      APP.confirm({
        callback: 'markAsCompleteSubmit',
        msg:
          'You are about to mark this job as completed. ' +
          'This will allow reviewers to start revision. After this confirm, ' +
          'the job will no longer become editable again until the review is over. ' +
          'Are you sure you want to mark the job as complete?',
      })
    }

    var translateAndReadonly = function () {
      return !config.isReview && config.job_completion_current_phase == 'revise'
    }

    var messageForClickOnReadonly = function () {
      if (UI.translateAndReadonly()) {
        return 'This job is currently under review. Segments are in read-only mode.'
      } else {
        return original_messageForClickOnReadonly()
      }
    }

    var isReadonlySegment = function (segment) {
      return UI.translateAndReadonly() || original_isReadonlySegment(segment)
    }

    var original_isReadonlySegment = UI.isReadonlySegment
    var original_messageForClickOnReadonly = UI.messageForClickOnReadonly

    var original_handleClickOnReadOnly = UI.handleClickOnReadOnly

    var translateWarningMessage

    var handleClickOnReadOnly = function () {
      if (!config.isReview && config.job_completion_current_phase == 'revise') {
        if (!translateWarningMessage || translateWarningMessage.dismissed) {
          showTranslateWarningMessage()
        }
      } else {
        original_handleClickOnReadOnly.apply(undefined, arguments)
      }
    }

    var markJobAsComplete = function () {
      if (config.isReview) {
        UI.clickMarkAsCompleteForReview()
      } else {
        UI.clickMarkAsCompleteForTranslate()
      }
    }

    var clickOnMarkAsComplete = function () {
      if (!button.hasClass('isMarkableAsComplete')) {
        return
      }
      if (
        UI.globalWarnings.totals &&
        UI.globalWarnings.totals.ERROR.length > 0
      ) {
        UI.showFixWarningsModal()
        return
      } else {
        UI.markJobAsComplete()
      }
      $(document).trigger('sidepanel:close')
    }

    var showFixWarningsModal = function () {
      APP.confirm({
        name: 'markJobAsComplete', // <-- this is the name of the function that gets invoked?
        cancelTxt: 'Fix errors',
        onCancel: 'goToFirstError',
        callback: 'markJobAsComplete',
        okTxt: 'Mark as complete',
        msg:
          'Unresolved issues may prevent completing your translation. <br>Please fix the issues. <a style="color: #4183C4; font-weight: 700; text-decoration:' +
          ' underline;" href="https://site.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in MateCat </a> ',
      })
    }

    var checkCompletionOnReady = function () {
      UI.translateAndReadonly() && showTranslateWarningMessage()
      evalReviseNotice()
    }

    $.extend(UI, {
      // This is necessary because of the way APP.popup works
      markAsCompleteSubmit: markAsCompleteSubmit,
      isReadonlySegment: isReadonlySegment,
      messageForClickOnReadonly: messageForClickOnReadonly,
      handleClickOnReadOnly: handleClickOnReadOnly,
      markJobAsComplete: markJobAsComplete,
      isMarkedAsCompleteClickable: isClickableStatus,
      translateAndReadonly: translateAndReadonly,
      clickMarkAsCompleteForTranslate: clickMarkAsCompleteForTranslate,
      clickMarkAsCompleteForReview: clickMarkAsCompleteForReview,
      showFixWarningsModal: showFixWarningsModal,
      checkCompletionOnReady: checkCompletionOnReady,
    })

    var showTranslateWarningMessage = function () {
      var message =
        'All segments are in <b>read-only mode</b> because this job is under review.'

      if (config.chunk_completion_undoable && config.last_completion_event_id) {
        message =
          message +
          '<p class=\'warning-call-to\'><a href="javascript:void(0);" id="showTranslateWarningMessageUndoLink" >Re-Open Job</a></p>'
      }

      translateWarningMessage = window.intercomErrorNotification =
        APP.addNotification({
          autoDismiss: false,
          dismissable: true,
          position: 'tc',
          text: message,
          title: 'Warning',
          type: 'warning',
          allowHtml: true,
        })
    }

    $(document).on(
      'click',
      '#showTranslateWarningMessageUndoLink',
      function (e) {
        e.preventDefault()

        $.ajax({
          type: 'DELETE',
          url: sprintf(
            '/api/app/jobs/%s/%s/completion-events/%s',
            config.id_job,
            config.password,
            config.last_completion_event_id,
          ),
        })
          .done(function () {
            location.reload()
          })
          .fail(function () {
            console.error('Error undoing completion event')
          })
      },
    )

    var evalReviseNotice = function () {
      if (
        config.isReview &&
        config.job_completion_current_phase == 'translate' &&
        config.job_marked_complete == 0
      ) {
        APP.addNotification({
          type: 'warning',
          title: 'Warning',
          text: 'Translator/post-editor did not mark this job as complete yet. Please wait for vendor phase to complete before making any change.',
          dismissable: false,
          autoDismiss: false,
        })
      }
    }

    $(document).on('click', '#markAsCompleteButton', function (ev) {
      ev.preventDefault()
      clickOnMarkAsComplete()
    })

    $(document).on('setTranslation:success', function (ev, data) {
      evalSendButtonStatus(data.stats)
    })

    $(document).ready(function () {
      UI.checkCompletionOnReady()
    })
  })(jQuery, window.config, window.ProjectCompletion, UI)
}
