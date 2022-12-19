import React from 'react'

import DQFCredentials from './DQFCredentials'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'
import {getUserApiKey} from '../../api/getUserApiKey'
import {createUserApiKey} from '../../api/createUserApiKey'
import {deleteUserApiKey} from '../../api/deleteUserApiKey'
import {connectedServicesGDrive} from '../../api/connectedServicesGDrive'
import {logoutUser as logoutUserApi} from '../../api/logoutUser'

class PreferencesModal extends React.Component {
  constructor(props) {
    super(props)

    this.state = {
      service: this.props.service,
      credentials: null,
    }

    getUserApiKey()
      .then((response) => {
        this.setState({
          credentials: response,
          credentialsCreated: false,
          credentialsCopied: false,
        })
      })
      .catch(() => {
        this.setState({
          credentials: null,
          credentialsCreated: false,
          credentialsCopied: false,
        })
      })
  }

  openResetPassword() {
    $('#modal').trigger('openresetpassword')
  }

  checkboxChange() {
    var self = this
    var selected = $(this.checkDrive).is(':checked')
    if (selected) {
      var url = config.gdriveAuthURL
      var newWindow = window.open(url, 'name', 'height=600,width=900')

      if (window.focus) {
        newWindow.focus()
      }
      var interval = setInterval(function () {
        if (newWindow.closed) {
          APP.USER.loadUserData().then(function () {
            var service = APP.USER.getDefaultConnectedService()
            if (service) {
              self.setState({
                service: service,
              })
            } else {
              $(self.checkDrive).attr('checked', false)
            }
          })
          clearInterval(interval)
        }
      }, 600)
    } else {
      if (APP.USER.STORE.connected_services.length) {
        this.disableGDrive().then((data) => {
          APP.USER.upsertConnectedService(data.connected_service)
          self.setState({
            service: APP.USER.getDefaultConnectedService(),
          })
        })
      }
    }
  }

  disableGDrive() {
    return connectedServicesGDrive(this.state.service.id)
  }

  logoutUser() {
    logoutUserApi().then(() => {
      if ($('body').hasClass('manage')) {
        location.href = config.hostpath + config.basepath
      } else {
        window.location.reload()
      }
    })
  }

  getDqfHtml() {
    if (config.dqf_enabled === 1) {
      return (
        <div className="dqf-container">
          <h2>DQF Credentials</h2>
          <DQFCredentials metadata={this.props.metadata} />
        </div>
      )
    }
  }

  generateKey() {
    createUserApiKey().then((response) => {
      this.setState({
        credentials: response,
        credentialsCreated: true,
      })
    })
  }

  confirmDelete() {
    this.setState({
      confirmDelete: true,
    })
  }

  deleteKey() {
    deleteUserApiKey().then(() => {
      this.setState({
        credentials: null,
        credentialsCreated: false,
        confirmDelete: false,
      })
    })
  }

  undoDelete() {
    this.setState({
      confirmDelete: false,
    })
  }

  copyToClipboard(e) {
    e.stopPropagation()
    navigator.clipboard.writeText(
      `${this.state.credentials.api_key}-${this.state.credentials.api_secret}`,
    )
    this.setState({
      credentialsCopied: true,
    })
  }

