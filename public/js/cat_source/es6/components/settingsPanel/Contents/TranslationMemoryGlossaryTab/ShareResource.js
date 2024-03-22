import React, {useRef, useState, useEffect} from 'react'
import PropTypes from 'prop-types'
import {shareTmKey} from '../../../../api/shareTmKey'
import CommonUtils from '../../../../utils/commonUtils'

import Close from '../../../../../../../img/icons/Close'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import ModalsActions from '../../../../actions/ModalsActions'
import ShareTmModal from '../../../modals/ShareTmModal'
import CatToolActions from '../../../../actions/CatToolActions'

export const ShareResource = ({row, onClose}) => {
  const [emails, setEmails] = useState('')
  const [status, setStatus] = useState()
  const [sharedUsers, setSharedUsers] = useState()

  const formRef = useRef()

  const onChange = (e) => {
    setStatus(undefined)
    if (e.currentTarget.value) setEmails(e.currentTarget.value)
  }

  const onSubmit = (event) => {
    const validation = CommonUtils.validateEmailList(emails)

    if (!validation.result) {
      const errorsObject = {
        message: `The email ${emails} is not valid.`,
      }
      CatToolActions.addNotification({
        title: 'Error sharing resource',
        type: 'error',
        text: errorsObject.message,
        position: 'br',
        allowHtml: true,
        timer: 5000,
      })
      setStatus({errors: errorsObject})
    } else {
      shareTmKey({
        key: row.key,
        emails: emails,
      })
        .then(() => {
          onClose()
          CatToolActions.addNotification({
            title: 'Resource shared',
            type: 'success',
            text: `The resource <b>${row.name}</b> has been shared.`,
            position: 'br',
            allowHtml: true,
            timer: 5000,
          })
          setStatus({successfull: true})
        })
        .catch((errors) => {
          const errorsObject = errors?.[0]
            ? errors[0]
            : {
                message:
                  'There was a problem sharing the key, try again or contact the support.',
              }
          CatToolActions.addNotification({
            title: 'Error sharing resource',
            type: 'error',
            text: errorsObject.message,
            position: 'br',
            timer: 5000,
          })
          setStatus({errors: errorsObject})
        })
    }

    event.preventDefault()
  }

  const onReset = () => {
    setEmails(config.userMail)
    setStatus(undefined)
    onClose()
  }

  const isFormDisabled = false
  const isErrorExport = status && status.errors

  const openShareTmModal = () => {
    const props = {
      description: row.description,
      tmKey: row.key,
      user: APP.USER.STORE.user,
      users: sharedUsers,
      callback: onClose,
    }
    ModalsActions.showModalComponent(ShareTmModal, props, 'Share resource')
  }

  useEffect(() => {
    getInfoTmKey({key: row.key}).then((response) => {
      const users = response.data
      if (users.length > 1)
        setSharedUsers(
          users.filter(
            (user) => parseInt(user.uid) !== APP.USER.STORE.user.uid,
          ),
        )
    })
  }, [])

  return (
    <div className="translation-memory-glossary-tab-export">
      <form
        ref={formRef}
        className={`action-form${isErrorExport ? ' action-form-error' : ''}`}
        onSubmit={onSubmit}
        onReset={onReset}
      >
        <div>
          {!sharedUsers ? (
            <span>
              Share ownership of the resource by sharing the key. This action
              cannot be undone.
            </span>
          ) : (
            <div>
              <span>Shared resource is co-owned by you, </span>
              <span
                className="message-share-tmx-email"
                onClick={() => openShareTmModal()}
              >
                {sharedUsers[0].first_name} {sharedUsers[0].last_name}{' '}
                {sharedUsers.length > 1
                  ? `and ${sharedUsers.length - 1} others`
                  : ''}
              </span>
            </div>
          )}

          <input
            type="text"
            className="translation-memory-glossary-tab-input-text"
            placeholder="Enter email addresses separated by comma"
            required
            value={emails}
            onChange={onChange}
            disabled={isFormDisabled}
          />
        </div>
        <div className="translation-memory-glossary-tab-buttons-group align-center">
          <button
            type="submit"
            className="ui primary button settings-panel-button-icon confirm-button"
            disabled={isFormDisabled || isErrorExport}
          >
            Share
          </button>

          <button
            type="reset"
            className="ui button orange close-button"
            disabled={isFormDisabled}
          >
            <Close size={18} />
          </button>
        </div>
      </form>
    </div>
  )
}

ShareResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
