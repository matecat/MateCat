var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class RegisterModal extends React.Component {


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
        if (!$(this.textInput).is(':checked')) {
            this.checkStyle = {
                color: 'red'
            };
            return null;
        }
        console.log("Send Register Data", this.state);
        $('#modal').trigger('opensuccess', [{
            title: 'Register Now',
            text: 'To complete your registration please follow the instructions in the email we sent you to ' + this.state.emailAddress + '.'
        }]);
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

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div className="register-modal">
            <h2>Register</h2>
            <a className="google-login btn-confirm-medium" onClick={this.googole_popup.bind(this)}> Google login </a>
            <p>By clicking you accept terms and conditions</p>
            <div className="register-form-container">
                <h2>Register with your email</h2>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("name")}
                           placeholder="Name" name="name" errorText={this.errorFor("name")}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("surname")}
                           placeholder="Surname" name="name" errorText={this.errorFor("surname")}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                           placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")}/>
                <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password")}
                           placeholder="Password" name="password" errorText={this.errorFor("password")}/>
                <input type="checkbox" id="check-conditions" name="terms" ref={(input) => this.textInput = input} onChange={this.changeCheckbox.bind(this)}/>
                <label htmlFor="check-conditions" style={this.checkStyle}>Accept terms and conditions</label><br/>
                <a className="register-submit btn-confirm-medium" onClick={this.handleSubmitClicked.bind()}> Register Now </a><br/>
                <span style={{cursor:'pointer'}} onClick={this.openLoginModal}>Already registered? Login</span>
            </div>
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("name", "Name", FormRules.requiredRule),
    RuleRunner.ruleRunner("surname", "Surname", FormRules.requiredRule),
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
    RuleRunner.ruleRunner("password", "Password", FormRules.requiredRule, FormRules.minLength(6)),
];

export default RegisterModal ;
