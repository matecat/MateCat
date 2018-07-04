let TextField = require('../common/TextField').default;
let DQFCredentials = require('./DQFCredentials').default;

import * as RuleRunner from '../common/ruleRunner';
import * as FormRules from '../common/formRules';

class PreferencesModal extends React.Component {


    constructor(props) {
        super(props);

        this.state = {
            service: this.props.service,
            coupon: this.props.metadata.coupon,
            couponError: '',
            validCoupon : false,
            openCoupon: false,

        };
        this.onKeyPressCoupon = this.onKeyPressCoupon.bind( this );
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

    submitUserChanges() {
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
            this.submitUserChanges();
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
        if (config.dqf_enabled === 1) {
            return <div className="dqf-container">
                    <h2>DQF Credentials</h2>
                <DQFCredentials
                    metadata={this.props.metadata}/>
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
                        <a className="open-coupon-link"
                        onClick={this.openCoupon.bind(this)}>Add a coupon</a>
                    ): (
                    <div>
                        <h2 htmlFor="user-coupon">Coupon</h2>
                        <span>If you have received a code, you may be eligible for free credit that you can use for the Outsourcing feature.</span>
                        <input type="text" name="coupon" id="user-coupon" placeholder="Insert your code"
                        onKeyUp={this.onKeyPressCoupon.bind(this)}
                        ref={(input) => this.couponInput = input}/>
                        <a className={"btn-confirm-medium " + buttonClass}  onClick={this.submitUserChanges.bind(this)}>Apply</a>
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

        let avatar = <div className="avatar-user pull-left">{config.userShortName}</div>;
        if (this.props.metadata.gplus_picture) {
            avatar = <div className="avatar-user pull-left">
                <img src={this.props.metadata.gplus_picture} style={{width: '48px'}}/>
            </div>;
        }


        let googleDrive = null ;

        if ( config.googleDriveEnabled ) {
            googleDrive = <div>
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
            </div>;
        }

        return <div className="preferences-modal">

                     <div className="user-info-form">
                         {avatar}
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

                        {googleDrive}
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
