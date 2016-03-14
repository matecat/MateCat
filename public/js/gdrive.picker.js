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
                addView(google.picker.ViewId.PRESENTATIONS).
                setOAuthToken(oauthToken).
                setDeveloperKey(developerKey).
                setCallback(pickerCallback).
                enableFeature(google.picker.Feature.MINE_ONLY).
                build();
            picker.setVisible(true);
        }
    }

    function pickerCallback( data ) {
        if ( data[google.picker.Response.ACTION] == google.picker.Action.PICKED ) {
            var doc = data[google.picker.Response.DOCUMENTS][0];
            var id = doc.id;
            
            var jsonDoc = {
                "exportIds": [
                    id
                ],
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