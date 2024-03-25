import PropTypes from 'prop-types'
import update from 'immutability-helper'
import {size} from 'lodash'
import React from 'react'

import TextField from '../common/TextField'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'
import {checkRedeemProject as checkRedeemProjectApi} from '../../api/checkRedeemProject'
import {loginUser} from '../../api/loginUser'
import ModalsActions from '../../actions/ModalsActions'
import ForgotPasswordModal from './ForgotPasswordModal'
import CatToolActions from '../../actions/CatToolActions'
import {
  GOOGLE_LOGIN_LOCAL_STORAGE,
  GOOGLE_LOGIN_NOTIFICATION,
} from '../../hooks/useGoogleLoginNotification'

class LoginModal extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      showErrors: false,
      validationErrors: {},
      generalError: '',
      requestRunning: false,
    }
    this.state.validationErrors = RuleRunner.run(this.state, fieldValidations)
    this.handleFieldChanged = this.handleFieldChanged.bind(this)
    this.handleSubmitClicked = this.handleSubmitClicked.bind(this)
    this.sendLoginData = this.sendLoginData.bind(this)
    this.errorFor = this.errorFor.bind(this)
    this.timerLoginAttempts
  }

  // TODO: find a way to abstract this into the plugin
  otherServiceLogin() {
    let url = config.pluggable.other_service_auth_url
    let self = this
    this.checkRedeemProject()
    let newWindow = window.open(url, 'name', 'height=900,width=900')
    if (window.focus) {
      newWindow.focus()
    }
    let interval = setInterval(function () {
      if (newWindow.closed) {
        clearInterval(interval)
        let loc
        if (self.props.goToManage) {
          window.location = '/manage/'
        } else if ((loc = window.localStorage.getItem('wanted_url'))) {
          window.localStorage.removeItem('wanted_url')
          window.location.href = loc
        } else {
          window.location.reload()
        }
      }
    }, 600)
  }

  googole_popup() {
    // TODO: Remove temp notification warning login google (search in files this todo)
    this.showNotificationGoogleLogin()

    let url = this.props.googleUrl
    let self = this
    this.checkRedeemProject()
    let newWindow = window.open(url, 'name', 'height=600,width=900')
    if (window.focus) {
      newWindow.focus()
    }
    let interval = setInterval(function () {
      if (newWindow.closed) {
        clearInterval(interval)
        let loc
        if (self.props.goToManage) {
          window.location = '/manage/'
        } else if ((loc = window.localStorage.getItem('wanted_url'))) {
          window.localStorage.removeItem('wanted_url')
          window.location.href = loc
        } else {
          window.location.reload()
        }
      }
    }, 600)
  }

  handleFieldChanged(field) {
    return (e) => {
      let newState = update(this.state, {
        [field]: {$set: e.target.value},
      })
      newState.validationErrors = RuleRunner.run(newState, fieldValidations)
      newState.generalError = ''
      this.setState(newState)
    }
  }

  showErrorWithTimer(time) {
    this.setState({
      requestRunning: true,
    })
    clearInterval(this.timerLoginAttempts)
    this.timerLoginAttempts = setInterval(() => {
      time--
      if (time === 0) {
        clearInterval(this.timerLoginAttempts)
        this.setState({
          requestRunning: false,
          showErrors: false,
          generalError: '',
        })
      } else {
        let text = `Too many attempts, please retry in ${time} seconds`
        this.setState({
          generalError: text,
          requestRunning: true,
        })
      }
    }, 1000)
  }

  handleSubmitClicked() {
    this.setState({showErrors: true})
    if ($.isEmptyObject(this.state.validationErrors) == false) return null
    if (this.state.requestRunning) {
      return false
    }
    this.setState({requestRunning: true})
    this.checkRedeemProject().then(
      this.sendLoginData()
        .then(() => {
          if (this.props.goToManage) {
            window.location = '/manage/'
          } else {
            window.location.reload()
          }
        })
        .catch((response) => {
          if (response.status === 429) {
            const time = response.headers.get('Retry-After')
            this.showErrorWithTimer(time)
            return
          }
          const text = 'Login failed.'
          this.setState({
            generalError: text,
            requestRunning: false,
          })
        }),
    )
  }

  checkRedeemProject() {
    if (this.props.redeemMessage) {
      return checkRedeemProjectApi()
    } else {
      return Promise.resolve()
    }
  }

  sendLoginData() {
    return loginUser(this.state.emailAddress, this.state.password)
  }

  errorFor(field) {
    return this.state.validationErrors[field]
  }

  openRegisterModal() {
    APP.openRegisterModal()
  }

  openForgotPassword() {
    let props = {}
    if (config.showModalBoxLogin == 1) {
      props.redeemMessage = true
    }
    const style = {
      width: '577px',
    }
    ModalsActions.showModalComponent(
      ForgotPasswordModal,
      props,
      'Forgot Password',
      style,
    )
  }

  googleLoginButton() {
    if (!(config.pluggable && config.pluggable.auth_disable_google)) {
      return (
        <a
          className="google-login-button btn-confirm-medium"
          onClick={this.googole_popup.bind(this)}
        />
      )
    }
  }

  otherServiceLoginButton() {
    if (config.pluggable && config.pluggable.other_service_auth_url) {
      return (
        <a
          className="btn-confirm-medium"
          onClick={this.otherServiceLogin.bind(this)}
        >
          {config.pluggable.other_service_button_label}
        </a>
      )
    }
  }

  loginFormContainerCode() {
    if (!(config.pluggable && config.pluggable.auth_disable_email)) {
      let generalErrorHtml = ''
      let buttonSignInClass =
        size(this.state.validationErrors) === 0 ? '' : 'disabled'
      if (this.state.generalError.length) {
        generalErrorHtml = (
          <div style={{color: 'red', fontSize: '14px'}} className="text">
            {this.state.generalError}
          </div>
        )
      }

      let loaderClass = this.state.requestRunning ? 'show' : ''
      return (
        <div className="login-form-container">
          <div className="form-divider">
            <div className="divider-line"></div>
            <span>OR</span>
            <div className="divider-line"></div>
          </div>
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('emailAddress')}
            placeholder="Email"
            name="emailAddress"
            errorText={this.errorFor('emailAddress')}
            tabindex={1}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <TextField
            type="password"
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('password')}
            placeholder="Password"
            name="password"
            errorText={this.errorFor('password')}
            tabindex={2}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <a
            className={
              'login-button btn-confirm-medium sing-in ' + buttonSignInClass
            }
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
            onClick={this.handleSubmitClicked.bind()}
            tabIndex={3}
          >
            <span className={'button-loader ' + loaderClass} /> Sign in{' '}
          </a>
          {generalErrorHtml}
          <br />
          <span className="forgot-password" onClick={this.openForgotPassword}>
            Forgot password?
          </span>
        </div>
      )
    }
  }

  showNotificationGoogleLogin() {
    const googleNotification = localStorage.getItem(GOOGLE_LOGIN_LOCAL_STORAGE)
    if (!googleNotification) {
      localStorage.setItem(GOOGLE_LOGIN_LOCAL_STORAGE, 'true')
      CatToolActions.addNotification(GOOGLE_LOGIN_NOTIFICATION)
    }
  }

  render() {
    let htmlMessage = (
      <div className="login-container-right">
        <h2>Sign up now to:</h2>
        <ul className="">
          <li>Manage your TMs, glossaries and MT engines</li>
          <li>Access the management panel</li>
          <li>Translate Google Drive files</li>
        </ul>
        <a
          className="register-button btn-confirm-medium"
          onClick={this.openRegisterModal}
        >
          Sign up
        </a>
      </div>
    )

    if (this.props.redeemMessage) {
      htmlMessage = (
        <div className="login-container-right">
          <h2 style={{fontSize: '21px'}}>
            Sign up or sign in to add the project to your management panel and:
          </h2>
          <ul className="add-project-manage">
            <li>Track the progress of your translations</li>
            <li>Monitor the activity for increased security</li>
            <li>Manage TMs, MT and glossaries</li>
          </ul>
          <a
            className="register-button btn-confirm-medium sing-up"
            onClick={this.openRegisterModal}
          >
            Sign up
          </a>
        </div>
      )
    }
    return (
      <div className="login-modal">
        {htmlMessage}
        <div className="login-container-left">
          {this.otherServiceLoginButton()}

          {this.googleLoginButton()}

          {this.loginFormContainerCode()}
        </div>
      </div>
    )
  }
}

const fieldValidations = [
  RuleRunner.ruleRunner(
    'emailAddress',
    'Email address',
    FormRules.requiredRule,
    FormRules.checkEmail,
  ),
]

LoginModal.propTypes = {
  googleUrl: PropTypes.string,
}

export default LoginModal
