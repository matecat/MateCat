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
                            window.open( $(location).attr('protocol') + "//" + $(location).attr('hostname') + "/plugins/mmt/me", "_blank" )
                        }
                    );
                }
            } );

        }
    } );

    UI.decorateMMTRow();

})( jQuery );