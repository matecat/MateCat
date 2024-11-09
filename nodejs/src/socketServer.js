const http = require( 'http' );
const {Application} = require( "./Application" );
const {ConnectionPool} = require( './amq/AMQconnector' );
const {logger} = require( './utils' );
const ini = require( "node-ini" );
const path = require( "path" );
const fs = require( 'node:fs' );

const config = ini.parseSync( path.resolve( __dirname, '../config.ini' ) );
const SERVER_VERSION = config.server.version.replace( /['"]+/g, '' );
const allowedOrigins = config.cors.allowedOrigins;
const auth_secret_key = fs.readFileSync( '../../inc/login_secret.dat', 'utf8' );

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

new Application( server, amqConnector, {serverVersion: SERVER_VERSION, allowedOrigins: allowedOrigins, authSecretKey: auth_secret_key} ).start();