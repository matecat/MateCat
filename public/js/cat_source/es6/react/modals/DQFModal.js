let TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class DQFModal extends React.Component {


    constructor(props) {
        super(props);

        this.state = {
            dqfCredentials : {
                dqfUsername : this.props.metadata.dqf_username,
                dqfPassword : this.props.metadata.dqf_password
            },
            dqfOptions : this.props.metadata.dqf_options,
            dqfValid: false,
            showErrors: false,
            validationErrors: {},
        };
        this.state.validationErrors = RuleRunner.run(this.state, fieldValidations);
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
        this.setState({showErrors: true});
        if($.isEmptyObject(this.state.validationErrors) == false) return null;
        this.submitDQFCredentials();
    }

    errorFor(field) {
        return this.state.validationErrors[field];
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
                if (self.saveButton) {
                    self.saveButton.classList.remove('disabled');
                }
                self.setState({
                    dqfValid: false,
                    dqfCredentials : {},
                    dqfOptions: {}
                });
            }
        });
    }

    saveDQFOptions() {
        let dqf_options = {};
        let errors = false;

        if (this.contentType.value === "") {
            errors = true;
            this.contentType.classList.add('error');
        } else {
            this.contentType.classList.remove('error');
        }

        if (this.industry.value === "") {
            errors = true;
            this.industry.classList.add('error');
        } else {
            this.industry.classList.remove('error');

        }

        if (this.process.value === "") {
            errors = true;
            this.process.classList.add('error');
        } else {
            this.process.classList.remove('error');
        }

        if (this.qualityLevel.value === "") {
            errors = true;
            this.qualityLevel.classList.add('error');
        } else {
            this.qualityLevel.classList.remove('error');
        }

        if (!errors) {
            this.saveButton.classList.add('disabled');
            dqf_options.contentType = this.contentType.value;
            dqf_options.industry = this.industry.value;
            dqf_options.process = this.process.value;
            dqf_options.qualityLevel = this.qualityLevel.value;
            $('.dqf-box #dqf_switch').trigger('dqfEnable');
            APP.USER.STORE.metadata.dqf_options = dqf_options;
            APP.ModalWindow.onCloseModal();
        }
    }

    resetOptions() {
        this.saveButton.classList.remove('disabled');
        this.contentType.classList.remove('error');
        this.process.classList.remove('error');
        this.industry.classList.remove('error');
        this.qualityLevel.classList.remove('error');
    }

    getOptions() {
        let validUser = !!(this.state.dqfValid || this.state.dqfCredentials.dqfUsername);
        let containerStyle = (validUser && !config.dqf_active_on_project)? {} : {opacity: 0.5, pointerEvents: 'none'};
        let contentTypeOptions = config.dqf_content_types.map(function(item){
            return <option key={item.id} value={item.id}>{item.name}</option>
        });
        let industryOptions = config.dqf_industry.map(function(item){
            return <option key={item.id} value={item.id}>{item.name}</option>
        });
        let processOptions = config.dqf_process.map(function(item){
            return <option key={item.id} value={item.id}>{item.name}</option>
        });
        let qualityOptions = config.dqf_quality_level.map(function(item){
            return <option key={item.id} value={item.id}>{item.name}</option>
        });
        return <div className="dqf-options-container" style={containerStyle}>
            <h2>DQF Options</h2>
            <div className="dqf-option">
                <h4>Content Type</h4>
                <select name="contentType" id="contentType"
                        ref={(select)=> this.contentType = select}
                        onChange={this.resetOptions.bind(this)}>
                    <option value="">Choose</option>
                    {contentTypeOptions}
                </select>
            </div>
            <div className="dqf-option">
                <h4>Industry</h4>
                <select name="industry" id="industry"
                        ref={(select)=> this.industry = select}
                        onChange={this.resetOptions.bind(this)}>
                    <option value="">Choose</option>
                    {industryOptions}
                </select>
            </div>
            <div className="dqf-option">
                <h4>Process</h4>
                <select name="process" id="process"
                        ref={(select)=> this.process = select}
                        onChange={this.resetOptions.bind(this)}>
                    <option value="">Choose</option>
                    {processOptions}
                </select>
            </div>
            <div className="dqf-option">
                <h4>Quality level</h4>
                <select name="qualityLevel" id="qualityLevel"
                        ref={(select)=> this.qualityLevel = select}
                        onChange={this.resetOptions.bind(this)}>
                    <option value="">Choose</option>
                    {qualityOptions}
                </select>
            </div>
            {!config.is_cattool ? (
                <div className="ui primary button" style={{margin:'0 auto', marginTop: '16px'}}
                     onClick={this.saveDQFOptions.bind(this)}
                     ref={(button)=> this.saveButton=button}>Save</div>
            ) : ('')}

        </div>
    }

    getDqfHtml() {

        if (this.state.dqfValid || this.state.dqfCredentials.dqfUsername) {

            return <div className="dqf-container">
                    <h2>DQF Credentials</h2>
                    <div className="user-dqf">
                        <input type="text" name="dqfUsername"  defaultValue={this.state.dqfCredentials.dqfUsername} disabled /><br/>
                        <input type="password" name="dqfPassword"  defaultValue={this.state.dqfCredentials.dqfPassword} disabled  style={{marginTop: '15px'}}/><br/>
                        <div className="ui primary button" style={{marginTop: '15px', marginLeft: '82%'}}
                             onClick={this.clearDQFCredentials.bind(this)}>Clear</div>
                    </div>

                    {this.getOptions()}

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
                            <span style={{color: 'red', fontSize: '14px',position: 'absolute', right: '27%', lineHeight: '24px'}}
                                  className="coupon-message">{this.state.dqfError}</span>
                        </div>
                    </div>
                    {this.getOptions()}
                </div>
        }
    }

    componentWillMount() { }

    componentDidMount() {
        if ( this.state.service && !this.state.service.disabled_at) {
            $(this.checkDrive).attr('checked', true);
        }

        if (this.state.dqfOptions) {
            this.contentType.value = this.state.dqfOptions.contentType;
            this.industry.value = this.state.dqfOptions.industry;
            this.process.value = this.state.dqfOptions.process;
            this.qualityLevel.value = this.state.dqfOptions.qualityLevel;
            this.saveButton.classList.add('disabled');
        } else if (config.dqf_active_on_project) {
            this.contentType.value = config.dqf_selected_content_types;
            this.industry.value = config.dqf_selected_industry;
            this.process.value = config.dqf_selected_process;
            this.qualityLevel.value = config.dqf_selected_quality_level;
        }
    }

    render() {

        return <div className="dqf-modal">
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

export default DQFModal ;
