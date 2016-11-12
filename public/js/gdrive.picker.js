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

    function getDefaultService() {
        if ( APP.STORE.USER.connected_services.length ) {
            var selectable = $( APP.STORE.USER.connected_services).filter( function() {
                return !this.expired_at && !this.disabled_at ;
            });
            var defaults =  $( selectable ).filter(function() {
                return this.is_default ;
            });
            return defaults[0] || selectable[0] ;
        }
    }

    function tryToRefreshToken( service ) {
        return $.get( sprintf( '/api/app/connected_services/%s/verify', service.id ) );
    }

    $(document).on('click', '.load-gdrive', function() {

        // is this enough to know if the user is logged in?
        if ( APP.STORE.USER.user ) {
           var default_service = getDefaultService();

           if ( default_service ) {
               if ( ! ( gdrive.pickerApiLoaded && gdrive.authApiLoaded ) ) return ;
               // check if the token expired and try to refresh

               tryToRefreshToken( default_service )
               .done( function( data ) {
                   console.log( data ) ;
                   gdrive.createPicker( default_service ) ;
               }).fail( function() {
                   $('#modal').trigger('openpreferences');
                   console.debug( arguments );
               });

           }
           else {
               $('#modal').trigger('openpreferences');
               // TODO: open preferences panel to link a gdrive account
           }

       } else {
            $('#modal').trigger('openlogin');

           // TODO: show signup form
       }
    });

})(jQuery, gdrive );

function onGDriveApiLoad() {
    gdrive.loadPicker();
}
