$.extend(APP, {
    setLoginEvents: function () {
        APP.ModalWindow = ReactDOM.render(
            React.createElement(ModalWindow),
            $("#modal")[0]
        );

        $('#modal').on('closemodal', function () {
            APP.ModalWindow.onCloseModal();
        });

        $('#modal').on('opensuccess', function (e, param) {
            APP.ModalWindow.showModalComponent(SuccessModal, param, param.title);
        });

        $('#modal').on('confirmregister', function (e, param) {
            var style = {
                width: '25%',
                maxWidth: '450px'
            };
            APP.ModalWindow.showModalComponent(ConfirmRegister, param, 'Confirm Registration', style);
        });

        $('#modal').on('openpreferences', function (e, param) {
            e.preventDefault();
            e.stopPropagation();
            var props = {
                user: APP.USER.STORE.user,
                metadata: (APP.USER.STORE.metadata) ? APP.USER.STORE.metadata : {}
            };
            if (APP.USER.STORE.connected_services && APP.USER.STORE.connected_services.length ) {
                props.service = APP.USER.getDefaultConnectedService();
            }
            if (param) {
                $.extend(props, param);
            }
            APP.ModalWindow.showModalComponent(PreferencesModal, props, 'Profile');
        });
        $('#modal').on('openresetpassword', function () {
            APP.ModalWindow.showModalComponent(ResetPasswordModal, {}, "Reset Password");
        });
        $('#modal').on('openforgotpassword', function () {
            var props = {};
            if (config.showModalBoxLogin == 1) {
                props.redeemMessage = true
            }
            var style = {
                width: '577px',
            };
            APP.ModalWindow.showModalComponent(ForgotPasswordModal, props, "Forgot Password", style);
        });
        $('#modal').on('openregister', function () {
            var props = {
                googleUrl: config.authURL
            };
            if (config.showModalBoxLogin == 1) {
                props.redeemMessage = true
            }
            APP.ModalWindow.showModalComponent(RegisterModal, props, "Register Now");
        });
        $('#modal').on('openlogin', function (e, param) {
            if ( $('.popup-tm.open').length) {
                UI.closeTMPanel();
            }
            var title = '';
            var style = {
                'width': '80%',
                'maxWidth': '800px',
                'minWidth': '600px'
            };
            var props = {
                googleUrl: config.authURL
            };

            if (config.showModalBoxLogin == 1) {
                props.redeemMessage = true;
                title = 'Add project to your management panel';
            }

            if (param) {
                $.extend(props, param);
            }
            APP.ModalWindow.showModalComponent(LoginModal, props, title, style);
        });

        //Link footer
        $('.user-menu-preferences').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('#modal').trigger('openpreferences');
            return false;
        });

        $('#welcomebox:has(.authLink)').click(function(e){
            e.preventDefault();
            e.stopPropagation();
            $('#modal').trigger('openlogin');
            return false;
        });

        $( '#sign-in' ).click( function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            $('#modal').trigger('openlogin');
            return false;
        } );

        $( '#sign-in-o, #sign-in-o-mt' ).click( function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            $( '#sign-in' ).trigger( 'click' );
            return false;
        } );

        if (config.showModalBoxLogin == 1) {
            $('#modal').trigger('openlogin');
        }

        this.checkForPopupToOpen();
    },
    checkForPopupToOpen: function () {

        var openFromFlash = APP.lookupFlashServiceParam("popup");
        if ( !openFromFlash ) return ;
        var modal$ = $('#modal');

        switch ( openFromFlash[ 0 ].value ) {
            case "passwordReset":
                modal$.trigger('openresetpassword');
                break;
            case "profile":
                // TODO: optimized this, establish a list of events to happen after user data is loaded
                APP.USER.loadUserData().done( function() {
                    modal$.trigger('openpreferences');
                });
                break;
            case "login":
                modal$.trigger('openlogin');
                break;
        }
    }
});