  getApiKeyHtml() {
    return (
      <div data-testid="preferences-modal">
        <h2>API Key</h2>
        {this.state.credentials ? (
          this.state.confirmDelete ? (
            <div className={'user-api'}>
              <div className={'user-api-text user-api-text-confirm-delete'}>
                <label>Are you sure you want to delete the token?</label>
                <label>This action cannot be undone.</label>
              </div>
              <div className={'user-api-buttons'}>
                <a className="btn-ok" onClick={() => this.deleteKey()}>
                  Delete
                </a>
                <a onClick={(e) => this.undoDelete(e)} className={'btn-cancel'}>
                  Cancel
                </a>
              </div>
            </div>
          ) : (
            <div
              className={
                'user-api ' +
                (this.state.credentialsCreated ? 'user-api-created' : '')
              }
            >
              <div className={'user-api-text'}>
                {this.state.credentialsCreated ? (
                  <>
                    <div>
                      <label>Api Key</label>
                      <input
                        type="text"
                        readOnly
                        value={this.state.credentials.api_key}
                        onFocus={(e) => e.target.select()}
                      />
                    </div>
                    <div>
                      <label>Api Secret</label>
                      <input
                        type="text"
                        readOnly
                        value={this.state.credentials.api_secret}
                        onFocus={(e) => e.target.select()}
                      />
                    </div>
                  </>
                ) : (
                  <textarea
                    ref={(keys) => (this.keys = keys)}
                    rows="1"
                    readOnly={true}
                    value={
                      this.state.credentials.api_key +
                      '-' +
                      this.state.credentials.api_secret
                    }
                  />
                )}
              </div>
              {this.state.credentialsCreated ? (
                <div className={'user-api-buttons'}>
                  <a
                    onClick={(e) => this.copyToClipboard(e)}
                    className={'btn-ok copy'}
                  >
                    <i className="icon-copy icon" />
                    {this.state.credentialsCopied ? 'Copied' : 'Copy'}
                  </a>
                  <a className="btn-ok" onClick={() => this.confirmDelete()}>
                    Delete
                  </a>
                </div>
              ) : (
                <div className={'user-api-buttons'}>
                  <a className="btn-ok" onClick={() => this.confirmDelete()}>
                    Delete
                  </a>
                </div>
              )}
              {this.state.credentialsCreated ? (
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
              <label>No API Key associated to your account</label>
            </div>
            <div className="user-api-buttons">
              <a className="btn-ok" onClick={() => this.generateKey()}>
                Generate
              </a>
            </div>
          </div>
        )}
      </div>
    )
  }

  render() {
    var gdriveMessage = ''
    if (this.props.showGDriveMessage) {
      gdriveMessage = (
        <div className="preference-modal-message">
          Connect your Google account to translate files in your Drive
        </div>
      )
    }

    var services_label = 'Allow Matecat to access your files on Google Drive'
    if (
      this.state.service &&
      (!this.state.service.disabled_at || !this.state.service.expired_at)
    ) {
      services_label =
        'Connected to Google Drive (' + this.state.service.email + ')'
    }
    var resetPasswordHtml = ''
    if (this.props.user.has_password) {
      resetPasswordHtml = (
        <a
          className="reset-password pull-left"
          onClick={this.openResetPassword.bind(this)}
        >
          Reset Password
        </a>
      )
    }

    let avatar = (
      <div className="avatar-user pull-left">{config.userShortName}</div>
    )
    if (this.props.metadata.gplus_picture) {
      avatar = (
        <div className="avatar-user pull-left">
          <img
            src={this.props.metadata.gplus_picture}
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
            <div className="onoffswitch-drive">
              <input
                type="checkbox"
                name="onoffswitch"
                defaultChecked={
                  this.state.service &&
                  (!this.state.service.disabled_at ||
                    !this.state.service.expired_at)
                }
                onChange={this.checkboxChange.bind(this)}
                ref={(input) => (this.checkDrive = input)}
                className="onoffswitch-checkbox"
                id="gdrive_check"
              />
              <label className="onoffswitch-label" htmlFor="gdrive_check">
                <span className="onoffswitch-inner" />
                <span className="onoffswitch-switch" />
                <span className="onoffswitch-label-status-active">ON</span>
                <span className="onoffswitch-label-status-inactive">OFF</span>
                <span className="onoffswitch-label-status-unavailable">
                  Unavailable
                </span>
              </label>
            </div>
            <label>{services_label}</label>
          </div>
        </div>
      )
    }

    return (
      <div className="preferences-modal">
        <div className="user-info-form">
          {avatar}
          <div className="user-name pull-left">
            <strong>
              {this.props.user.first_name} {this.props.user.last_name}
            </strong>
            <br />
            <span className="grey-txt">{this.props.user.email}</span>
            <br />
          </div>
          <br />
          <div className="user-link">
            <div
              id="logoutlink"
              className="pull-right"
              onClick={this.logoutUser.bind(this)}
            >
              Logout
            </div>
            {resetPasswordHtml}
          </div>
        </div>
        <div className="user-info-attributes">
          <div className="user-reset-password">{gdriveMessage}</div>
          {this.getApiKeyHtml()}
          {googleDrive}
          {this.getDqfHtml()}
        </div>
      </div>
    )
  }
}

const fieldValidations = [
  RuleRunner.ruleRunner('dqfUsername', 'Username', FormRules.requiredRule),
  RuleRunner.ruleRunner(
    'dqfPassword',
    'Password',
    FormRules.requiredRule,
    FormRules.minLength(8),
  ),
]

export default PreferencesModal
