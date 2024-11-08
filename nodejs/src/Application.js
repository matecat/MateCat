const io = require( "socket.io" );
const {Reader} = require( './amq/AMQconnector' );
const {MessageHandler} = require( './amq/MessageHandler' );
const {getWebSocketClientAddress} = require( './utils' );
module.exports.Application = class {

    constructor( server, amqConnector, logger, options ) {
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
            this.setClientIpAddressOnSocket( socket );
        } );
    }

    setClientIpAddressOnSocket = ( socket ) => {
        socket.clientAddress = null;
        socket.getClientAddress = () => {
            return socket.clientAddress;
        };
        socket.clientAddress = getWebSocketClientAddress( socket );
    };

}