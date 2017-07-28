let TextField = require('../common/TextField').default;
import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';
import update from 'react-addons-update';

class PreferencesModal extends React.Component {


    constructor(props) {
        super(props);

        this.state = {
            service: this.props.service,
            coupon: this.props.metadata.coupon,
            dqfCredentials : {
                dqfUsername : this.props.metadata.dqf_username,
                dqfPassword : this.props.metadata.dqf_password
            },
            couponError: '',
            validCoupon : false,
            dqfValid: false,
            openCoupon: false,
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


    openResetPassword() {
        $('#modal').trigger('openresetpassword');
    }

    componentWillMount() { }

    componentDidMount() {
        if ( this.state.service && !this.state.service.disabled_at) {
            $(this.checkDrive).attr('checked', true);
        }
    }

    checkboxChange() {
        var self = this;
        var selected = $(this.checkDrive).is(':checked');
        if ( selected ) {
            var url = config.gdriveAuthURL;
            var newWindow = window.open(url, 'name', 'height=600,width=900');

            if (window.focus) {
                newWindow.focus();
            }
            var interval = setInterval(function () {
                if (newWindow.closed) {
                    APP.USER.loadUserData().done(function () {
                        var service = APP.USER.getDefaultConnectedService();
                        if ( service ) {
                            self.setState({
                                service: service
                            });
                        } else {
                            $(self.checkDrive).attr('checked', false);
                        }
                    });
                    clearInterval(interval);
                }
            }, 600);
        } else {
            if ( APP.USER.STORE.connected_services.length ) {
                this.disableGDrive().done(function (data) {
                    APP.USER.upsertConnectedService(data.connected_service);
                    self.setState({
                        service: APP.USER.getDefaultConnectedService()
                    });
                });
            }
        }

    }
    submitCoupon() {
        var self = this;
        if (!this.state.validCoupon) {
            return;
        }
        return $.post('/api/app/user/metadata', { metadata : {
            coupon : this.couponInput.value
        }
        }).done( function( data ) {
            if (data) {
                APP.USER.STORE.metadata = data;
                self.setState({
                    coupon: APP.USER.STORE.metadata.coupon
                });
            } else {
                self.setState({
                    couponError: 'Invalid Coupon'
                });
            }
        }).fail(function () {
            self.setState({
                couponError: 'Invalid Coupon'
            });
        });
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


    onKeyPressCoupon(e) {
        var length = this.couponInput.value.length;
        var validCoupon = false;
        if ( length >= 8 ) {
            validCoupon = true;
        }
        this.setState({
            couponError : '',
            validCoupon : validCoupon
        });
        if (e.key === 'Enter') {
            this.submitCoupon();
        }
    }

    disableGDrive() {
        return $.post('/api/app/connected_services/' + this.state.service.id, { disabled: true } );

    }

    logoutUser() {
        $.post('/api/app/user/logout',function(data){
            if ($('body').hasClass('manage')) {
                location.href = config.hostpath + config.basepath;
            } else {
                window.location.reload();
            }
        });
    }

    openCoupon() {
        this.setState({
            openCoupon: true
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
        var gdriveMessage = '';
        if (this.props.showGDriveMessage) {
            gdriveMessage = <div className="preference-modal-message">
                Connect your Google account to translate files in your Drive
            </div>;
        }

        var services_label = 'Allow MateCat to access your files on Google Drive';
        if ( this.state.service && !this.state.service.disabled_at) {
            services_label = 'Connected to Google Drive ('+ this.state.service.email+')';
        }
        var resetPasswordHtml = '';
        if ( this.props.user.has_password ) {
            resetPasswordHtml = <a className="reset-password pull-left"
                                   onClick={this.openResetPassword.bind(this)}>Reset Password</a>;

        }

        var couponHtml = '';
        if ( !this.state.coupon) {
            var buttonClass = (this.state.validCoupon) ? '' : 'disabled';
            couponHtml = <div className="coupon-container">
                {!this.state.openCoupon ? (
                        <div className="open-coupon-link"
                        onClick={this.openCoupon.bind(this)}>Add a coupon</div>
                    ): (
                    <div>
                        <h2 htmlFor="user-coupon">Coupon</h2>
                        <span>If you have received a code, you may be eligible for free credit that you can use for the Outsourcing feature.</span>
                        <input type="text" name="coupon" id="user-coupon" placeholder="Insert your code"
                        onKeyUp={this.onKeyPressCoupon.bind(this)}
                        ref={(input) => this.couponInput = input}/>
                        <a className={"btn-confirm-medium " + buttonClass}  onClick={this.submitCoupon.bind(this)}>Apply</a>
                        <div className="coupon-message">
                            <span style={{color: 'red', fontSize: '14px',position: 'absolute', right: '27%', lineHeight: '24px'}} className="coupon-message">{this.state.couponError}</span>
                        </div>
                    </div>
                    ) }


            </div>
        } else {

            couponHtml = <div className="coupon-container coupon-success">

                <h2 htmlFor="user-coupon">Coupon</h2>
                <span>Credit is available when you outsource translation services.</span>
                <input type="text" name="coupon" id="user-coupon" defaultValue={this.state.coupon} disabled /><br/>
                <div className="coupon-message">
                    <span style={{color: 'green', fontSize: '14px', position: 'absolute', right: '3%', lineHeight: '24px', top: '-38px'}} className="coupon-message">Coupon activated</span>
                </div>


            </div>
        }

        return <div className="preferences-modal">

                     <div className="user-info-form">
                        <div className="avatar-user pull-left">{config.userShortName}</div>
                        <div className="user-name pull-left">
                            <strong>{this.props.user.first_name} {this.props.user.last_name}</strong><br/>
                        <span className="grey-txt">{this.props.user.email}</span><br/>
                        </div>
                         <br/>
                         <div className="user-link">
                            <div id='logoutlink' className="pull-right" onClick={this.logoutUser.bind(this)}>Logout</div>
                             {resetPasswordHtml}
                         </div>
                    </div>
                    <div className="user-info-attributes">

                        <div className="user-reset-password">
                            {gdriveMessage}

                        </div>

                        <h2>Google Drive</h2>
                        <div className="user-gdrive">

                            <div className="onoffswitch-drive">
                                <input type="checkbox" name="onoffswitch" onChange={this.checkboxChange.bind(this)}
                                       ref={(input) => this.checkDrive = input}
                                       className="onoffswitch-checkbox" id="gdrive_check"/>
                                <label className="onoffswitch-label" htmlFor="gdrive_check">
                                    <span className="onoffswitch-inner"/>
                                    <span className="onoffswitch-switch"/>
                                    <span className="onoffswitch-label-status-active">ON</span>
                                    <span className="onoffswitch-label-status-inactive">OFF</span>
                                    <span className="onoffswitch-label-status-unavailable">Unavailable</span>
                                </label>
                            </div>
                            <label>{services_label}</label>
                        </div>

                        {this.getDqfHtml()}

                        {couponHtml}
                    </div>
            </div>;
    }
}

const fieldValidations = [
    RuleRunner.ruleRunner("dqfUsername", "Username", FormRules.requiredRule),
    RuleRunner.ruleRunner("dqfPassword", "Password", FormRules.requiredRule, FormRules.minLength(8)),
];

export default PreferencesModal ;
