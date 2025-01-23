import React, {useState, useEffect, useRef, useContext} from 'react'

import Switch from '../common/Switch'
import {getUserApiKey} from '../../api/getUserApiKey'
import {createUserApiKey} from '../../api/createUserApiKey'
import {connectedServicesGDrive} from '../../api/connectedServicesGDrive'
import {deleteUserApiKey} from '../../api/deleteUserApiKey'
import IconEdit from '../icons/IconEdit'
import {modifyUserInfo} from '../../api/modifyUserInfo/modifyUser'
import UserActions from '../../actions/UserActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import IconClose from '../icons/IconClose'
import UserStore from '../../stores/UserStore'
import {getUserData} from '../../api/getUserData'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import ModalsActions from '../../actions/ModalsActions'
import useAuth from '../../hooks/useAuth'

const PreferencesModal = (props) => {
  const {userInfo, setUserInfo, logout} = useContext(ApplicationWrapperContext)

  const {user, metadata} = userInfo
  const serviceInfo = UserStore.getDefaultConnectedService()

  const [service, setService] = useState(serviceInfo)
  const [credentials, setCredentials] = useState(null)
  const [driveActive, setDriveActive] = useState(
    service && (!service.disabled_at || !service.expired_at),
  )
  const [credentialsCreated, setCredentialsCreated] = useState(false)
  const [credentialsCopied, setCredentialsCopied] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [modifyUser, setModifyUser] = useState(false)
  const [firstName, setFirstName] = useState(user.first_name)
  const [lastName, setLastName] = useState(user.last_name)

  const inputName = useRef()

  useEffect(() => {
    getUserApiKey()
      .then((response) => {
        setCredentials(response)
      })
      .catch(() => {
        setCredentials(null)
      })
  }, [])

  const openResetPassword = () => {
    ModalsActions.openResetPassword()
  }

  const checkboxChange = (selected) => {
    if (selected) {
      setDriveActive(true)

      const url = config.gdriveAuthURL
      const newWindow = window.open(url, 'name', 'height=600,width=900')

      if (window.focus) {
        newWindow.focus()
      }

      let interval = setInterval(() => {
        if (newWindow.closed) {
          getUserData().then((data) => {
            UserActions.updateUser(data)
            const updatedService = UserStore.getDefaultConnectedService()
            if (updatedService) {
              setService(updatedService)
              setDriveActive(true)
            } else {
              setDriveActive(false)
            }
          })
          clearInterval(interval)
        }
      }, 600)
    } else {
      setDriveActive(false)

      if (userInfo.connected_services.length) {
        disableGDrive().then((data) => {
          const connectedServices = UserStore.updateConnectedService(
            data.connected_service,
          )
          const defaultService = connectedServices.find((item) => {
            return item.is_default
          })
          setService(defaultService)
        })
      }
    }
  }

  const disableGDrive = () => {
    return connectedServicesGDrive(service.id)
  }

  const logoutFn = () => {
    logout()
  }

  const generateKey = () => {
    createUserApiKey().then((response) => {
      setCredentials(response)
      setCredentialsCreated(true)
    })
  }

  const confirmDeleteHandler = () => {
    setConfirmDelete(true)
  }

  const deleteKey = () => {
    deleteUserApiKey().then(() => {
      setCredentials(null)
      setCredentialsCreated(false)
      setConfirmDelete(false)
    })
  }

  const undoDelete = () => {
    setConfirmDelete(false)
  }

  const copyToClipboard = (e) => {
    e.stopPropagation()
    navigator.clipboard.writeText(
      `${credentials.api_key}-${credentials.api_secret}`,
    )
    setCredentialsCopied(true)
  }

  const modifyUserDetails = () => {
    modifyUserInfo(firstName, lastName).then(() => {
      console.log('saved')
    })
    setModifyUser(false)
    setUserInfo((prevState) => ({
      ...prevState,
      user: {...prevState.user, first_name: firstName, last_name: lastName},
    }))
  }

  const getApiKeyHtml = () => {
    return (
      <div data-testid="preferences-modal">
        <h2>API Key</h2>
        {credentials ? (
          confirmDelete ? (
            <div className={'user-api'}>
              <div className={'user-api-text user-api-text-confirm-delete'}>
                <label>Are you sure you want to delete the token?</label>
                <label>This action cannot be undone.</label>
              </div>
              <div className={'user-api-buttons'}>
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  size={BUTTON_SIZE.MEDIUM}
                  onClick={() => deleteKey()}
                  tabIndex="0"
                >
                  Delete
                </Button>
                <Button
                  mode={BUTTON_MODE.OUTLINE}
                  size={BUTTON_SIZE.MEDIUM}
                  onClick={(e) => undoDelete(e)}
                  className={'btn-cancel'}
                  tabIndex="0"
                >
                  Cancel
                </Button>
              </div>
            </div>
          ) : (
            <div
              className={
                'user-api ' + (credentialsCreated ? 'user-api-created' : '')
              }
            >
              <div className={'user-api-text'}>
                {credentialsCreated ? (
                  <>
                    <div>
                      <label>Api Key</label>
                      <input
                        type="text"
                        readOnly
                        value={credentials.api_key}
                        onFocus={(e) => e.target.select()}
                      />
                    </div>
                    <div>
                      <label>Api Secret</label>
                      <input
                        type="text"
                        readOnly
                        value={credentials.api_secret}
                        onFocus={(e) => e.target.select()}
                      />
                    </div>
                  </>
                ) : (
                  <textarea
                    rows="1"
                    readOnly={true}
                    tabIndex={-1}
                    value={credentials.api_key + '-' + credentials.api_secret}
                  />
                )}
              </div>
              {credentialsCreated ? (
                <div className={'user-api-buttons'}>
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    size={BUTTON_SIZE.MEDIUM}
                    onClick={(e) => copyToClipboard(e)}
                    tabIndex="0"
                  >
                    <i className="icon-copy icon" />
                    {credentialsCopied ? 'Copied' : 'Copy'}
                  </Button>
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    size={BUTTON_SIZE.MEDIUM}
                    onClick={() => confirmDeleteHandler()}
                    tabIndex="0"
                  >
                    Delete
                  </Button>
                </div>
              ) : config.isAnInternalUser ? (
                <div className="user-api-buttons">
                  <Button
                    type={BUTTON_TYPE.PRIMARY}
                    size={BUTTON_SIZE.MEDIUM}
                    onClick={() => confirmDeleteHandler()}
                    tabIndex="0"
                  >
                    Delete
                  </Button>
                </div>
              ) : (
                <div className="user-api-message">
                  <div className={'user-api-message-content'}>
                    <label>
                      An API key associated to your account is already existing.
                      If you need a new one, please{' '}
                      <a
                        href="mailto:support@matecat.com"
                        className="email-link"
                        rel="noreferrer"
                        target="_blank"
                      >
                        contact us
                      </a>
                      .
                    </label>
                  </div>
                </div>
              )}
              {credentialsCreated ? (
                <div className={'user-api-message'}>
                  <i className="icon-info3 icon" />
                  <div className={'user-api-message-content'}>
                    This is the only time that the secret access key can be
                    viewed or copied. You cannot recover it later. However, you
                    can delete and create new access keys at any time.
                  </div>
                </div>
              ) : null}
            </div>
          )
        ) : (
          <div className="user-api">
            <div className={'user-api-text'}>
              {config.isAnInternalUser ? (
                <label>No API Key associated to your account</label>
              ) : (
                <label>
                  There is no API key associated to your account. If you need
                  one, please{' '}
                  <a
                    href="mailto:support@matecat.com"
                    className="email-link"
                    rel="noreferrer"
                    target="_blank"
                  >
                    contact us
                  </a>
                  .
                </label>
              )}
            </div>
            <div className="user-api-buttons">
              {config.isAnInternalUser ? (
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  size={BUTTON_SIZE.MEDIUM}
                  onClick={() => generateKey()}
                  tabIndex="0"
                >
                  Generate
                </Button>
              ) : null}
            </div>
          </div>
        )}
      </div>
    )
  }

  let gdriveMessage = ''
  if (props.showGDriveMessage) {
    gdriveMessage = (
      <div className="preference-modal-message">
        Connect your Google account to translate files in your Drive
      </div>
    )
  }

  let services_label = 'Allow Matecat to access your files on Google Drive'
  if (service && !service.disabled_at && !service.expired_at) {
    services_label = `Connected to Google Drive (${service.email})`
  }

  let resetPasswordHtml = ''
  if (user.has_password) {
    resetPasswordHtml = (
      <a className="reset-password pull-left" onClick={openResetPassword}>
        Reset Password
      </a>
    )
  }

  let avatar = (
    <div className="avatar-user pull-left">{config.userShortName}</div>
  )
  if (metadata && metadata[`${metadata.oauth_provider}_picture`]) {
    avatar = (
      <div className="avatar-user pull-left">
        <img
          src={metadata[`${metadata.oauth_provider}_picture`]}
          style={{width: '48px'}}
        />
      </div>
    )
  }

  let googleDrive = null
  if (config.googleDriveEnabled) {
    googleDrive = (
      <div>
        <h2>Google Drive</h2>
        <div className="user-gdrive">
          <Switch
            name="onoffswitch"
            activeText="ON"
            inactiveText="OFF"
            onChange={(selected) => {
              checkboxChange(selected)
            }}
            active={driveActive}
          />
          <label>{services_label}</label>
        </div>
      </div>
    )
  }
  useEffect(() => {
    modifyUser && inputName.current.focus()
  }, [modifyUser])
  return (
    <div className="preferences-modal">
      <div className="user-info-form">
        {avatar}
        <div className="user-name pull-left">
          {modifyUser ? (
            <div className={'user-info-details user-info-modify'}>
              <input
                ref={inputName}
                value={firstName}
                onKeyUp={(e) => e.key === 'Enter' && modifyUserDetails()}
                onChange={(e) => setFirstName(e.target.value)}
              />
              <input
                value={lastName}
                onKeyUp={(e) => e.key === 'Enter' && modifyUserDetails()}
                onChange={(e) => setLastName(e.target.value)}
              />
              <div className="user-info-modify-buttons">
                <Button
                  type={BUTTON_TYPE.PRIMARY}
                  size={BUTTON_SIZE.MEDIUM}
                  onClick={modifyUserDetails}
                  tabIndex={0}
                  disabled={!firstName || !lastName}
                >
                  Confirm
                </Button>
                <Button
                  type={BUTTON_TYPE.WARNING}
                  size={BUTTON_SIZE.ICON_STANDARD}
                  tabIndex="0"
                  onClick={() => {
                    setFirstName(user.first_name)
                    setLastName(user.last_name)
                    setModifyUser(false)
                  }}
                >
                  <IconClose />
                </Button>
              </div>
            </div>
          ) : (
            <div className="user-info-details">
              <strong>
                {firstName} {lastName}
              </strong>
              <div
                className="user-info-icon-update"
                onClick={() => {
                  setModifyUser(true)
                }}
              >
                <IconEdit />
              </div>
            </div>
          )}
          <span className="grey-txt">{user.email}</span>
          <br />
        </div>
        <br />
        <div className="user-link">
          {resetPasswordHtml}
          <div id="logoutlink" className="pull-right" onClick={logoutFn}>
            Logout
          </div>
        </div>
      </div>
      <div className="user-info-attributes">
        <div className="user-reset-password">{gdriveMessage}</div>
        {getApiKeyHtml()}
        {googleDrive}
      </div>
    </div>
  )
}

export default PreferencesModal
