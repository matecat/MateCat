import React, {useState} from 'react'

import CatToolActions from '../../actions/CatToolActions'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

const RevisionFeedbackModal = (props) => {
  const [sending, setSending] = useState(false)
  const [feedback, setFeedback] = useState(props.feedback)
  const [buttonEnabled, setButtonEnabled] = useState(false)

  const sendFeedback = () => {
    setSending(true)
    CatToolActions.sendRevisionFeedback(feedback)
      .then(() => {
        setTimeout(() => CatToolActions.reloadQualityReport())
        ModalsActions.onCloseModal()
        var notification = {
          title: 'Feedback submitted',
          text: 'Feedback has been submitted correctly',
          type: 'success',
        }
        CatToolActions.addNotification(notification)
      })
      .catch(() => {
        var notification = {
          title: 'Feedback not sent',
          text: 'An error occurred while sending feedback please try again or contact support.',
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
  }

  const onChange = (e) => {
    let value = e.target.value
    if (value !== '') {
      setFeedback(value)
      setButtonEnabled(true)
    } else {
      setFeedback(value)
      setButtonEnabled(false)
    }
  }

  let sendLabel = props.feedback ? 'Modify' : 'Submit'
  return (
    <div className="feedback-modal">
      <div className="matecat-modal-top">
        <h1>Leave your feedback</h1>
      </div>
      <div className="matecat-modal-middle">
        <div className="matecat-modal-text">
          {props.revisionNumber === 1 ? (
            <span>
              Please leave some feedback for the translator on the job quality.
            </span>
          ) : (
            <span>
              Please leave some feedback for the reviser on the job quality.
            </span>
          )}
        </div>
        <div className="matecat-modal-textarea">
          <textarea
            value={feedback}
            style={{width: '100%', height: '100px', resize: 'none', padding: 4}}
            placeholder="Leave your feedback here"
            onChange={onChange}
          />
        </div>
      </div>
      <div className="matecat-modal-bottom">
        <div className="modal-buttons">
          <Button
            type={BUTTON_TYPE.DEFAULT}
            onClick={() => ModalsActions.onCloseModal()}
          >
            {props.feedback ? 'Close' : "I'll do it later"}
          </Button>

          {sending ? (
            <Button type={BUTTON_TYPE.PRIMARY} disabled={true}>
              <span className="button-loader show" style={{left: '280px'}} />
              {sendLabel}
            </Button>
          ) : !buttonEnabled ? (
            <Button type={BUTTON_TYPE.PRIMARY} disabled={true}>
              {sendLabel}
            </Button>
          ) : (
            <Button type={BUTTON_TYPE.PRIMARY} onClick={() => sendFeedback()}>
              {sendLabel}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}

export default RevisionFeedbackModal
