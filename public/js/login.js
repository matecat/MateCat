$.extend(APP, {
    setLoginEvents: function () {
        APP.ModalWindow = ReactDOM.render(
            React.createElement(ModalWindow),
            $("#modal")[0]
        );
        $('#logoutlink').on('click',function(event){
            //stop form submit
            event.preventDefault();
            $.post('/ajaxLogout',{logout:1},function(data){
                if('unlogged'==data){
                    //ok, unlogged
                    if($('body').hasClass('manage')) {
                        location.href = config.hostpath + config.basepath;
                    } else {
                        window.location.reload();
                    }
                }
            });
        });

        $('.user-menu-preferences').on('click', function () {
            $('#modal').trigger('openpreferences');
        });

        $('#modal').on('opensuccess', function (e, param) {
            APP.ModalWindow.showModalComponent(SuccessModal, param, param.title);
        });

        $('#modal').on('openpreferences', function (e, param) {
            var props = {
                user: APP.USER.STORE.user,
                service: APP.USER.STORE.connected_services[0]
            };
            if (param) {
                props = param;
                $.extend(props, param);
            }
            APP.ModalWindow.showModalComponent(PreferencesModal, props, 'Preferences');
        });
        $('#modal').on('openresetpassword', function () {
            APP.ModalWindow.showModalComponent(ResetPasswordModal, {}, "Reset Password");
        });
        $('#modal').on('openforgotpassword', function () {
            APP.ModalWindow.showModalComponent(ForgotPasswordModal, {}, "Forgot Password");
        });
        $('#modal').on('openregister', function () {
            var props = {
                googleUrl: $('#loginlink').attr('href')
            };
            APP.ModalWindow.showModalComponent(RegisterModal, props, "Register Now");
        });
        $('#modal').on('openlogin', function () {
            if ( $('.popup-tm.open').length) {
                UI.closeTMPanel();
            }
            var style = {
                'width': '80%',
                'maxWidth': '800px',
                'minWidth': '600px'
            };
            var props = {
                googleUrl: $('#loginlink').attr('href')
            };
            APP.ModalWindow.showModalComponent(LoginModal, props, 'Login or register', style);
        });

        /// TODO
        $('a.authLink').click(function(e){
            e.preventDefault();
            $('#modal').trigger('openlogin');
        });

        $( '#sign-in' ).click( function ( e ) {
            e.preventDefault();
            $('#modal').trigger('openlogin');
        } );

        $( '#sign-in-o, #sign-in-o-mt' ).click( function ( e ) {
            $( '#sign-in' ).trigger( 'click' );
        } );

        this.checkForPopupToOpen();
    },
    checkForPopupToOpen: function () {
        var openFromFlash = APP.lookupFlashServiceParam("open");
        if ( !openFromFlash ) return ;

        var modal$ = $('#modal');
        switch ( openFromFlash[ 0 ].value ) {
            case "passwordReset":
                modal$.trigger('openresetpassword');
                break;
            case "preference":
                modal$.trigger('openpreferences');
                break;
        }
    }
});