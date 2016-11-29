var GDrive = function() {
    'use strict';
    
    var scope = [ 'https://www.googleapis.com/auth/drive.readonly' ];

    this.pickerApiLoaded = false;
    this.authApiLoaded = false;

    function onAuthApiLoad() {
        gdrive.authApiLoaded = true;
    }

    function onPickerApiLoad() {
        gdrive.pickerApiLoaded = true;
    }

    this.createPicker = function(service) {
        var token = JSON.parse( service.oauth_access_token );

        console.log( token.access_token ) ;

        var picker = new google.picker.PickerBuilder().
        addView(google.picker.ViewId.DOCUMENTS).
        addView(google.picker.ViewId.PRESENTATIONS).
        addView(google.picker.ViewId.SPREADSHEETS).

        setOAuthToken( token.access_token ).

        setDeveloperKey(window.developerKey).

        setCallback(pickerCallback).
        enableFeature(google.picker.Feature.MINE_ONLY).
        enableFeature(google.picker.Feature.MULTISELECT_ENABLED).
        build();
        picker.setVisible(true);
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

    this.loadPicker = function() {
        gapi.load( 'auth', { 'callback': onAuthApiLoad } );
        gapi.load( 'picker', { 'callback': onPickerApiLoad } );
    }
};

var gdrive = new GDrive() ;

(function( $, gdrive, undefined) {
    var default_service;

    /**
     * Reads the store and returns the first selectable or first default or null
     *
     * @returns {*}
     */
    function tryToRefreshToken( service ) {
        return $.getJSON( sprintf( '/api/app/connected_services/%s/verify', service.id ) );
    }

    function gdriveInitComplete() {
        return ( gdrive.pickerApiLoaded && gdrive.authApiLoaded ) ;
    }

    function showPreferencesWithMessage() {

        $('#modal').trigger('openpreferences', [{showGDriveMessage: true}]);

    }

    function openGoogleDrivePickerIntent() {
        if ( ! gdriveInitComplete() ) {
            console.log( 'gdriveInitComplete not complete');
            return ;
        }

        // TODO: is this enough to know if the user is logged in?
        if ( !APP.USER.STORE.user ) {
            $('#modal').trigger('openlogin');
            return ;
        }

        var default_service = APP.USER.getDefaultConnectedService();

        if ( !default_service ) {
            showPreferencesWithMessage();
            return ;
        }

        tryToRefreshToken( default_service )
        .done( function( data ) {
            APP.USER.upsertConnectedService( data.connected_service ) ;
            gdrive.createPicker( data.connected_service ) ;
        }).fail( function() {
            showPreferencesWithMessage()
        });
    }

    $(document).on('click', '.load-gdrive', function(e) {
        e.preventDefault();
        openGoogleDrivePickerIntent();
    });

})(jQuery, gdrive );

function onGDriveApiLoad() {
    gdrive.loadPicker();
}
