import update from 'immutability-helper'
import React from 'react'

import TextField from '../common/TextField'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'
import {checkRedeemProject as checkRedeemProjectApi} from '../../api/checkRedeemProject'
import {registerUser} from '../../api/registerUser'
import CommonUtils from '../../utils/commonUtils'
import ModalsActions from '../../actions/ModalsActions'
import ConfirmRegister from './ConfirmRegister'

const PASSWORD_MIN_LENGTH = 12
const PASSWORD_MAX_LENGTH = 50

class RegisterModal extends React.Component {
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
    this.sendRegisterData = this.sendRegisterData.bind(this)
    this.errorFor = this.errorFor.bind(this)
  }

  handleFieldChanged(field) {
    return (e) => {
      var newState = update(this.state, {
        [field]: {$set: e.target.value},
      })
      newState.validationErrors = RuleRunner.run(newState, fieldValidations)
      newState.generalError = ''
      this.setState(newState)
    }
  }

  handleSubmitClicked() {
    var self = this
    this.setState({showErrors: true})
    if ($.isEmptyObject(this.state.validationErrors) == false) return null
    if (!$(this.textInput).is(':checked')) {
      this.checkStyle = {
        color: 'red',
      }
      return null
    }
    if (this.state.requestRunning) {
      return false
    }
    this.setState({requestRunning: true})
    const data = {
      event: 'open_register_email',
    }
    CommonUtils.dispatchAnalyticsEvents(data)
    this.checkRedeemProject().then(
      this.sendRegisterData()
        .then(() => {
          const style = {
            width: '25%',
            maxWidth: '450px',
          }
          ModalsActions.showModalComponent(
            ConfirmRegister,
            {emailAddress: self.state.emailAddress},
            'Confirm Registration',
            style,
          )
        })
        .catch((response) => {
          var generalErrorText
          if (response.responseText) {
            var data = JSON.parse(response.responseText)
            generalErrorText = data.error.message
          } else {
            generalErrorText =
              'There was a problem saving the data, please try again later or contact support.'
          }
          self.setState({
            generalError: generalErrorText,
            requestRunning: false,
          })
        }),
    )
  }

  errorFor(field) {
    return this.state.validationErrors[field]
  }

  openLoginModal() {
    APP.openLoginModal()
  }

  googole_popup() {
    const data = {
      event: 'open_register_google',
    }
    CommonUtils.dispatchAnalyticsEvents(data)
    var url = this.props.googleUrl
    this.checkRedeemProject()
    var newWindow = window.open(url, 'name', 'height=600,width=900')
    if (window.focus) {
      newWindow.focus()
    }
    var interval = setInterval(function () {
      if (newWindow.closed) {
        clearInterval(interval)
        window.location.reload()
      }
    }, 600)
  }

  changeCheckbox() {
    this.checkStyle = {
      color: '',
    }
    this.setState(this.state)
  }

  sendRegisterData() {
    return registerUser({
      firstname: this.state.name,
      surname: this.state.surname,
      email: this.state.emailAddress,
      password: this.state.password,
      passwordConfirmation: this.state.password_confirmation,
      wantedUrl: window.location.href,
    })
  }

  checkRedeemProject() {
    if (this.props.redeemMessage) {
      return checkRedeemProjectApi()
    } else {
      return Promise.resolve()
    }
  }

  onKeyDown = (event) => {
    const focusedElement = document.activeElement
    const nextNodeName =
      focusedElement.parentNode.nextSibling?.firstChild?.nodeName?.toLowerCase?.()

    if (event.key === 'Tab') {
      if (nextNodeName === 'input') {
        focusedElement.parentNode.nextSibling.firstChild.focus()
      } else {
        const focusedElementNodeName = focusedElement.nodeName.toLowerCase()

        if (focusedElementNodeName === 'a') {
          // reset focus to first input
          const firstInput = Array.from(this.formContainer.children).find(
            (element) => element.firstChild.nodeName.toLowerCase() === 'input',
          )
          firstInput.firstChild.focus()
        } else if (
          focusedElementNodeName === 'input' &&
          focusedElement.getAttribute('type') === 'password'
        ) {
          const termsAndCondition = this.formContainer.querySelector(
            'input[name="terms"]',
          )
          termsAndCondition.focus()
        } else {
          const submitButton =
            this.formContainer.getElementsByClassName('register-submit')[0]
          submitButton.focus()
        }
      }
    }
  }

  componentDidMount() {
    document.addEventListener('keyup', this.onKeyDown)
  }

  componentWillUnmount() {
    document.removeEventListener('keyup', this.onKeyDown)
  }

  render() {
    var generalErrorHtml = ''
    if (this.state.generalError.length) {
      generalErrorHtml = (
        <div>
          <span style={{color: 'red', fontSize: '14px'}} className="text">
            {this.state.generalError}
          </span>
          <br />
        </div>
      )
    }
    var loaderClass = this.state.requestRunning ? 'show' : ''
    var emailAddress = this.props.userMail ? this.props.userMail : ''

    return (
      <div className="register-modal">
        <a
          className="google-login-button btn-confirm-medium"
          onClick={this.googole_popup.bind(this)}
        />
        <p className="condition-google">
          By clicking you accept{' '}
          <a
            href="https://site.matecat.com/terms/"
            rel="noreferrer"
            target="_blank"
          >
            terms and conditions
          </a>
        </p>
        <div className="form-divider">
          <div className="divider-line"></div>
          <span>OR</span>
          <div className="divider-line"></div>
        </div>
        <div
          className="register-form-container"
          ref={(ref) => (this.formContainer = ref)}
        >
          <h2>Register with your email</h2>
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('name')}
            placeholder="Name"
            name="name"
            errorText={this.errorFor('name')}
            tabindex={1}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('surname')}
            placeholder="Surname"
            name="name"
            errorText={this.errorFor('surname')}
            tabindex={2}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('emailAddress')}
            placeholder="Email"
            name="emailAddress"
            errorText={this.errorFor('emailAddress')}
            tabindex={3}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
            text={emailAddress}
          />
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('password')}
            type="password"
            placeholder={`Password (Minimum ${PASSWORD_MIN_LENGTH} characters, at least one special character)`}
            name="password"
            errorText={this.errorFor('password')}
            tabindex={4}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleFieldChanged('password_confirmation')}
            type="password"
            placeholder="Confirm Password"
            name="password_confirmation"
            errorText={this.errorFor('password_confirmation')}
            tabindex={5}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
          />
          <br />
          <input
            type="checkbox"
            id="check-conditions"
            name="terms"
            ref={(input) => (this.textInput = input)}
            onChange={this.changeCheckbox.bind(this)}
            tabIndex={6}
          />
          <label
            className="check-conditions"
            htmlFor="check-conditions"
            style={this.checkStyle}
          >
            Accept{' '}
            <a
              href="https://site.matecat.com/terms/"
              style={this.checkStyle}
              target="_blank"
              rel="noreferrer"
            >
              terms and conditions
            </a>
          </label>
          <br />
          <a
            className="register-submit btn-confirm-medium register-now"
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleSubmitClicked() : null
            }}
            onClick={this.handleSubmitClicked}
            tabIndex={7}
          >
            <span className={'button-loader ' + loaderClass} /> Register Now{' '}
          </a>
          {generalErrorHtml}
          <p>
            <a style={{cursor: 'pointer'}} onClick={this.openLoginModal}>
              Already registered? Login
            </a>
          </p>
        </div>
      </div>
    )
  }
}

const fieldValidations = [
  RuleRunner.ruleRunner('name', 'Name', FormRules.requiredRule),
  RuleRunner.ruleRunner('surname', 'Surname', FormRules.requiredRule),
  RuleRunner.ruleRunner(
    'emailAddress',
    'Email address',
    FormRules.requiredRule,
    FormRules.checkEmail,
  ),
  RuleRunner.ruleRunner(
    'password',
    'Password',
    FormRules.requiredRule,
    FormRules.minLength(PASSWORD_MIN_LENGTH),
    FormRules.maxLength(PASSWORD_MAX_LENGTH),
    FormRules.atLeastOneSpecialChar(),
  ),
  RuleRunner.ruleRunner(
    'password_confirmation',
    'Password confirmation',
    FormRules.requiredRule,
    FormRules.mustMatch('password', 'Password'),
  ),
]

export default RegisterModal
