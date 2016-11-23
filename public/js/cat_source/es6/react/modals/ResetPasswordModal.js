var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class ResetPasswordModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            showErrors: false,
            validationErrors: {},
            generalError: ''
        };
        this.requestRunning = false;
        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
        this.handleFieldChanged = this.handleFieldChanged.bind(this);
        this.handleSubmitClicked = this.handleSubmitClicked.bind(this);
        this.sendResetPassword = this.sendResetPassword.bind(this);
        this.errorFor = this.errorFor.bind(this);
    }

    handleFieldChanged(field) {
        return (e) => {
            var newState = update(this.state, {
                [field]: {$set: e.target.value},
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

        if ( this.requestRunning ) {
            return false;
        }
        this.requestRunning = true;

        this.sendResetPassword().done(function (data) {
            $('#modal').trigger('opensuccess', [{
                title: 'Reset Password',
                text: 'Your password has been changed.'
            }]);
        }).fail(function (response) {
            if (response.responseText.length) {
                var data = JSON.parse( response.responseText );
                self.setState({
                    generalError: data
                });
            } else {
                self.setState({
                    generalError: 'There was a problem saving the data, please try again later or contact support.'
                });
            }
            self.requestRunning = false;
        });

    }

    sendResetPassword() {
        return $.post('/api/app/user/password', {
            password: this.state.password1,
            password_confirmation: this.state.password2
        });
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
            generalErrorHtml = <span style={ {color: 'red',fontSize: '14px'} } className="text">{this.state.generalError}</span>;
        }
        return <div className="reset-password-modal">
            <h2>Reset Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <TextField type="password" showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password1")}
                       placeholder="Password" name="password1" errorText={this.errorFor("password1")} tabindex={1}/>
            <TextField type="password" showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password2")}
                       placeholder="Confirm Password" name="password2" errorText={this.errorFor("password2")} tabindex={1}/>
            <a className="reset-password-button btn-confirm-medium" onClick={this.handleSubmitClicked}
               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}
               tabIndex="3"> Reset </a> <br/>
            {generalErrorHtml}
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("password1", "Password", FormRules.requiredRule, FormRules.minLength(6)),
    RuleRunner.ruleRunner("password2", "Password confirmation", FormRules.mustMatch("password1", "Password")),
];


export default ResetPasswordModal ;
