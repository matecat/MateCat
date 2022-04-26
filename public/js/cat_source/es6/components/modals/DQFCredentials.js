import update from 'immutability-helper'
import React from 'react'

import TextField from '../common/TextField'
import * as RuleRunner from '../common/ruleRunner'
import * as FormRules from '../common/formRules'

import {
  clearDqfCredentials as clearDqfCredentialsApi,
  submitDqfCredentials as submitDqfCredentialsApi,
} from '../../api/dqfCredentials'

class DQFCredentials extends React.Component {
  constructor(props) {
    super(props)

    this.state = {
      dqfCredentials: {
        dqfUsername: this.props.metadata.dqf_username,
        dqfPassword: this.props.metadata.dqf_password,
      },
      dqfValid: false,
      showErrors: false,
      validationErrors: {},
    }
    this.state.validationErrors = RuleRunner.run(this.state, fieldValidations)
  }

  handleDQFFieldChanged(field) {
    return (e) => {
      var newState = update(this.state, {
        [field]: {$set: e.target.value},
      })
      newState.validationErrors = RuleRunner.run(newState, fieldValidations)
      newState.generalError = ''
      this.setState(newState)
    }
  }

  handleDQFSubmitClicked() {
    this.setState({showErrors: true})
    if ($.isEmptyObject(this.state.validationErrors) == false) return null
    this.submitDQFCredentials()
  }

  errorFor(field) {
    return this.state.validationErrors[field]
  }

  submitDQFCredentials() {
    let self = this
    submitDqfCredentialsApi(this.state.dqfUsername, this.state.dqfPassword)
      .then((data) => {
        if (data) {
          APP.USER.STORE.metadata = data

          self.setState({
            dqfValid: true,
            dqfCredentials: {
              dqfUsername: self.state.dqfUsername,
              dqfPassword: self.state.dqfPassword,
            },
          })
        } else {
          self.setState({
            dqfError: 'Invalid credentials',
          })
        }
      })
      .catch(() => {
        self.setState({
          dqfError: 'Invalid credentials',
        })
      })
  }

  clearDQFCredentials() {
    let self = this
    let dqfCheck = $('.dqf-box #dqf_switch')
    clearDqfCredentialsApi().then((data) => {
      APP.USER.STORE.metadata = data
      dqfCheck.trigger('dqfDisable')
      if (self.saveButton) {
        self.saveButton.classList.remove('disabled')
      }
      self.setState({
        dqfValid: false,
        dqfCredentials: {},
        dqfOptions: {},
      })
    })
  }

  getDqfHtml() {
    if (this.state.dqfValid || this.state.dqfCredentials.dqfUsername) {
      return (
        <div className="user-dqf">
          <input
            type="text"
            name="dqfUsername"
            defaultValue={this.state.dqfCredentials.dqfUsername}
            disabled
          />
          <br />
          <input
            type="password"
            name="dqfPassword"
            defaultValue={this.state.dqfCredentials.dqfPassword}
            disabled
            style={{marginTop: '15px'}}
          />
          <br />
          <div
            className="ui primary button"
            style={{marginTop: '15px', marginLeft: '82%'}}
            onClick={this.clearDQFCredentials.bind(this)}
          >
            Clear
          </div>
        </div>
      )
    } else {
      return (
        <div className="user-dqf">
          <TextField
            showError={this.state.showErrors}
            onFieldChanged={this.handleDQFFieldChanged('dqfUsername')}
            placeholder="Username"
            name="dqfUsername"
            errorText={this.errorFor('dqfUsername')}
            tabindex={1}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleDQFSubmitClicked() : null
            }}
          />
          <TextField
            type="password"
            showError={this.state.showErrors}
            onFieldChanged={this.handleDQFFieldChanged('dqfPassword')}
            placeholder="Password (minimum 8 characters)"
            name="dqfPassword"
            errorText={this.errorFor('dqfPassword')}
            tabindex={2}
            onKeyPress={(e) => {
              e.key === 'Enter' ? this.handleDQFSubmitClicked() : null
            }}
          />
          <div
            className="ui primary button"
            onClick={this.handleDQFSubmitClicked.bind(this)}
          >
            Sign in
          </div>
          <div className="dqf-message">
            <span
              style={{
                color: 'red',
                fontSize: '14px',
                position: 'absolute',
                right: '27%',
                lineHeight: '24px',
              }}
              className="coupon-message"
            >
              {this.state.dqfError}
            </span>
          </div>
        </div>
      )
    }
  }

  componentDidMount() {}

  render() {
    return this.getDqfHtml()
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

export default DQFCredentials
