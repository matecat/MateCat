var GDrive = function() {
    'use strict';
    
    var scope = [ 'https://www.googleapis.com/auth/drive.readonly' ];

    var pickerApiLoaded = false;
    var authApiLoaded = false;
    var isGDriveAccessible = false;
    var isToOpenPicker = false;

    function onAuthApiLoad() {
        authApiLoaded = true;

        if( isToOpenPicker ) {
            createPicker();
        }
    }

    function onPickerApiLoad() {
        pickerApiLoaded = true;

        if( isToOpenPicker ) {
            createPicker();
        }
    }

    function createPicker() {
        if ( pickerApiLoaded && authApiLoaded && oauthToken && isGDriveAccessible ) {
            if( isToOpenPicker ) {
                isToOpenPicker = false;
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
        } else if( isGDriveAccessible === false ) {
            displayGoogleLogin();
            setOpenPickerAfterLogin( 'true' );
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
        $('.load-gdrive').removeClass('load-gdrive-disabled');

        $('.load-gdrive').click( function () {
            createPicker();
        });

        gapi.load( 'auth', { 'callback': onAuthApiLoad } );
        gapi.load( 'picker', { 'callback': onPickerApiLoad } );

        isToOpenPicker = isToOpenPickerAfterLogin();
    }

    function displayGoogleLogin() {
        $("#sign-in").data("oauth", config.gdriveAuthURL);
        $(".login-google").show();

        $('.modal .x-popup').click( function() {
            setOpenPickerAfterLogin( 'false' );
        });
    }

    function verifyGDrive( data ) {
        isGDriveAccessible = data.success;

        loadPicker();
    }

    this.apiLoaded = function () {
        $.ajax({
            cache: false,
            url: '/gdrive/verify',
            dataType: 'json'
        }).done( verifyGDrive );
    };
};

var gdrive = new GDrive();

function onApiLoad() {
    gdrive.apiLoaded();
}