( function() {
    'use strict';
    
    var scope = [ 'https://www.googleapis.com/auth/drive.readonly' ];

    var pickerApiLoaded = false;

    function onAuthApiLoad() {
        if( !oauthToken ) {
            if( isToOpenPickerAfterLogin() ) {
                setOpenPickerAfterLogin( 'false' );
            } else {
                $(".login-google").show();
                setOpenPickerAfterLogin( 'true' );
            }
        }
    }

    function onPickerApiLoad() {
        pickerApiLoaded = true;
        createPicker();
    }

    function handleAuthResult( authResult ) {
        if ( authResult && !authResult.error ) {
            oauthToken = authResult.access_token;
            createPicker();
        }
    }

    function createPicker() {
        if ( pickerApiLoaded && oauthToken ) {
            if( isToOpenPickerAfterLogin() ) {
                setOpenPickerAfterLogin( 'false' );
            }

            var picker = new google.picker.PickerBuilder().
                addView(google.picker.ViewId.DOCUMENTS).
                addView(google.picker.ViewId.PRESENTATIONS).
                addView(google.picker.ViewId.SPREADSHEETS).
                setOAuthToken(oauthToken).
                setDeveloperKey(developerKey).
                setCallback(pickerCallback).
                enableFeature(google.picker.Feature.MINE_ONLY).
                enableFeature(google.picker.Feature.MULTISELECT_ENABLED).
                build();
            picker.setVisible(true);
        }
    }

    function pickerCallback( data ) {
        if ( data[google.picker.Response.ACTION] == google.picker.Action.PICKED ) {
            var exportIds = [];

            var countDocuments = data[google.picker.Response.DOCUMENTS].length;

            for( var i = 0; i < countDocuments; i++ ) {
                var doc = data[google.picker.Response.DOCUMENTS][i];
                var id = doc.id;

                exportIds[i] = id;
            }

            APP.addGDriveFile( exportIds );
        }
    }

    function setOpenPickerAfterLogin( openPicker ) {
        localStorage[ 'openPicker' ] = openPicker;
    }

    function isToOpenPickerAfterLogin() {
        return ( localStorage[ 'openPicker' ] === 'true' );
    }

    function loadPicker() {
        if ( pickerApiLoaded && oauthToken ) {
            createPicker();
        } else {
            gapi.load( 'auth', { 'callback': onAuthApiLoad } );
            gapi.load( 'picker', { 'callback': onPickerApiLoad } );
        }
    }

    $( document ).ready( function () {
        $('.load-gdrive').click( function () {
            loadPicker();
        });

        if( isToOpenPickerAfterLogin() ) {
            loadPicker();
        }
    });
})();