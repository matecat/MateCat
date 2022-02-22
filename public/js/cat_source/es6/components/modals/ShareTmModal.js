import PropTypes from 'prop-types'
import React from 'react'
import CommonUtils from '../../utils/commonUtils'
import {shareTmKey} from '../../api/shareTmKey'

class ShareTmModal extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      errorEmailsResult: false,
      errorEmails: null,
      errorApiCallResult: false,
      errorApiCallMessage: null,
    }
  }
  allowHTML(string) {
    return {__html: string}
  }

  onKeyUp(e) {
    this.setState({
      errorEmailsResult: false,
      errorEmails: null,
      errorApiCallResult: false,
      errorApiCallMessage: null,
    })
    if (e.key === 'Enter') {
      this.shareTmKeyByEmail()
    }
  }

  shareTmKeyByEmail() {
    const {tmKey, callback} = this.props
    const emails = this.emails.value
    const {result: validEmails, emails: errorEmails} =
      CommonUtils.validateEmailList(emails)
    if (!validEmails) {
      this.setState({
        errorEmailsResult: true,
        errorEmails: errorEmails,
      })
    } else {
      shareTmKey({
        key: tmKey,
        emails: emails,
      })
        .then(() => {
          callback.call()
          this.props.onClose()
        })
        .catch((errors) => {
          if (errors && errors.length > 0) {
            this.setState({
              errorApiCallResult: true,
              errorApiCallMessage: errors[0].message,
            })
          }
        })
    }
  }

  render() {
    const {user, users, description, tmKey} = this.props
    const {
      errorEmailsResult,
      errorEmails,
      errorApiCallResult,
      errorApiCallMessage,
    } = this.state
    const htmlUsersList = []
    htmlUsersList.push(
      <div className="share-popup-list-item" key={user.uid}>
        <span className="share-popup-item-name">
          {user.first_name} {user.last_name}(you)
        </span>
        <span className="share-popup-item-email">{user.email}</span>
      </div>,
    )
    users.forEach(function (item) {
      htmlUsersList.push(
        <div className="share-popup-list-item" key={item.uid}>
          <span className="share-popup-item-name">
            {item.first_name} {item.last_name}
          </span>
          <span className="share-popup-item-email">{item.email}</span>
        </div>,
      )
    })
    return (
      <div className="message-modal">
        <div className="matecat-modal-middle">
          <div className={'ui one column grid ' + this.props.modalName}>
            <div className="column left aligned" style={{fontSize: '18px'}}>
              <div className="share-popup-container">
                <div className="share-popup-top">
                  <p className="popup-tm pull-left">
                    Share ownership of the resource: <br />
                    <span className="share-popup-description">
                      {description}
                      {' - '}
                    </span>
                    <span className="share-popup-key">{tmKey}</span>
                  </p>
                </div>
                <div className="share-popup-container-bottom">
                  <p>This action cannot be undone.</p>
                  <div className="share-popup-copy-result" />
                  <input
                    className={`share-popup-container-input-email ${
                      errorEmailsResult || errorApiCallResult ? 'error' : ''
                    }`}
                    placeholder="Enter email addresses separated by comma"
                    ref={(input) => (this.emails = input)}
                    onKeyUp={(e) => this.onKeyUp(e)}
                  />
                  <button
                    className="ui primary button right floated"
                    onClick={() => this.shareTmKeyByEmail()}
                  >
                    Share
                  </button>

                  <div className="share-popup-input-result">
                    {errorEmailsResult && (
                      <p>
                        The email{' '}
                        <span style={{fontWeight: 'bold'}}>{errorEmails}</span>{' '}
                        is not valid.
                      </p>
                    )}
                    {errorApiCallResult && <p>{errorApiCallMessage}</p>}
                  </div>
                </div>
              </div>

              <div className="share-popup-container-list">
                <h3 className="popup-tm">Who owns the resource</h3>

                <div className="share-popup-list">{htmlUsersList}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }
}
ShareTmModal.propTypes = {
  description: PropTypes.string,
  tmKey: PropTypes.string,
  user: PropTypes.object,
  users: PropTypes.array,
  callback: PropTypes.func,
}

export default ShareTmModal
