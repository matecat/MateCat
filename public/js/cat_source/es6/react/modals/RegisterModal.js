var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class RegisterModal extends React.Component {


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
        this.sendRegisterData = this.sendRegisterData.bind(this);
        this.errorFor = this.errorFor.bind(this);


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
        if (!$(this.textInput).is(':checked')) {
            this.checkStyle = {
                color: 'red'
            };
            return null;
        }
        this.sendRegisterData().done(function (data) {
            $('#modal').trigger('opensuccess', [{
                title: 'Register Now',
                text: 'To complete your registration please follow the instructions in the email we sent you to ' + self.state.emailAddress + '.'
            }]);
        }).fail(function (response) {

            if (response.responseText.length) {
                var data = JSON.parse( response.responseText );
                self.setState({
                    generalError: data.error.message
                });
            } else {
                self.setState({
                    generalError: 'There was a problem saving the data, please try again later or contact support.'
                });
            }
        });
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }

    openLoginModal() {
        $('#modal').trigger('openlogin');
    }

    googole_popup(  ) {
        var url = this.props.googleUrl;
        var newWindow = window.open( url, 'name', 'height=600,width=900' );
        if ( window.focus ) {
            newWindow.focus();
        }
    }

    changeCheckbox() {
        this.checkStyle = {
            color: ''
        };
        this.setState(this.state);
    }



    sendRegisterData() {
        return $.post('/api/app/user', { user: {
            first_name: this.state.name,
            last_name: this.state.surname,
            email: this.state.emailAddress,
            password: this.state.password,
            password_confirmation: this.state.password
        }});
    }

    render() {
        var generalErrorHtml = '';
        if (this.state.generalError.length) {
            generalErrorHtml = <div><span style={ {color: 'red',fontSize: '14px'} } className="text">{this.state.generalError}</span><br/></div>;
        }
        return <div className="register-modal">
            <h2>Register</h2>
            <a className="google-login btn-confirm-medium" onClick={this.googole_popup.bind(this)}> Google login </a>
            <p>By clicking you accept terms and conditions</p>
            <div className="register-form-container">
                <h2>Register with your email</h2>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("name")}
                               placeholder="Name" name="name" errorText={this.errorFor("name")} tabindex={1}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("surname")}
                           placeholder="Surname" name="name" errorText={this.errorFor("surname")} tabindex={2}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                           placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")} tabindex={3}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password")}
                           type="password" placeholder="Password" name="password" errorText={this.errorFor("password")} tabindex={4}/>
                <input type="checkbox" id="check-conditions" name="terms" ref={(input) => this.textInput = input} onChange={this.changeCheckbox.bind(this)} tabIndex={5}/>
                <label htmlFor="check-conditions" style={this.checkStyle}>Accept terms and conditions</label><br/>
                <a className="register-submit btn-confirm-medium"
                   onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}
                   onClick={this.handleSubmitClicked.bind()} tabIndex={6}> Register Now </a>
                {generalErrorHtml}
                <br/>
                <span style={{cursor:'pointer'}} onClick={this.openLoginModal}>Already registered? Login</span>
            </div>
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("name", "Name", FormRules.requiredRule),
    RuleRunner.ruleRunner("surname", "Surname", FormRules.requiredRule),
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
    RuleRunner.ruleRunner("password", "Password", FormRules.requiredRule, FormRules.minLength(8)),
];

export default RegisterModal ;
