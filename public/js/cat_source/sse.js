SSE = {
    init: function () {
        // TODO configure this
        this.baseURL = config.sse_base_url;
    },
    getSource: function ( what ) {
        var source = '';
        switch ( what ) {
            case 'notifications':
                source = '/channel/updates' + '?jid=' + config.id_job + '&pw=' + config.password;
                break;

            default:
                throw new Exception( 'source mapping not found' );
        }

        return new EventSource( SSE.baseURL + source );
    }
};

SSE.Message = function ( data ) {
    this._type = data._type;
    this.data = data;
    this.types = new Array( 'comment', 'ack', 'contribution', 'concordance', 'bulk_segment_status_change', 'cross_language_matches' );
    this.eventIdentifier = 'sse:' + this._type;

    this.isValid = function () {

        return (this.types.indexOf( this._type ) !== -1);
    }
};

NOTIFICATIONS = {
    start: function () {
        var self = this;
        SSE.init();
        this.source = SSE.getSource( 'notifications' );
        this.addEvents();

    },
    restart: function () {
        this.source = SSE.getSource( 'notifications' );
        this.addEvents();
    },
    addEvents: function () {
        var self = this;
        this.source.addEventListener( 'message', function ( e ) {
            var message = new SSE.Message( JSON.parse( e.data ) );
            if ( message.isValid() ) {
                $( document ).trigger( message.eventIdentifier, message );
            }
        }, false );

        this.source.addEventListener( 'error', function ( e ) {
            console.error( "SSE: server disconnect" );
            // console.log( "readyState: " + NOTIFICATIONS.source.readyState );
            if ( NOTIFICATIONS.source.readyState === 2 ) {
                setTimeout( function () {
                    // console.log( "Restart Event Source" );
                    self.source.close();
                    self.restart();
                }, 5000 );
            }

        }, false );

        $( document ).on( 'sse:ack', function ( ev, message ) {
            config.id_client = message.data.clientId;
        } );
    }
};