const SseChannel = require( 'sse-channel' );
const http = require( 'http' );
const os = require( 'os' );
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
const CONTRIBUTIONS_TYPE = 'contribution';
const CONCORDANCE_TYPE = 'concordance';
const CROSS_LANG_CONTRIBUTIONS = 'cross_language_matches';
const BULK_STATUS_CHANGE_TYPE = 'bulk_segment_status_change';

// Init logger
winston.add( winston.transports.DailyRotateFile, {filename: path.resolve( __dirname, config.log.file )} );
winston.level = config.log.level;

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
        if ( req.headers['origin'] && req.headers['origin'] === element ) {
            res.setHeader( 'Access-Control-Allow-Origin', element );
            res.setHeader( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
            return true;
        }
    } );
}

//Event triggered when a message is sent to the client
browserChannel.on( 'message', function ( message ) {
    // winston.debug('browserChannel message', message);
} );

//Event triggered when a client disconnect
browserChannel.on( 'disconnect', function ( context, res ) {
    winston.debug( 'browserChannel disconnect', res._clientId );
} );

//Event triggered when a client connect
browserChannel.on( 'connect', function ( context, req, res ) {
    // winston.debug('browserChannel connect ', res._clientId, res._matecatJobId);
    //Send a message to the client to communicate the clientId
    browserChannel.send( {
        data: {
            _type: 'ack',
            clientId: res._clientId
        }
    }, [res] );
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

} ).listen( config.server.port, config.server.address, function () {
    winston.debug( 'Listening on http://' + config.server.address + ':' + config.server.port + '/' );
} );

const checkCandidate = function ( type, response, message ) {
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
        default:
            candidate = false;
    }
    return candidate;
};

const stompMessageReceived = function ( body ) {
    const message = JSON.parse( body );

    const dest = _.filter( browserChannel.connections, function ( serverResponse ) {
        if ( typeof serverResponse._clientId === 'undefined' ) {
            return false;
        }

        const candidate = checkCandidate( message._type, serverResponse, message );

        if ( candidate ) {
            if ( message._type === CONTRIBUTIONS_TYPE ) {
                winston.debug( 'Contribution segment-id: ' + message.data.payload.id_segment );
            }
            winston.debug( 'candidate found', serverResponse._clientId );
        }

        return candidate;
    } );

    message.data.payload._type = message._type;

    browserChannel.send( {
        data: message.data.payload
    }, dest );
};

const startStompConnection = function () {

    /**
     * Start connection with the amq queue
     */
    stompit.connect( connectOptions, function ( error, client ) {

        if ( typeof client === 'undefined' ) {
            setTimeout( startStompConnection, 10000 );
            winston.debug( "** client error, restarting connection in 10 seconds", error );
            return;
        }

        client.subscribe( subscribeHeaders, function ( error, message ) {
            winston.debug( '** event received in client subscription' );

            if ( error ) {
                winston.debug( '!! subscribe error ' + error.message );

                client.disconnect();
                startStompConnection();

                return;
            }

            message.readString( 'utf-8', function ( error, body ) {

                if ( error ) {
                    winston.debug( '!! read message error ' + error.message );
                } else {
                    stompMessageReceived( body );
                    message.ack();
                }
            } );
        } );
    } );
};

startStompConnection();
