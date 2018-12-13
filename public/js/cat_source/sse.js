SSE = {
    init : function() {
        // TODO configure this
        this.baseURL = config.sse_base_url ;
    },
    getSource : function(what) {
        var source = '';
        switch(what) {
            case 'notifications':
                source = '/channel/updates' + '?jid=' + config.id_job + '&pw=' + config.password ;
                break;

            default:
                throw new Exception('source mapping not found');
        }

        return new EventSource(SSE.baseURL + source );
    }
};

SSE.Message = function(data) {
  this._type = data._type;
  this.data = data;

  this.eventIdentifier = 'sse:' + this._type;

  this.isValid = function() {
    var types = new Array('comment', 'ack', 'contribution');
    return ( types.indexOf( this._type ) != -1 ) ;
  }
};

NOTIFICATIONS = {
    start: function (  ) {
        SSE.init();
        var source = SSE.getSource( 'notifications' );

        source.addEventListener( 'message', function ( e ) {
            var message = new SSE.Message( JSON.parse( e.data ) );
            if ( message.isValid() ) {
                $( document ).trigger( message.eventIdentifier, message );
            }
        }, false );

        source.addEventListener( 'error', function ( e ) {
            console.error("SSE: server disconnect")
        }, false );

        $( document ).on( 'sse:ack', function ( ev, message ) {
            config.id_client = message.data.clientId;
        } );
    }
};