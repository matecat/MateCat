const SseChannel = require( 'sse-channel' );
const http = require( 'http' );
const stompit = require( 'stompit' );
const url = require( 'url' );
const qs = require( 'querystring' );
const _ = require( 'lodash' );
const winston = require( 'winston' );
const path = require( 'path' );
const ini = require( 'node-ini' );
const uuid = require( 'uuid' );

const config = ini.parseSync( path.resolve( __dirname, 'config.ini' ) );

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

// Init logger
const logger = winston.createLogger( {
    level: 'info',
    format: winston.format.json(),
    transports: [
        new winston.transports.Console( {level: 'debug'} ),
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
    pingInterval: 15000,
    jsonEncode: true
} );

const corsAllow = ( req, res ) => {

    return allowedOrigins.some( ( element ) => {
        if ( element === '*' || req.headers['origin'] && req.headers['origin'] === element ) {
            res.setHeader( 'Access-Control-Allow-Origin', element );
            res.setHeader( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
            logger.debug( "Allowed domain " + req.headers['origin'] );
            return true;
        } else if ( !req.headers['origin'] ) {
            logger.debug( "Allowed Request from same origin " + req.headers['host'] );
            return true;
        }
    } );
}

//Event triggered when a message is sent to the client
browserChannel.on( 'message', function ( message ) {
    // logger.debug('browserChannel message', message);
} );

//Event triggered when a client disconnect
browserChannel.on( 'disconnect', ( context, res ) => {
    logger.debug( 'browserChannel disconnect', res._clientId );
} );

//Event triggered when a client connect
browserChannel.on( 'connect', ( context, req, res ) => {
    // logger.debug('browserChannel connect ', res._clientId, res._matecatJobId);
    //Send a message to the client to communicate the clientId
    browserChannel.send( {
        data: {
            _type: 'ack',
            clientId: res._clientId
        }
    }, [res] );
    logger.debug( ['New client connection ' + res._clientId ] );

} );

/**
 * We create an HTTP server listening the address in config.path
 * and add new clients to the browserChannel
 */
http.createServer( ( req, res ) => {

    // find job id from requested path
    const parsedUrl = url.parse( req.url );
    const path = parsedUrl.path;

    if ( corsAllow( req, res ) ) {

        if ( path.indexOf( config.server.path ) === 0 ) {

            const query = qs.parse( parsedUrl.query );

            res._clientId = uuid.v4();
            res._matecatJobId = query.jid;
            res._matecatPw = query.pw;

            browserChannel.addClient( req, res );

        } else {
            res.writeHead( 404 );
            res.end();
        }

    } else {
        res.writeHead( 401 );
        res.end();
    }

} ).listen( config.server.port, config.server.address, () => {
    logger.info( 'Listening on http://' + config.server.address + ':' + config.server.port + '/' );
} );

const checkCandidate = ( type, response, message ) => {
    let candidate;
    switch ( type ) {
        case COMMENTS_TYPE:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId !== message.data.id_client;
            break;
        case CONTRIBUTIONS_TYPE:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
            break;
        case CONCORDANCE_TYPE:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
            break;
        case BULK_STATUS_CHANGE_TYPE:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
            break;
        case CROSS_LANG_CONTRIBUTIONS:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
            break;
        case GLOSSARY_TYPE_G:
        case GLOSSARY_TYPE_S:
        case GLOSSARY_TYPE_D:
        case GLOSSARY_TYPE_U:
        case GLOSSARY_TYPE_DO:
        case GLOSSARY_TYPE_SE:
        case GLOSSARY_TYPE_CH:
        case GLOSSARY_TYPE_K:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
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

    let dest = null;
    if ( browserChannel.connections.length !== 0 ) {
        dest = _.filter( browserChannel.connections, ( serverResponse ) => {
            if ( typeof serverResponse._clientId === 'undefined' ) {
                logger.error( ["No valid clientId found in message", message] );
                return false;
            }
            return checkCandidate( message._type, serverResponse, message );
        } );
    }

    if ( !dest ) {
        logger.debug( ["No registered clients on this instance."] );
    } else if ( dest && dest.length === 0 ) {
        logger.debug( ["Skip message, no available recipient found ", message.data.id_client] );
        return;
    } else {
        logger.debug( ['candidate for ' + message._type, dest[0]._clientId] );
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

            // logger.debug( '** event received in client subscription' );

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
