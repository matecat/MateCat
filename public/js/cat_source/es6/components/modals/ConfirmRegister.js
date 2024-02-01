import React from 'react'
import {resendEmailConfirmation} from '../../api/resendEmailConfirmation'
import ModalsActions from '../../actions/ModalsActions'

class ConfirmRegister extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      resend: false,
    }
  }

  resendEmail() {
    var self = this
    resendEmailConfirmation(this.props.emailAddress).then(() => {
      self.setState({
        resend: true,
      })
    })
  }

  closeModal() {
    ModalsActions.onCloseModal()
  }

  render() {
    var resend = ''
    if (this.state.resend) {
      resend = <p className="resend-message">Email sent again</p>
    }
    return (
      <div className="success-modal">
        <p>
          {'To complete your registration please follow the instructions in the email we sent you to ' +
            this.props.emailAddress +
            '.'}
        </p>
        <a
          className="ui primary right floated button tiny register-ok"
          onClick={this.closeModal.bind(this)}
        >
          {' '}
          OK{' '}
        </a>
        <div id="resendlink" onClick={this.resendEmail.bind(this)}>
          Resend Email
        </div>
        {resend}
      </div>
    )
  }
}

export default ConfirmRegister
