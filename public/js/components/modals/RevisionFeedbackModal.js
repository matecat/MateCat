import React from 'react'

import CatToolActions from '../../actions/CatToolActions'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

class RevisionFeedbackModal extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      sending: false,
      feedback: this.props.feedback,
      buttonEnabled: false,
    }
  }

  sendFeedback() {
    this.setState({
      sending: true,
    })
    CatToolActions.sendRevisionFeedback(this.state.feedback)
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

  onChange = (e) => {
    let value = e.target.value
    if (value !== '') {
      this.setState({
        feedback: value,
        buttonEnabled: true,
      })
    } else {
      this.setState({
        feedback: value,
        buttonEnabled: false,
      })
    }
  }

  render() {
    let sendLabel = this.props.feedback ? 'Modify' : 'Submit'
    return (
      <div className="feedback-modal">
        <div className="matecat-modal-top">
          <h1>Leave your feedback</h1>
        </div>
        <div className="matecat-modal-middle">
          <div className="matecat-modal-text">
            {this.props.revisionNumber === 1 ? (
              <span>
                Please leave some feedback for the translator on the job
                quality.
              </span>
            ) : (
              <span>
                Please leave some feedback for the reviser on the job quality.
              </span>
            )}
          </div>
          <div className="matecat-modal-textarea">
            <textarea
              value={this.state.feedback}
              style={{width: '100%', height: '100px', resize: 'none'}}
              placeholder="Leave your feedback here"
              onChange={this.onChange}
            />
          </div>
        </div>
        <div className="matecat-modal-bottom">
          <div className="modal-buttons">
            <Button
              type={BUTTON_TYPE.DEFAULT}
              onClick={() => ModalsActions.onCloseModal()}
            >
              {this.props.feedback ? 'Close' : "I'll do it later"}
            </Button>

            {this.state.sending ? (
              <Button type={BUTTON_TYPE.PRIMARY} disabled={true}>
                <span className="button-loader show" style={{left: '280px'}} />
                {sendLabel}
              </Button>
            ) : !this.state.buttonEnabled ? (
              <Button type={BUTTON_TYPE.PRIMARY} disabled={true}>
                {sendLabel}
              </Button>
            ) : (
              <Button
                type={BUTTON_TYPE.PRIMARY}
                onClick={() => this.sendFeedback()}
              >
                {sendLabel}
              </Button>
            )}
          </div>
        </div>
      </div>
    )
  }
}

export default RevisionFeedbackModal
