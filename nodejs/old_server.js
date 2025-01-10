const SseChannel = require( 'sse-channel' );
const http = require( 'http' );
const stompit = require( 'stompit' );
const winston = require( 'winston' );
const path = require( 'path' );
const ini = require( 'node-ini' );
const uuid = require( 'uuid' );

const config = ini.parseSync( path.resolve( __dirname, 'config.ini' ) );
const SERVER_VERSION = config.server.version.replace( /['"]+/g, '' );

const AI_ASSISTANT_EXPLAIN_MEANING = 'ai_assistant_explain_meaning';
const LOGOUT_TYPE = 'logout';
const COMMENTS_TYPE = 'comment';
const GLOSSARY_TYPE_G = 'glossary_get';
const GLOSSARY_TYPE_S = 'glossary_set';
const GLOSSARY_TYPE_D = 'glossary_delete';
const GLOSSARY_TYPE_U = 'glossary_update';
const GLOSSARY_TYPE_DO = 'glossary_domains';
const GLOSSARY_TYPE_SE = 'glossary_search';
const GLOSSARY_TYPE_CH = 'glossary_check';
const GLOSSARY_TYPE_K = 'glossary_keys';
const CONTRIBUTIONS_TYPE = 'contribution';
const CONCORDANCE_TYPE = 'concordance';
const CROSS_LANG_CONTRIBUTIONS = 'cross_language_matches';
const BULK_STATUS_CHANGE_TYPE = 'bulk_segment_status_change';
const DISCONNECT_UPGRADE = 'upgrade';
const RELOAD = 'force_reload';

// Init logger
const logger = winston.createLogger( {
    level: 'info',
    format: winston.format.json(),
    transports: [
        new winston.transports.Console( {level: config.log.level} ),
        new winston.transports.File( {filename: path.resolve( __dirname, config.log.file ), level: config.log.level} ),
    ],
} );

const allowedOrigins = config.cors.allowedOrigins;

// Connections Options for stompit
const connectOptions = {
    'host': config.queue.host,
    'port': config.queue.port,
    'connectHeaders': {
        'host': '/',
        'login': config.queue.login,
        'passcode': config.queue.passcode,
        'heart-beat': '5000,5000'
    }
};

const subscribeHeaders = {
    'destination': config.queue.name,
    'ack': 'client-individual'
};

//SSE Channel Options
const browserChannel = new SseChannel( {
    retryTimeout: 250,
    historySize: 300,
    pingInterval: 10000,
    jsonEncode: true
} );

const corsAllow = ( req, res ) => {

    return allowedOrigins.some( ( element ) => {
        if ( element === '*' || req.headers['origin'] && req.headers['origin'] === element ) {
            res.setHeader( 'Access-Control-Allow-Origin', element );
            res.setHeader( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
            logger.debug( ["Allowed domain " + req.headers['origin']] );
            return true;
        } else if ( !req.headers['origin'] ) {
            logger.debug( ["Allowed Request from same origin " + req.headers['host']] );
            return true;
        }
    } );
}

const v8 = require( 'v8' );
// enabling trace-gc
v8.setFlagsFromString( '--trace-gc' );
const {PerformanceObserver} = require( 'node:perf_hooks' );
// Create a performance observer
const obs = new PerformanceObserver( list => {
    const entry = list.getEntries()[0];
    /*
    The entry is an instance of PerformanceEntry containing
    metrics of a single garbage collection event.
    For example:
    PerformanceEntry {
      name: 'gc',
      entryType: 'gc',
      startTime: 2820.567669,
      duration: 1.315709,
      kind: 1
    }
    */
    logger.verbose( ['GC: ', entry] );
} );

obs.observe( {entryTypes: ['gc']} );
setInterval( () => { logger.verbose( ['Memory: ', process.memoryUsage()] ); }, 5000 );

//Event triggered when a message is sent to the client
browserChannel.on( 'message', function ( message ) {
    logger.silly( ['browserChannel message', message] );
} );

//Event triggered when a client disconnect
browserChannel.on( 'disconnect', ( context, res ) => {
    logger.verbose( ['browserChannel disconnect', res._clientId] );
} );

//Event triggered when a client connect
browserChannel.on( 'connect', ( context, req, res ) => {
    // logger.verbose('browserChannel connect ', res._clientId, res._matecatJobId);
    //Send a message to the client to communicate the clientId
    browserChannel.send( {
        data: {
            _type: 'ack',
            clientId: res._clientId,
            serverVersion: SERVER_VERSION
        }
    }, [res] );
    logger.verbose( ['New client connection ' + res._clientId] );

} );

/**
 * We create an HTTP server listening the address in config.path
 * and add new clients to the browserChannel
 */
http.createServer( ( req, res ) => {
    // find job id from requested path
    const parsedUrl = new URL( req.url, `https://${req.headers.host}/` )

    if ( corsAllow( req, res ) ) {
        if ( parsedUrl.pathname.indexOf( config.server.path ) === 0 ) {
            const params = parsedUrl.searchParams
            res._clientId = uuid.v4()
            res._matecatJobId = parseInt( params.get( 'jid' ) );
            res._matecatPw = params.get( 'pw' )
            res._userId = parseInt( params.get( 'uid' ) );
            browserChannel.addClient( req, res )
        } else {
            res.writeHead( 404 )
            res.end()
        }
    } else {
        res.writeHead( 401 )
        res.end()
    }

} ).listen( config.server.port, config.server.address, () => {
    logger.info( ['Server version ' + SERVER_VERSION] )
    logger.info( ['Listening on //' + config.server.address + ':' + config.server.port + '/'] )
} );

['SIGINT', 'SIGTERM'].forEach(
    signal => process.on( signal, ( sig ) => {
        logger.info( [sig + ' received...'] );
        notifyUpgrade();
    } )
);

const notifyUpgrade = ( isReboot = true ) => {

    new Promise( ( resolve, reject ) => {
        if ( browserChannel.connections.length !== 0 ) {

            logger.info( 'Disconnecting clients...' );

            const disconnectMessage = {
                payload: {
                    _type: isReboot ? DISCONNECT_UPGRADE : RELOAD
                }
            };

            browserChannel.send( {
                data: disconnectMessage.payload
            } );

        }

        resolve( isReboot );

    } ).then( ( isReboot ) => {
        if ( isReboot ) {
            logger.info( 'Exit...' );
            browserChannel.close();
            process.exit( 0 );
        }
    } );

}

const checkCandidate = ( type, connection, message ) => {
    let candidate;

    switch ( type ) {
        case AI_ASSISTANT_EXPLAIN_MEANING:
            candidate = connection._clientId === message.data.id_client;
            break;
        case LOGOUT_TYPE:
            candidate = connection._userId === message.data.uid;
            break;
        case COMMENTS_TYPE:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId !== message.data.id_client;
            break;
        case CONTRIBUTIONS_TYPE:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId === message.data.id_client;
            break;
        case CONCORDANCE_TYPE:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId === message.data.id_client;
            break;
        case BULK_STATUS_CHANGE_TYPE:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId === message.data.id_client;
            break;
        case CROSS_LANG_CONTRIBUTIONS:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId === message.data.id_client;
            break;
        case GLOSSARY_TYPE_G:
        case GLOSSARY_TYPE_S:
        case GLOSSARY_TYPE_D:
        case GLOSSARY_TYPE_U:
        case GLOSSARY_TYPE_DO:
        case GLOSSARY_TYPE_SE:
        case GLOSSARY_TYPE_CH:
        case GLOSSARY_TYPE_K:
            candidate = connection._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( connection._matecatPw ) !== -1 &&
                connection._clientId === message.data.id_client;
            break;
        default:
            candidate = false;
    }

    return candidate;
};

const stompMessageReceived = ( body ) => {

    let message = null;
    try {
        message = JSON.parse( body );
    } catch ( e ) {
        logger.error( ["Invalid json payload received ", body] );
        return;
    }

    let dest = [];

    if ( message._type === RELOAD ) {
        logger.info( 'RELOAD: ' + RELOAD + ' message received...' );
        notifyUpgrade( false );
        return;
    } else if ( browserChannel.connections.length !== 0 ) {
        dest = browserChannel.connections.filter( ( connection ) => {

            if ( typeof connection._clientId === 'undefined' ) {
                logger.warn( ["No valid _clientId found in connection list"] ); // invalid client registered or bug ?!?
                return;
            }

            logger.debug( {
                'type': message._type,
                'jid_srv': connection._matecatJobId,
                'jid_msg': message.data.id_job,
                'pw_srv': connection._matecatPw,
                'pw_msg': message.data.passwords,
                'client_id_srv': connection._clientId,
                'client_id_msg': message.data.id_client,
            } );

            return checkCandidate( message._type, connection, message );
        } );
    } else if ( browserChannel.connections.length === 0 ) {
        logger.warn( ["Got a message but there are no registered clients on this instance."] );
        return;
    }

    if ( dest.length === 0 ) {
        logger.silly( ["Skip message, no available recipient found ", message.data.id_client] );
        return;
    } else {
        logger.debug( ['Candidate found for ' + message._type, dest[0]._clientId] );
    }

    message.data.payload._type = message._type;

    browserChannel.send( {
        data: message.data.payload
    }, dest );

};

const startStompConnection = () => {

    /**
     * Start connection with the amq queue
     */
    stompit.connect( connectOptions, ( error, client ) => {

        if ( typeof client === 'undefined' ) {
            setTimeout( startStompConnection, 10000 );
            logger.error( "** client error, restarting connection in 10 seconds", error );
            return;
        }

        client.on( "error", () => {
            client.disconnect();
            startStompConnection();
        } );

        client.subscribe( subscribeHeaders, ( error, message ) => {

            // logger.verbose( '** event received in client subscription' );

            if ( error ) {
                logger.error( '!! subscribe error ' + error.message );

                client.disconnect();
                startStompConnection();

                return;
            }

            message.readString( 'utf-8', ( error, body ) => {

                if ( error ) {
                    logger.error( '!! read message error ' + error.message );
                } else {
                    stompMessageReceived( body );
                    client.ack( message );
                }

            } );
        } );
    } );
};

startStompConnection();
