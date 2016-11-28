
class ConfirmRegister extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            resend: false
        };
    }

    resendEmail() {
        var self = this;
        $.post('/api/app/user/resend_email_confirm', {
            user: {
                first_name: this.state.name,
                last_name: this.state.surname,
                email: this.state.emailAddress,
                password: this.state.password,
                password_confirmation: this.state.password,
                wanted_url: window.location.href
            }
        }).done(function () {
            self.setState({
                resend: true
            })
        });
    }

    render() {
        var resend = '';
        if ( this.state.resend ) {
            resend = <p className="resend-message">Email sent again</p>
        }
        return <div className="success-modal">
            <p>{'To complete your registration please follow the instructions in the email we sent you to ' + this.props.emailAddress + '.'}</p><br/>
            <a className="register-submit btn-confirm-medium"
               onClick={this.resendEmail.bind(this)} > Resend the email </a>
            {resend}
        </div>;
    }
}

export default ConfirmRegister ;
