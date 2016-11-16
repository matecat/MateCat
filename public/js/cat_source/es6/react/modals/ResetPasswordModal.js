var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class ResetPasswordModal extends React.Component {


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
        console.log("Send Reset Password Data");
        $('#modal').trigger('opensuccess', [{
            title: 'Reset Password',
            text: 'Your password has been changed.'
        }]);
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }

    componentWillMount() {

    }

    componentDidMount() {

    }

    render() {
        return <div className="reset-password-modal">
            <h2>Reset Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password1")}
                       placeholder="Password" name="password1" errorText={this.errorFor("password1")}/>
            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password2")}
                       placeholder="Confirm Password" name="password2" errorText={this.errorFor("password2")}/>
            <a className="reset-password-button btn-confirm-medium" onClick={this.handleSubmitClicked.bind()}> Reset </a> <br/>
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("password1", "Password", FormRules.requiredRule, FormRules.minLength(6)),
    RuleRunner.ruleRunner("password2", "Password confirmation", FormRules.mustMatch("password1", "Password")),
];


export default ResetPasswordModal ;
