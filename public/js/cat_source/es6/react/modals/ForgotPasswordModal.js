var TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';
class ForgotPasswordModal extends React.Component {


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

    openLoginModal() {
        $('#modal').trigger('openlogin');
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
        console.log("Send forgot password Data");
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
        return <div className="forgot-password-modal">
            <h2>Forgot Password</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit</p>
            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                       placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")}/>
            <a className="send-password-button btn-confirm-medium" onClick={this.handleSubmitClicked.bind()}> Send </a> <br/>
            <span className="forgot-password" onClick={this.openLoginModal}>Back to login</span>
        </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("emailAddress", "Email address", FormRules.requiredRule, FormRules.checkEmail),
];

export default ForgotPasswordModal ;
