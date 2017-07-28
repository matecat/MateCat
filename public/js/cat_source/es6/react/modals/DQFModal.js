let TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class PreferencesModal extends React.Component {


    constructor(props) {
        super(props);

        this.state = {
            dqfCredentials : {
                dqfUsername : this.props.metadata.dqf_username,
                dqfPassword : this.props.metadata.dqf_password
            },
            dqfValid: false,
            showErrors: false,
            validationErrors: {},
        };
        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
        this.onKeyPressCoupon = this.onKeyPressCoupon.bind( this );
    }

    handleDQFFieldChanged(field) {
        return (e) => {
            var newState = update(this.state, {
                [field]: {$set: e.target.value}
            });
            newState.validationErrors = RuleRunner.run(newState, fieldValidations);
            newState.generalError = '';
            this.setState(newState);
        }
    }

    handleDQFSubmitClicked() {
        let self = this;
        this.setState({showErrors: true});
        if($.isEmptyObject(this.state.validationErrors) == false) return null;
        this.submitDQFCredentials();
    }

    errorFor(field) {
        return this.state.validationErrors[field];
    }


    componentWillMount() { }

    componentDidMount() {
        if ( this.state.service && !this.state.service.disabled_at) {
            $(this.checkDrive).attr('checked', true);
        }
    }

    submitDQFCredentials() {
        let self = this;
        let dqfCheck = $('.dqf-box #dqf_switch');
        return $.post('/api/app/user/metadata', { metadata : {
            dqf_username : this.state.dqfUsername,
            dqf_password : this.state.dqfPassword
        }
        }).done( function( data ) {
            if (data) {
                APP.USER.STORE.metadata = data;
                APP.USER.STORE.metadata.dqf = {
                    username : self.state.dqfUsername,
                    password : self.state.dqfPassword
                };
                dqfCheck.trigger('dqfEnable');
                self.setState({
                    dqfValid: true,
                    dqfCredentials : {
                        dqfUsername : self.state.dqfUsername,
                        dqfPassword : self.state.dqfPassword
                    },
                });
            } else {
                self.setState({
                    dqfError: 'Invalid credentials'
                });
            }
        }).fail(function () {

            self.setState({
                dqfError: 'Invalid credentials'
            });
        });
    }

    clearDQFCredentials() {
        let self = this;
        let dqfCheck = $('.dqf-box #dqf_switch');
        return $.post('/api/app/user/metadata', { metadata : {
            dqf_clear : 1,
        }
        }).done( function( data ) {
            if (data) {
                APP.USER.STORE.metadata = data;
                dqfCheck.trigger('dqfDisable');
                self.setState({
                    dqfValid: false,
                    dqfCredentials : {},
                });
            }
        });
    }

    getDqfHtml() {
        if (!config.dqf_enabled) {
            return '';
        } else if (this.state.dqfValid || this.state.dqfCredentials.dqfUsername) {
            return <div className="dqf-container">
                <h2>DQF Credentials</h2>
                <div className="user-dqf">
                    <input type="text" name="dqfUsername"  defaultValue={this.state.dqfCredentials.dqfUsername} disabled /><br/>
                    <input type="password" name="dqfPassword"  defaultValue={this.state.dqfCredentials.dqfPassword} disabled  style={{marginTop: '15px'}}/><br/>
                    <div className="ui primary button" style={{marginTop: '15px', marginLeft: '82%'}}
                         onClick={this.clearDQFCredentials.bind(this)}>Clear</div>

                </div>
            </div>
        } else {
            return <div className="dqf-container">
                <h2>DQF Credentials</h2>
                <div className="user-dqf">
                    <TextField showError={this.state.showErrors} onFieldChanged={this.handleDQFFieldChanged("dqfUsername")}
                               placeholder="Username" name="dqfUsername" errorText={this.errorFor("dqfUsername")} tabindex={1}
                               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleDQFSubmitClicked() : null) }}/>
                    <TextField type="password" showError={this.state.showErrors} onFieldChanged={this.handleDQFFieldChanged("dqfPassword")}
                               placeholder="Password (minimum 8 characters)" name="dqfPassword" errorText={this.errorFor("dqfPassword")} tabindex={2}
                               onKeyPress={(e) => { (e.key === 'Enter' ? this.handleDQFSubmitClicked() : null) }}/>
                    <div className="ui primary button" onClick={this.handleDQFSubmitClicked.bind(this)}>Sign in</div>
                    <div className="dqf-message">
                        <span style={{color: 'red', fontSize: '14px',position: 'absolute', right: '27%', lineHeight: '24px'}} className="coupon-message">{this.state.dqfError}</span>
                    </div>
                </div>
            </div>
        }
    }

    render() {

        return <div className="preferences-modal">
                    <div className="user-info-attributes">
                        {this.getDqfHtml()}
                    </div>
                </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("dqfUsername", "Username", FormRules.requiredRule),
    RuleRunner.ruleRunner("dqfPassword", "Password", FormRules.requiredRule, FormRules.minLength(8)),
];

export default PreferencesModal ;
