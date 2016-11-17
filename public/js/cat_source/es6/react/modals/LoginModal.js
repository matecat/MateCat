var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class LoginModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            showErrors: false,
            validationErrors: {}
        };

        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
        this.handleFieldChanged = this.handleFieldChanged.bind(this);
        this.handleSubmitClicked = this.handleSubmitClicked.bind(this);
        this.errorFor = this.errorFor.bind(this);
    }

    openRegisterModal() {
        $('#modal').trigger('openregister');
    }

    openForgotPassword() {
        $('#modal').trigger('openforgotpassword');
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
            this.setState(newState);
        }
    }

    handleSubmitClicked() {
        this.setState({showErrors: true});
        if($.isEmptyObject(this.state.validationErrors) == false) return null;
        console.log("Send Login Data");
        // ... continue submitting data to server
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
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
                                       placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")}/>
                            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password")}
                                       placeholder="Password" name="password" errorText={this.errorFor("password")}/>
                            <a className="login-button btn-confirm-medium" onClick={this.handleSubmitClicked.bind()}> Log in </a><br/>
                            <span className="forgot-password" onClick={this.openForgotPassword}>Forgot password?</span>
                        </div>
                    </div>
                </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
    RuleRunner.ruleRunner("password", "Password", FormRules.requiredRule, FormRules.minLength(6)),
];

LoginModal.propTypes = {
    googleUrl: React.PropTypes.string
};

export default LoginModal ;
