const stompit = require( 'stompit' );
const { logger } = require( '../utils' );

module.exports.ConnectionPool = class {

    constructor( parameters ) {

        const { read_queue, write_queue, connectOptions } = parameters;

        // Connections Options for stompit
        this.connectOptions = connectOptions;
        this.read_queue = read_queue;
        this.write_queue = write_queue;

        this.connectionManager = new stompit.ConnectFailover( [ this.connectOptions ] );

        this.connectionManager.on( 'error', ( error ) => {
            logger.error( 'ConnectionManager Error', error );
        } );

        this.connectionManager.on( 'connect', ( msg ) => {
            // logger.info( 'Connection Manager connected', msg );
        } );

        this.channelFactory = new stompit.ChannelFactory( this.connectionManager );

    }

};

module.exports.Reader = class {

    static name = 'Reader';

    constructor( queue_name, handler, channelFactory ) {
        this.queue_name = queue_name;
        this.messageHandler = handler;
        this.channelFactory = channelFactory;
        this.subscribe = this.subscribe.bind( this );

        this.channelFactory.channel( ( error, channel ) => {
                if ( error ) {
                    logger.error( 'channel factory error: ' + error.message );
                    return;
                }
                this.subscribe( channel );
            }
        );

    }

    static getClassName() {
        return this.name;
    }

    getClassName() {
        return this.constructor.getClassName();
    }

    subscribe( channel ) {
        /**
         * Start connection with the amq queue
         */

        channel.subscribe(
            {
                destination: this.queue_name,
                ack: 'client-individual',
            },

            ( error, message ) => {

                if ( error ) {
                    logger.error( '!! subscribe error ' + error.message );
                    return;
                }

                message.readString( 'utf-8', ( error, body ) => {

                    if ( error ) {
                        logger.error( '!! read message error ' + error.message );
                        return;
                    }

                    try {
                        const obj = JSON.parse( body );
                        logger.debug( 'Received message ' + obj.messageType );
                        this.messageHandler( obj );
                    } catch ( e ) {
                        logger.error( 'Fail parsing message', e );
                    }

                    channel.ack( message );

                } );

            }
        );

    }
};

module.exports.Writer = class {

    static name = 'Writer';

    constructor( queue_name, channelFactory ) {
        this.queue_name = queue_name;
        this.channelFactory = channelFactory;
        this.send = this.send.bind( this );
        this.connection = null;
    }

    static getClassName() {
        return this.name;
    }

    getClassName() {
        return this.constructor.getClassName();
    }

    send( message, callback ) {

        this.channelFactory.channel( ( error, channel ) => {

            if ( typeof channel === 'undefined' ) {
                logger.error( 'Stompit connect error', error );
                return;
            }

            channel.send(
                {
                    destination: this.queue_name,
                    'content-type': 'application/json',
                    persistent: true,
                },

                JSON.stringify( message ),

                ( error ) => {
                    if ( error ) {
                        logger.error( 'Error while disconnecting: ' + error.message );
                    } else {

                        logger.debug( 'Sent message ' + message.messageType, message );

                        if ( typeof callback === 'function' ) {
                            callback.call();
                        }
                    }
                }
            );

        } );

    }

};