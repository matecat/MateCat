import PropTypes from 'prop-types'
import React, {useRef, useState} from 'react'
import CommonUtils from '../../utils/commonUtils'
import {shareTmKey} from '../../api/shareTmKey'
import CatToolActions from '../../actions/CatToolActions'
import {Button, BUTTON_TYPE} from '../common/Button/Button'

const ShareTmModal = ({
  description,
  tmKey,
  user,
  users,
  callback,
  onClose,
  modalName,
}) => {
  const [errorEmailsResult, setErrorEmailsResult] = useState(false)
  const [errorEmails, setErrorEmails] = useState(null)
  const [errorApiCallResult, setErrorApiCallResult] = useState(false)
  const [errorApiCallMessage, setErrorApiCallMessage] = useState(null)

  const emailsRef = useRef(null)

  // eslint-disable-next-line no-unused-vars
  const allowHTML = (string) => {
    return {__html: string}
  }

  const shareTmKeyByEmail = () => {
    const emails = emailsRef.current.value
    const {result: validEmails, emails: errorEmails} =
      CommonUtils.validateEmailList(emails)
    if (!validEmails) {
      setErrorEmailsResult(true)
      setErrorEmails(errorEmails)
    } else {
      shareTmKey({
        key: tmKey,
        description: description,
        emails: emails,
      })
        .then(() => {
          CatToolActions.addNotification({
            title: 'Resource shared',
            type: 'success',
            text: `The resource has been shared.`,
            position: 'br',
            allowHtml: true,
            timer: 5000,
          })
          callback.call()
          onClose()
        })
        .catch((errors) => {
          if (errors && errors.length > 0) {
            setErrorApiCallResult(true)
            setErrorApiCallMessage(errors[0].message)
          }
        })
    }
  }

  const onKeyUp = (e) => {
    setErrorEmailsResult(false)
    setErrorEmails(null)
    setErrorApiCallResult(false)
    setErrorApiCallMessage(null)
    if (e.key === 'Enter') {
      shareTmKeyByEmail()
    }
  }

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
        <div className={'modal-grid ' + modalName}>
          <div className="modal-grid__body" style={{fontSize: '18px'}}>
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
                <div className="share-popup-container-top">
                  <input
                    className={`share-popup-container-input-email ${
                      errorEmailsResult || errorApiCallResult ? 'error' : ''
                    }`}
                    placeholder="Enter email addresses separated by comma"
                    ref={emailsRef}
                    onKeyUp={(e) => onKeyUp(e)}
                  />
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    onClick={() => shareTmKeyByEmail()}
                  >
                    Share
                  </Button>
                </div>
                <div className="share-popup-input-result">
                  {errorEmailsResult && (
                    <p>
                      The email{' '}
                      <span style={{fontWeight: 'bold'}}>{errorEmails}</span> is
                      not valid.
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
ShareTmModal.propTypes = {
  description: PropTypes.string,
  tmKey: PropTypes.string,
  user: PropTypes.object,
  users: PropTypes.array,
  callback: PropTypes.func,
}

export default ShareTmModal
