var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class LoginModal extends React.Component {


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
        this.sendLoginData = this.sendLoginData.bind(this);
        this.errorFor = this.errorFor.bind(this);
    }

    googole_popup(  ) {
        //var rid=$('#rid').text();
        //url=url+'&rid='+rid;
        var url = this.props.googleUrl;
        var newWindow = window.open( url, 'name', 'height=600,width=900' );
        if ( window.focus ) {
            newWindow.focus();
        }
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
        if ( this.requestRunning ) {
            return false;
        }
        this.requestRunning = true;
        this.sendLoginData().done(function (data) {
            window.location.reload();
        }).fail(function (response) {
            if (response.responseText.length) {
                var data = JSON.parse( response.responseText );
                self.setState({
                    generalError: data
                });
            } else {
                self.setState({
                    generalError: 'Login failed.'
                });
            }
            self.requestRunning = false;
        });

    }

    sendLoginData() {
        return $.post('/api/app/user/login',  {
            email : this.state.emailAddress,
            password : this.state.password
        });
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }

    openRegisterModal() {
    $('#modal').trigger('openregister');
}

    openForgotPassword() {
        $('#modal').trigger('openforgotpassword');
    }

    render() {
        var generalErrorHtml = '';
        if (this.state.generalError.length) {
            generalErrorHtml = <span style={ {color: 'red',fontSize: '14px'} } className="text">{this.state.generalError}</span>;
        }
        return <div className="login-modal">
                    <div className="login-container-right">
                        <h2>All the advatnages</h2>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        <ul className="">
                            <li>Lorem ipsum dolor sit amet</li>
                            <li>Lorem ipsum dolor sit amet</li>
                            <li>Lorem ipsum dolor sit amet</li>
                        </ul>
                        <a className="register-button btn-confirm-medium" onClick={this.openRegisterModal}> Register now </a>
                    </div>
                    <div className="login-container-left">
                        <h2>Log in</h2>
                        <a className="google-login-button btn-confirm-medium" onClick={this.googole_popup.bind(this)}> Google login </a>
                        <div className="login-form-container">
                            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                                       placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")} tabindex={1}
                                       onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}/>
                            <TextField type="password" showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password")}
                                       placeholder="Password" name="password" errorText={this.errorFor("password")} tabindex={2}
                                       onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}/>
                            <a className="login-button btn-confirm-medium"
                               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}
                               onClick={this.handleSubmitClicked.bind()} tabIndex={3}> Log in </a>
                            {generalErrorHtml}
                            <br/>
                            <span className="forgot-password" onClick={this.openForgotPassword}>Forgot password?</span>
                        </div>
                    </div>
                </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
    RuleRunner.ruleRunner("password", "Password", FormRules.requiredRule, FormRules.minLength(8)),
];

LoginModal.propTypes = {
    googleUrl: React.PropTypes.string
};

export default LoginModal ;
