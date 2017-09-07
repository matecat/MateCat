/**
 *
 */
(function ( $, undefined ) {


    $.extend( UI, {
        decorateMMTRow: function () {

            $( '.mt-provider' ).each( function ( k, v ) {
                if ( $( v ).text() === 'MMT' ) {
                    $( v ).append( $( '<span> ( <a href="#" target="_blank">Details</a> )</span>' ) ).on( 'click',
                        function () {
                            window.open( "http://dev.matecat.com/plugins/mmt/me", "_blank" )
                        }
                    );
                }
            } );

        }
    } );

    UI.decorateMMTRow();

})( jQuery );