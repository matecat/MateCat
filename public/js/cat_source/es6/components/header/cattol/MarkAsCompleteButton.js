import React, {useEffect, useRef, useState} from 'react'
import ConfirmMessageModal from '../../modals/ConfirmMessageModal'
import {setChunkComplete} from '../../../api/setChunkComplete'
import AlertModal from '../../modals/AlertModal'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import CatToolActions from '../../../actions/CatToolActions'
import {deleteCompletionEvents} from '../../../api/deleteCompletionEvents'
import ModalsActions from '../../../actions/ModalsActions'
export const MarkAsCompleteButton = ({featureEnabled, isReview}) => {
  const button = useRef()
  const [markedAsComplete, setMarkedAsComplete] = useState(
    config.job_marked_complete !== 0,
  )
  const [buttonEnabled, setButtonEnabled] = useState(
    config.mark_as_complete_button_enabled,
  )
  const [stats, setStats] = useState()
  const [lastCompletionEventId, setLastCompletionEventId] = useState()
  const jobCompletionCurrentPhase = useRef(config.job_completion_current_phase)

  const translateMessage =
    'You are about to mark this job as completed. ' +
    'This will allow reviewers to start revision. After this confirm, ' +
    'the job will no longer become editable again until the review is over. ' +
    'Are you sure you want to mark the job as complete?'
  const reviewMessage =
    'You are about to mark this job as completed. ' +
    'This will allow translators to edit the job again. ' +
    'Are you sure you want to mark the job as complete?'

  const showFixWarningsModal = () => {
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      {
        text:
          'Unresolved issues may prevent completing your translation. <br>Please fix the issues. <a style="color: #4183C4; font-weight: 700; text-decoration:' +
          ' underline;" href="https://site.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in Matecat </a> ',
        successText: 'Mark as complete',
        cancelText: 'Fix errors',
        successCallback: () => clickMarkAsCompleteModal(),
      },
      'Confirmation required',
    )
  }

  const clickMarkAsCompleteModal = () => {
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      {
        text: isReview ? reviewMessage : translateMessage,
        successText: 'Continue',
        cancelText: 'Cancel',
        successCallback: () => markAsCompleteSubmit(),
      },
      'Confirmation required',
    )
  }

  const showTranslateWarningMessage = () => {
    // if (!lastCompletionEventId) return
    let message =
      'All segments are in <b>read-only mode</b> because this job is under review.'

    if (config.chunk_completion_undoable && config.last_completion_event_id) {
      message =
        message +
        '<p class=\'warning-call-to\'><a href="javascript:void(0);" id="showTranslateWarningMessageUndoLink" >Re-Open Job</a></p>'
    }

    CatToolActions.addNotification({
      uid: 'translate-warning',
      autoDismiss: false,
      dismissable: true,
      position: 'tc',
      text: message,
      title: 'Warning',
      type: 'warning',
      allowHtml: true,
    })
  }

  const clickMarkAsComplete = () => {
    if (buttonEnabled) {
      const globalWarnings = SegmentStore.getGlobalWarnings()
      if (globalWarnings.totals && globalWarnings.totals.ERROR.length > 0) {
        showFixWarningsModal()
      } else {
        clickMarkAsCompleteModal()
      }
    }
  }

  const markAsCompleteSubmit = () => {
    setButtonEnabled(false)

    setChunkComplete({
      action: 'Features_ProjectCompletion_SetChunkCompleted',
      id_job: config.id_job,
      password: config.password,
      current_password: config.currentPassword,
    })
      .then(function (data) {
        // check for errors in 200 response.
        setMarkedAsComplete(true)
        setLastCompletionEventId(data.data.event.id)

        location.reload()
      })
      .catch(() => {
        ModalsActions.showModalComponent(
          AlertModal,
          {
            text:
              'An error occurred while marking this job as complete. Please contact support at ' +
              '<a href="support@matecat.com">support@matecat.com</a>.',
          },
          'Error',
        )
        setButtonEnabled(true)
        setMarkedAsComplete(false)
      })
  }

  const checkCompletionOnMount = () => {
    if (!isReview && jobCompletionCurrentPhase.current == 'revise') {
      showTranslateWarningMessage()
    }
    if (
      isReview &&
      jobCompletionCurrentPhase.current === 'translate' &&
      !markedAsComplete
    ) {
      CatToolActions.addNotification({
        type: 'warning',
        title: 'Warning',
        text: 'Translator/post-editor did not mark this job as complete yet. Please wait for vendor phase to complete before making any change.',
        dismissable: false,
        autoDismiss: false,
      })
    }
  }

  useEffect(() => {
    // assume a translation was edited, button should be clickable again
    const isButtonClickable = () => {
      if (isReview) {
        /**
         * Review step
         *
         * In this case the job is markable as complete when 'DRAFT' count is 0
         *  and 'APPROVED' + 'REJECTED' > 0.
         */
        return (
          jobCompletionCurrentPhase.current == 'revise' &&
          stats.APPROVED + stats.REJECTED + stats.TRANSLATED > 0
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
          jobCompletionCurrentPhase.current == 'translate' &&
          parseInt(stats.REJECTED) == 0
        )
      }
    }
    if (!stats || markedAsComplete) return
    if (isButtonClickable()) {
      setMarkedAsComplete(false)
      setButtonEnabled(true)
    } else {
      setButtonEnabled(false)
    }
  }, [stats])

  // add actions listener
  useEffect(() => {
    const updateStats = (stats) => setStats(stats)
    CatToolStore.addListener(CattolConstants.SET_PROGRESS, updateStats)
    $(document).on('click', '#showTranslateWarningMessageUndoLink', (e) => {
      e.preventDefault()
      deleteCompletionEvents(lastCompletionEventId)
        .then(() => location.reload())
        .catch(() => {
          console.error('Error undoing completion event')
          location.reload()
        })
    })
    return () => {
      CatToolStore.removeListener(CattolConstants.SET_PROGRESS, updateStats)
      $(document).off('click', '#showTranslateWarningMessageUndoLink')
    }
  }, [])

  useEffect(() => {
    checkCompletionOnMount()
  }, [])

  return (
    <>
      {/*Mark as complete*/}
      {featureEnabled && (
        <button
          ref={button}
          className={`action-submenu ui floating dropdown ${
            markedAsComplete
              ? 'isMarkedComplete'
              : buttonEnabled
              ? 'isMarkableAsComplete'
              : 'notMarkedComplete'
          }`}
          id="markAsCompleteButton"
          disabled={!buttonEnabled}
          onClick={clickMarkAsComplete}
        />
      )}
    </>
  )
}
