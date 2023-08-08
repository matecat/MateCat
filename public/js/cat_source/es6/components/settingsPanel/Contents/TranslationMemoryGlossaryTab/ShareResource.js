import React, {useRef, useState, useContext, useEffect} from 'react'
import PropTypes from 'prop-types'
import {TranslationMemoryGlossaryTabContext} from './TranslationMemoryGlossaryTab'
import {shareTmKey} from '../../../../api/shareTmKey'
import CommonUtils from '../../../../utils/commonUtils'

import Close from '../../../../../../../img/icons/Close'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import ModalsActions from '../../../../actions/ModalsActions'
import ShareTmModal from '../../../modals/ShareTmModal'

export const ShareResource = ({row, onClose}) => {
  const {setNotification} = useContext(TranslationMemoryGlossaryTabContext)

  const [emails, setEmails] = useState('')
  const [status, setStatus] = useState()
  const [sharedUsers, setSharedUsers] = useState()

  const formRef = useRef()

  const onChange = (e) => {
    setStatus(undefined)
    setNotification({})
    if (e.currentTarget.value) setEmails(e.currentTarget.value)
  }

  const onSubmit = (event) => {
    const validation = CommonUtils.validateEmailList(emails)

    if (!validation.result) {
      const errorsObject = {
        message: `The email ${emails} is not valid.`,
      }

      setNotification({
        type: 'error',
        message: errorsObject.message,
        rowKey: row.key,
      })
      setStatus({errors: errorsObject})
    } else {
      shareTmKey({
        key: row.key,
        emails: emails,
      })
        .then(() => {
          onClose()
          setNotification({
            type: 'success',
            message: `The resource <b>${row.key}</b> has been shared.`,
            rowKey: row.key,
          })
          setStatus({successfull: true})
          setTimeout(() => setNotification({}), 3000)
        })
        .catch((errors) => {
          const errorsObject = errors?.[0]
            ? errors[0]
            : {
                message:
                  'There was a problem sharing the key, try again or contact the support.',
              }

          setNotification({
            type: 'error',
            message: errorsObject.message,
            rowKey: row.key,
          })
          setStatus({errors: errorsObject})
        })
    }

    event.preventDefault()
  }

  const onReset = () => {
    setEmails(config.userMail)
    setStatus(undefined)
    setNotification({})

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
            className="ui primary button settings-panel-button-icon tm-key-small-row-button"
            disabled={isFormDisabled || isErrorExport}
          >
            Share
          </button>

          <button
            type="reset"
            className="ui button orange tm-key-small-row-button"
            disabled={isFormDisabled}
          >
            <Close />
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
