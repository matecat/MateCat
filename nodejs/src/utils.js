const winston = require( 'winston' );
require( 'winston-daily-rotate-file' );
const {StatusCodes} = require( 'http-status-codes' );
const path = require( "path" );
const {format} = winston;

//exports prototypes
if ( !String.prototype.isEmpty ) {
    String.prototype.isEmpty = function () {
        return !this || 0 === this.length || !this.trim();
    };
}

const addTimestamp = winston.format( ( info ) => {
    info['timestamp'] = new Date().toJSON();
    return info;
} );

const ini = require( "node-ini" );
const config = ini.parseSync( path.resolve( __dirname, '../config.ini' ) );
exports.logger = winston.createLogger( {
    levels: winston.config.syslog.levels,
    transports: [
        new winston.transports.Console( {
            level: config.log.level,
            format: format.combine( addTimestamp(), format.json() ),
        } ),
        new winston.transports.DailyRotateFile( {
            filename: path.resolve( __dirname, config.log.file ),
            zippedArchive: true,
            maxSize: '100m',
            level: config.log.level,
            format: format.combine( addTimestamp(), format.json() ),
        } ),
    ],
} );

let parseHeaderRemoteAddress = ( headersList ) => {
    const ipRegex = require( 'ip-regex' );

    for ( let key of [
        'client-ip',
        'x-forwarded-for',
        'x-forwarded',
        'x-cluster-client-ip',
        'forwarded-for',
        'forwarded',
        'remote-addr',
    ] ) {
        if ( headersList[key] ) {
            const ip = headersList[key].split( ',' ).pop().trim(); // avoid ip spoofing by pop-ing
            if ( ipRegex.v4( {exact: true} ).test( ip ) ) {
                return ip;
            }
        }
    }
};

exports.getWebSocketClientAddress = ( socket ) => {
    let remoteAddress = parseHeaderRemoteAddress( socket.handshake.headers );
    return remoteAddress ? remoteAddress : socket.handshake.address;
};