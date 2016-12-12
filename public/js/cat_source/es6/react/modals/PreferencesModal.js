class PreferencesModal extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            service: this.props.service,
            coupon: this.props.metadata.coupon,
            couponError: ''
        };

        this.onKeyPressCopupon = this.onKeyPressCopupon.bind( this );
    }

    openResetPassword() {
        $('#modal').trigger('openresetpassword');
    }

    componentWillMount() { }

    componentDidMount() {
        if ( this.state.service && !this.state.service.disabled_at) {
            $(this.checkDrive).attr('checked', true);
        } else {

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

    onKeyPressCopupon() {
        this.setState({ couponError : '' } );
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

    render() {
        var gdriveMessage = '';
        if (this.props.showGDriveMessage) {
            gdriveMessage = <div className="preference-modal-message">
                Connect a Google Drive account to add files
            </div>;
        }

        var services_label = 'Connect your Google Drive';
        if ( this.state.service && !this.state.service.disabled_at) {
            services_label = 'Connected to '+ this.state.service.email+' Google Drive';
        }
        var resetPasswordHtml = '';
        if ( this.props.user.has_password ) {
            resetPasswordHtml = <a className="reset-password pull-right"
                                   onClick={this.openResetPassword.bind(this)}>Reset Password</a>;

        }

        this.spanStyle = {
            color: 'red',
            fontSize: '14px'
        };

        var couponHtml = '';
        if ( !this.state.coupon) {
            couponHtml = <div className="coupon-container">
                            <div className="half-form half-form-left">
                                    <label htmlFor="user-coupon">Coupon</label><br/>
                                    <input type="text" name="coupon" id="user-coupon"
                                           onKeyPress={(e) => { (e.key === 'Enter' ? this.submitUserChanges() : this.onKeyPressCopupon) }}
                                           ref={(input) => this.couponInput = input}/><br/>
                                    <div className="validation-error">
                                        <span style={this.spanStyle} className="text">{this.state.couponError}</span>
                                    </div>
                                </div>
                                <div className="half-form half-form-right">
                                <a className="btn-confirm-medium" onClick={this.submitUserChanges.bind(this)}>Save changes</a>
                            </div>
                        </div>
        } else {
            this.spanStyle = {
                color: 'green',
                fontSize: '14px'
            };
            couponHtml = <div className="coupon-container">
                <div className="half-form half-form-left">
                    <label htmlFor="user-coupon">Coupon</label><br/>
                    <input type="text" name="coupon" id="user-coupon" defaultValue={this.state.coupon} disabled /><br/>
                    <div className="validation-error">
                        <span style={this.spanStyle} className="text">Coupon activated</span>
                    </div>
                </div>
            </div>
        }

        // find if the use has the coupon already. If he has then do not show the input field.

        return <div className="preferences-modal">
                    <div className="user-info-form">

                        <div className="half-form half-form-left">
                            <label htmlFor="user-login-name">Name</label><br/>
                            <input type="text" name="name" id="user-login-name" defaultValue={this.props.user.first_name} disabled="true"/><br/>
                        </div>
                        <div className="half-form half-form-right">
                            <label htmlFor="user-login-name">Surname</label><br/>
                            <input type="text" name="name" id="user-login-surname" defaultValue={this.props.user.last_name} disabled="true"/><br/>
                         </div>

                            <label htmlFor="user-login-name">Email</label><br/>
                            <input type="text" name="name" id="user-login-email" defaultValue={this.props.user.email} disabled="true"/><br/>


                        {couponHtml}

                    </div>

                    <div className="user-reset-password">
                        {gdriveMessage}
                        {resetPasswordHtml}
                    </div>

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
                    <br/>
                    <div id='logoutlink' className="pull-right" onClick={this.logoutUser.bind(this)}>Logout</div>
            </div>;
    }
}

export default PreferencesModal ;
