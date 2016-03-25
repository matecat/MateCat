( function() {
    'use strict';
    
    var scope = [ 'https://www.googleapis.com/auth/drive.readonly' ];

    var pickerApiLoaded = false;
    var oauthToken;
    var oauthEmail;

    function onAuthApiLoad() {
        if( !oauthToken ) {
            window.gapi.auth.authorize ({
                'client_id': clientId,
                'scope': scope,
                'immediate': false,
                'authuser': oauthEmail
            }, handleAuthResult );
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

            var jsonDoc = {
                "exportIds": exportIds,
                "action":"open"
            };
            
            var encodedJson = encodeURIComponent(JSON.stringify(jsonDoc));
            
            $( '<div/>', {
                'class': 'modal-gdrive'
            }).appendTo( $( 'body' ));
            
            window.open('/webhooks/gdrive/open?state=' + encodedJson, '_self');
        }
    }
    
    $( document ).ready( function () {
        $('.load-gdrive').click( function () {
            if ( pickerApiLoaded && oauthToken ) {
                createPicker();
            } else {
                gapi.load( 'auth', { 'callback': onAuthApiLoad } );
                gapi.load( 'picker', { 'callback': onPickerApiLoad } );
            }
        });

        $.getJSON('/webhooks/gdrive/getEmail', function ( response ) {
            if( response && response.hasOwnProperty('email') ) {
                oauthEmail = response.email;
            }
        });
    });
})();