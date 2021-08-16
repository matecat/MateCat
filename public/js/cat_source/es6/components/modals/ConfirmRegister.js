import React from 'react'

class ConfirmRegister extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      resend: false,
    }
  }

  resendEmail() {
    var self = this
    $.post('/api/app/user/resend_email_confirm', {
      email: this.props.emailAddress,
    }).done(function () {
      self.setState({
        resend: true,
      })
    })
  }

  closeModal() {
    $('#modal').trigger('closemodal')
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
