const http = require( 'http' );
const {Application} = require( "./Application" );
const {ConnectionPool} = require( './amq/AMQconnector' );
const {logger} = require( './utils' );
const ini = require( "node-ini" );
const path = require( "path" );
const uuid = require( 'uuid' );

const config = ini.parseSync( path.resolve( __dirname, 'config.ini' ) );
const SERVER_VERSION = config.server.version.replace( /['"]+/g, '' );
const allowedOrigins = config.cors.allowedOrigins;

// Connections Options for stompit
const parameters = {
    read_queue: config.queue.name,
    connectOptions: {
        host: config.queue.host,
        port: config.queue.port,
    },
    connectHeaders: {
        host: '/',
        login: config.queue.login,
        passcode: config.queue.passcode,
        'heart-beat': '5000,5000'
    }
};

//Initialize AMQ Connection pool
const amqConnector = new ConnectionPool( parameters );

/**
 * We create an HTTP server listening to the address in config.path,
 * and add new clients to the browserChannel
 */
const server = http.createServer();
server.listen( config.server.port, config.server.address, () => {
    logger.info( ['Server version ' + SERVER_VERSION] )
    logger.info( ['Listening on //' + config.server.address + ':' + config.server.port + '/'] )
} );

new Application( server, amqConnector, logger, {allowedOrigins: allowedOrigins} ).start();

( req, res ) => {
    // find job id from the requested path
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

}