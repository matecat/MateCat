const io = require( "socket.io" );
const {Reader} = require( './amq/AMQconnector' );
const {MessageHandler} = require( './amq/MessageHandler' );
const {logger, getWebSocketClientAddress} = require( './utils' );
const {verify} = require( 'jsonwebtoken' );
module.exports.Application = class {

    constructor( server, amqConnector, options ) {
        this._registry = {
            allSockets: new Map(),
        };
        this.logger = logger;
        this.options = options;

        this._socketIOServer = io( server, {
            pingTimeout: 60000,
            //Set cors origin
            cors: {
                methods: ["GET", "OPTIONS"],
                origin: this.options.allowedOrigins,
                credentials: true,
            },
            allowEIO3: true,
        } ).use( ( socket, next ) => {

            if (
                socket.handshake.headers &&
                socket.handshake.headers['x-token'] &&
                socket.handshake.headers['x-userid'] &&
                socket.handshake.headers['x-uuid']
            ) {
                verify(
                    socket.handshake.headers['x-token'],
                    this.options.authSecretKey,
                    {
                        algorithms: ['HS256'],
                    },
                    function ( err, decoded ) {
                        if ( err ) {
                            return next( new Error( 'Authentication error' ) );
                        }
                        socket.user_id = socket.handshake.headers['x-userid'];
                        socket.uuid = socket.handshake.headers['x-uuid'];
                        next();
                    }
                );
            } else {
                next( new Error( 'Authentication error' ) );
            }
        } );

        this._amqConnector = amqConnector;
        this._amqMessageHandler = new MessageHandler( this );
        this._Reader = new Reader(
            amqConnector.read_queue,
            this._amqMessageHandler.onReceive,
            amqConnector.channelFactory
        );
    }

    start = () => {
        this._socketIOServer.on( 'connection', ( socket ) => {

            /*
             * Initialize some custom socket.io features
             */
            this.setClientIpAddressOnSocket( socket );

            socket.join( [socket.user_id, socket.uuid] );

            this.logger.debug( {
                message: 'Client connected ' + socket.id,
                remote_ip: socket.getClientAddress(),
                user_id: socket.user_id,
                uuid: socket.uuid,
            } );

            socket.on( 'disconnect', () => {
                logger.debug( {
                    message: 'Client disconnected ' + socket.id,
                    remote_ip: socket.getClientAddress(),
                    user_id: socket.user_id,
                    uuid: socket.uuid,
                } );
            } );

            socket.emit( 'message', {
                data: {
                    _type: 'ack',
                    clientId: socket.uuid,
                    serverVersion: this.options.serverVersion
                }
            } );

        } );

    }

    /**
     * Extends the socket object with a property and a function to retrieve the client ip address
     * @param socket
     */
    setClientIpAddressOnSocket = ( socket ) => {
        socket.clientAddress = null;
        socket.getClientAddress = () => {
            return socket.clientAddress;
        };
        socket.clientAddress = getWebSocketClientAddress( socket );
    };

    sendBroadcastServiceMessage = ( type, message ) => {
        this._socketIOServer.emit( type, message );
    };

    /**
     *
     * @param room
     * @param type
     * @param message
     */
    sendRoomNotifications = ( room, type, message ) => {
        this.getSocketGroup( room ).emit( type, message );
    };

    /**
     *
     * @param room
     * @returns {*}
     */
    getSocketGroup = ( room ) => {
        return this._socketIOServer.to( room );
    };

}