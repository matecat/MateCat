/**
 *
 */
(function ( $, undefined ) {


    $.extend( UI, {
        decorateMMTRow: function () {

            $( '.mt-provider' ).each( function ( k, v ) {
                if ( $( v ).text() === 'ModernMT' ) {
                    $( v ).append( $( '<span> ( <a href="#" target="_blank">Details</a> )</span>' ) ).on( 'click',
                        function () {
                            window.open( "https://site.matecat.com/support/advanced-features/modernmt-mmt-plug/", "_blank" )
                        }
                    );
                }
            } );

        }
    } );

    UI.decorateMMTRow();

})( jQuery );