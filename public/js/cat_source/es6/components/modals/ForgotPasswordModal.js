import update from 'immutability-helper'
import React from 'react'

import TextField from '../common/TextField'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'
import {forgotPassword} from '../../api/forgotPassword'
import {checkRedeemProject as checkRedeemProjectApi} from '../../api/checkRedeemProject'

class ForgotPasswordModal extends React.Component {
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
    this.errorFor = this.errorFor.bind(this)
  }

  openLoginModal() {
    APP.openLoginModal()
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
    if (this.state.requestRunning) {
      return false
    }
    this.setState({requestRunning: true})
    this.checkRedeemProject().then(
      this.sendForgotPassword()
        .then(() => {
          APP.openSuccessModal({
            title: 'Forgot Password',
            text:
              'We sent an email to ' +
              self.state.emailAddress +
              '. Follow the instructions to create a new password.',
          })
        })
        .catch(({errors}) => {
          const error = errors?.[0]
            ? errors[0].message
            : 'There was a problem saving the data, please try again later or contact support.'
          self.setState({
            generalError: error,
            requestRunning: false,
          })
        }),
    )
  }

  sendForgotPassword() {
    return forgotPassword(this.state.emailAddress, window.location.href)
  }

  checkRedeemProject() {
    checkRedeemProjectApi()
    if (this.props.redeemMessage) {
      return checkRedeemProjectApi()
    } else {
      return Promise.resolve()
    }
  }

  errorFor(field) {
    return this.state.validationErrors[field]
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

    return (
      <div className="forgot-password-modal">
        <p>
          Enter the email address associated with your account and we&apos;ll
          send you the link to reset your password.
        </p>
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
        <a
          className="send-password-button btn-confirm-medium"
          onKeyPress={(e) => {
            e.key === 'Enter' ? this.handleSubmitClicked() : null
          }}
          onClick={this.handleSubmitClicked.bind()}
          tabIndex={2}
        >
          <span className={'button-loader ' + loaderClass} /> Send{' '}
        </a>
        {generalErrorHtml}
        <br />
        <span className="forgot-password" onClick={this.openLoginModal}>
          Back to login
        </span>
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

export default ForgotPasswordModal
