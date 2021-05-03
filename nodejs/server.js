var SseChannel = require( 'sse-channel' );
var http = require( 'http' );
var os = require( 'os' );
var stompit = require( 'stompit' );
var url = require( 'url' );
var qs = require( 'querystring' );
var _ = require( 'lodash' );
var winston = require( 'winston' );
var path = require( 'path' );
var ini = require( 'node-ini' );

var config = ini.parseSync( path.resolve( __dirname, 'config.ini' ) );

const COMMENTS_TYPE = 'comment';
const GLOSSARY_TYPE = 'glossary';
const CONTRIBUTIONS_TYPE = 'contribution';
const CONCORDANCE_TYPE = 'concordance';
const CROSS_LANG_CONTRIBUTIONS = 'cross_language_matches';
const BULK_STATUS_CHANGE_TYPE = 'bulk_segment_status_change';

// Init logger
winston.add( winston.transports.DailyRotateFile, {filename: path.resolve( __dirname, config.log.file )} );
winston.level = config.log.level;

// Connections Options for stompit
var connectOptions = {
    'host': config.queue.host,
    'port': config.queue.port,
    'connectHeaders': {
        'host': '/',
        'login': config.queue.login,
        'passcode': config.queue.passcode,
        'heart-beat': '5000,5000'
    }
};

var subscribeHeaders = {
    'destination': config.queue.name,
    'ack': 'client-individual'
};

//SSE Channel Options
var browserChannel = new SseChannel( {
    retryTimeout: 250,
    historySize: 300, // XXX
    pingInterval: 15000,
    jsonEncode: true,
    cors: {
        origins: ['*'] // Defaults to []
    }
} );

/**
 * Function used to create an unique id
 * @param separator
 * @returns {*}
 */
var generateUid = function ( separator ) {
    var delim = separator || "";

    function S4() {
        return (((1 + Math.random()) * 0x10000) | 0).toString( 16 ).substring( 1 );
    }

    return (S4() + S4() + delim + S4());
};

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
http.createServer( function ( req, res ) {
    // find job id from requested path
    var parsedUrl = url.parse( req.url );
    var path = parsedUrl.path;


    if ( path.indexOf( config.server.path ) === 0 ) {
        var query = qs.parse( parsedUrl.query );

        res._clientId = generateUid();
        res._matecatJobId = query.jid;
        res._matecatPw = query.pw;

        browserChannel.addClient( req, res );
    } else {
        res.writeHead( 404 );
        res.end();
    }

} ).listen( config.server.port, config.server.address, function () {
    winston.debug( 'Listening on http://' + config.server.address + ':' + config.server.port + '/' );
} );

var checkCandidate = function ( type, response, message ) {
    var candidate = false;
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
        case GLOSSARY_TYPE:
            candidate = response._matecatJobId === message.data.id_job &&
                message.data.passwords.indexOf( response._matecatPw ) !== -1 &&
                response._clientId === message.data.id_client;
            break;
        default:
            candidate = false;
    }
    return candidate;
};

var stompMessageReceived = function ( body ) {
    var message = JSON.parse( body );

    var dest = _.filter( browserChannel.connections, function ( serverResponse ) {
        if ( typeof serverResponse._clientId === 'undefined' ) {
            return false;
        }

        var candidate = checkCandidate( message._type, serverResponse, message );

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

var startStompConnection = function () {

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
                    return;
                } else {
                    stompMessageReceived( body );
                    message.ack();
                }
            } );
        } );
    } );
};

startStompConnection();
