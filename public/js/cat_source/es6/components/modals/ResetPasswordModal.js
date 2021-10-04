import React from 'react'
import update from 'immutability-helper'

import TextField from '../common/TextField'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'
import {resetPasswordUser} from '../../api/resetPasswordUser'

class ResetPasswordModal extends React.Component {
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
    this.sendResetPassword = this.sendResetPassword.bind(this)
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

    if (this.state.requestRunning) {
      return false
    }
    this.setState({requestRunning: true})

    this.sendResetPassword()
      .then(() => {
        $('#modal').trigger('opensuccess', [
          {
            title: 'Reset Password',
            text: 'Your password has been changed.',
          },
        ])
      })
      .catch((response) => {
        const text = !response.statusText
          ? 'There was a problem saving the data, please try again later or contact support.'
          : response.statusText

        self.setState({
          generalError: text,
          requestRunning: false,
        })
      })
  }

  sendResetPassword() {
    return resetPasswordUser(this.state.password1, this.state.password2)
  }

  errorFor(field) {
    return this.state.validationErrors[field]
  }

  render() {
    var generalErrorHtml = ''
    if (this.state.generalError.length) {
      generalErrorHtml = (
        <span style={{color: 'red', fontSize: '14px'}} className="text">
          {this.state.generalError}
        </span>
      )
    }
    var loaderClass = this.state.requestRunning ? 'show' : ''

    return (
      <div className="reset-password-modal">
        <p>Enter the new password</p>
        <TextField
          type="password"
          showError={this.state.showErrors}
          onFieldChanged={this.handleFieldChanged('password1')}
          placeholder="Password"
          name="password1"
          errorText={this.errorFor('password1')}
          tabindex={1}
          onKeyPress={(e) => {
            e.key === 'Enter' ? this.handleSubmitClicked() : null
          }}
        />
        <TextField
          type="password"
          showError={this.state.showErrors}
          onFieldChanged={this.handleFieldChanged('password2')}
          placeholder="Confirm Password"
          name="password2"
          errorText={this.errorFor('password2')}
          tabindex={1}
          onKeyPress={(e) => {
            e.key === 'Enter' ? this.handleSubmitClicked() : null
          }}
        />
        <a
          className="reset-password-button btn-confirm-medium"
          onClick={this.handleSubmitClicked}
          onKeyPress={(e) => {
            e.key === 'Enter' ? this.handleSubmitClicked() : null
          }}
          tabIndex="3"
        >
          <span className={'button-loader ' + loaderClass} /> Reset{' '}
        </a>{' '}
        <br />
        {generalErrorHtml}
      </div>
    )
  }
}

const fieldValidations = [
  RuleRunner.ruleRunner(
    'password1',
    'Password',
    FormRules.requiredRule,
    FormRules.minLength(6),
  ),
  RuleRunner.ruleRunner(
    'password2',
    'Password confirmation',
    FormRules.mustMatch('password1', 'Password'),
  ),
]

export default ResetPasswordModal
