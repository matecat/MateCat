import React, {useRef, useState, useEffect} from 'react'
import PropTypes from 'prop-types'
import {shareTmKey} from '../../../../api/shareTmKey'
import CommonUtils from '../../../../utils/commonUtils'

import Close from '../../../../../img/icons/Close'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import ModalsActions from '../../../../actions/ModalsActions'
import ShareTmModal from '../../../modals/ShareTmModal'
import CatToolActions from '../../../../actions/CatToolActions'
import UserStore from '../../../../stores/UserStore'
import {
  Button,
  BUTTON_HTML_TYPE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../../common/Button/Button'

export const ShareResource = ({row, onClose, onShare}) => {
  const [emails, setEmails] = useState('')
  const [status, setStatus] = useState()
  const [sharedUsers, setSharedUsers] = useState()

  const formRef = useRef()
  const userInfo = UserStore.getUser()

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
        description: row.name,
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
          onShare()
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
      user: userInfo.user,
      users: sharedUsers,
      callback: onClose,
    }
    ModalsActions.showModalComponent(ShareTmModal, props, 'Share resource')
  }

  useEffect(() => {
    getInfoTmKey({key: row.key, description: row.name}).then((response) => {
      const users = response.data
      if (users.length > 1)
        setSharedUsers(
          users.filter((user) => parseInt(user.uid) !== userInfo.user.uid),
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
          <Button
            type={BUTTON_TYPE.PRIMARY}
            size={BUTTON_SIZE.SMALL}
            htmlType={BUTTON_HTML_TYPE.SUBMIT}
            disabled={isFormDisabled || isErrorExport}
          >
            Share
          </Button>

          <Button
            type={BUTTON_TYPE.WARNING}
            size={BUTTON_SIZE.ICON_SMALL}
            htmlType={BUTTON_HTML_TYPE.RESET}
            disabled={isFormDisabled}
          >
            <Close size={18} />
          </Button>
        </div>
      </form>
    </div>
  )
}

ShareResource.propTypes = {
  row: PropTypes.object.isRequired,
  onClose: PropTypes.func,
}
