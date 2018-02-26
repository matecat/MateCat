let PropTypes = require('prop-types');
let TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class LoginModal extends React.Component {


    constructor(props) {
        super(props);
        this.state = {
            showErrors: false,
            validationErrors: {},
            generalError: '',
            requestRunning: false
        };
        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
        this.handleFieldChanged = this.handleFieldChanged.bind(this);
        this.handleSubmitClicked = this.handleSubmitClicked.bind(this);
        this.sendLoginData = this.sendLoginData.bind(this);
        this.errorFor = this.errorFor.bind(this);
    }

    googole_popup(  ) {
        let url = this.props.googleUrl;
        let self = this;
        this.checkRedeemProject();
        let newWindow = window.open( url, 'name', 'height=600,width=900' );
        if ( window.focus ) {
            newWindow.focus();
        }
        let interval = setInterval(function () {
            if ( newWindow.closed ) {
                clearInterval( interval );
                let loc;
                if ( self.props.goToManage ) {
                    window.location = '/manage/';
                } else if ( loc = window.localStorage.getItem( 'wanted_url' ) ) {
                    window.localStorage.removeItem( 'wanted_url' );
                    window.location.href = loc;
                } else {
                    window.location.reload();
                }
            }
        }, 600);
    }

    handleFieldChanged(field) {
        return (e) => {
            let newState = update(this.state, {
                [field]: {$set: e.target.value}
            });
            newState.validationErrors = RuleRunner.run(newState, fieldValidations);
            newState.generalError = '';
            this.setState(newState);
        }
    }

    handleSubmitClicked() {
        let self = this;
        this.setState({showErrors: true});
        if($.isEmptyObject(this.state.validationErrors) == false) return null;
        if ( this.state.requestRunning ) {
            return false;
        }
        this.setState({requestRunning: true});
        this.checkRedeemProject().then(this.sendLoginData().done(function (data) {
            if (self.props.goToManage) {
                window.location = '/manage/';
            } else {
                window.location.reload();
            }
        }).fail(function (response) {
            let text;
            if (response.responseText.length) {
                text = JSON.parse( response.responseText );

            } else {
                text = 'Login failed.'
            }
            self.setState({
                generalError: text,
                requestRunning: false
            });
        }));

    }

    checkRedeemProject() {
        if (this.props.redeemMessage) {
            return $.post('/api/app/user/redeem_project');
        } else {
            let deferred = $.Deferred();
            deferred.resolve();
            return deferred.promise();
        }
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
        let generalErrorHtml = '';
        let buttonSignInClass = (_.size(this.state.validationErrors) === 0) ?  '':'disabled';
        if (this.state.generalError.length) {
            generalErrorHtml = <div style={ {color: 'red',fontSize: '14px'} } className="text">{this.state.generalError}</div>;
        }

        let loaderClass = (this.state.requestRunning) ? 'show' : '';

        let htmlMessage = <div className="login-container-right">
            <h2>Sign up now to:</h2>
            <ul className="">
                <li>Manage your TMs, glossaries and MT engines</li>
                <li>Access the management panel</li>
                <li>Translate Google Drive files</li>
            </ul>
            <a className="register-button btn-confirm-medium" onClick={this.openRegisterModal}>Sign up</a>
        </div>;

        if ( this.props.redeemMessage )  {
            htmlMessage = <div className="login-container-right manage-bg">
                <h2 style={{fontSize: "21px"}}>Sign up or sign in to add the project to your management panel and:</h2>
                <ul className="add-project-manage">
                    <li>Track the progress of your translations</li>
                    <li>Monitor the activity for increased security</li>
                    <li>Manage TMs, MT and glossaries</li>
                </ul>
                <a className="register-button btn-confirm-medium sing-up" onClick={this.openRegisterModal}>Sign up</a>
            </div>
        }
        return <div className="login-modal">
                    {htmlMessage}
                    <div className="login-container-left">
                        <a className="google-login-button btn-confirm-medium" onClick={this.googole_popup.bind(this)}/>

                        <div className="login-form-container">
                            <div className="form-divider">
                                <div className="divider-line"></div>
                                <span>OR</span>
                                <div className="divider-line"></div>
                            </div>
                            <TextField showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("emailAddress")}
                                       placeholder="Email" name="emailAddress" errorText={this.errorFor("emailAddress")} tabindex={1}
                                       onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}/>
                            <TextField type="password" showError={this.state.showErrors} onFieldChanged={this.handleFieldChanged("password")}
                                       placeholder="Password (minimum 8 characters)" name="password" errorText={this.errorFor("password")} tabindex={2}
                                       onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}/>
                            <a className={"login-button btn-confirm-medium sing-in " + buttonSignInClass }
                               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleSubmitClicked() : null) }}
                               onClick={this.handleSubmitClicked.bind()} tabIndex={3}><span className={"button-loader " + loaderClass}/> Sign in </a>
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
    googleUrl: PropTypes.string
};

export default LoginModal ;
