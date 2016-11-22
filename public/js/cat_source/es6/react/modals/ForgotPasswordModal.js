var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';
class ForgotPasswordModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            showErrors: false,
            validationErrors: {},
            generalError: ''
        };

        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
        this.handleFieldChanged = this.handleFieldChanged.bind(this);
        this.handleSubmitClicked = this.handleSubmitClicked.bind(this);
        this.errorFor = this.errorFor.bind(this);
    }

    openLoginModal() {
        $('#modal').trigger('openlogin');
    }

    handleFieldChanged(field) {
        return (e) => {
            var newState = update(this.state, {
                [field]: {$set: e.target.value}
            });
            newState.validationErrors = RuleRunner.run(newState, fieldValidations);
            newState.generalError = '';
            this.setState(newState);
        }
    }

    handleSubmitClicked() {
        var self = this;
        this.setState({showErrors: true});
        if($.isEmptyObject(this.state.validationErrors) == false) return null;
        console.log("Send forgot password Data");

        this.sendForgotPassword().done(function (data) {
            $('#modal').trigger('opensuccess', [{
                title: 'Forgot Password',
                text: 'We sent you an email. Follow the instructions to create a new password.'
            }]);
        }).fail(function (response) {
            var data = JSON.parse( response.responseText );
            if (data) {
                self.setState({
                    generalError: data
                });
            } else {
                self.setState({
                    generalError: 'There was a problem saving the data, please try again later or contact support.'
                });
            }
        });
    }

    sendForgotPassword() {
        $.post('/api/app/user/forgot_password', { email: this.state.emailAddress } )
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        var generalErrorHtml = '';
        if (this.state.generalError.length) {
            generalErrorHtml = <div><span style={ {color: 'red',fontSize: '14px'} } className="text">{this.state.generalError}</span><br/></div>;
        }
        return <div className="forgot-password-modal">
            <h2>Forgot Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                       placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")} tabindex={1}/>
            <a className="send-password-button btn-confirm-medium"
               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}
               onClick={this.handleSubmitClicked.bind()} tabIndex={2}> Send </a>
            {generalErrorHtml}
            <br/>
            <span className="forgot-password" onClick={this.openLoginModal}>Back to login</span>
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
];

export default ForgotPasswordModal ;
