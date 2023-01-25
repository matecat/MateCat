/**
 *
 */
(function ( $, undefined ) {


    $.extend( UI, {
        decorateMMTRow: function () {

            $( '.mt-provider' ).each( function ( k, v ) {
                if ( $( v ).text() === 'ModernMT' ) {
                    $( v ).append( $( '<span> ( <a href="https://guides.matecat.com/modernmt-mmt-plug-in" target="_blank">Details</a> )</span>' ) )
                }
            } );

        }
    } );

    UI.decorateMMTRow();

})( jQuery );