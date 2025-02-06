/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
const io = require("socket.io");
const {setupWorker} = require("@socket.io/sticky");
const {createAdapter} = require("@socket.io/redis-adapter");
const {Reader} = require('./amq/AMQconnector');
const {MessageHandler, MESSAGE_NAME, GLOBAL_MESSAGES} = require('./amq/MessageHandler');
const {logger, getWebSocketClientAddress} = require('./utils');
const {verify} = require('jsonwebtoken');
const Redis = require("ioredis");
module.exports.Application = class {

  constructor(server, amqConnector, options) {
    this._registry = {
      allSockets: new Map(),
    };
    this.logger = logger;
    this.options = options;

    // setup redis adapter
    logger.info(['Connecting redis for adapter started', this.options.redis])
    const pubClient = new Redis(this.options.redis);

    pubClient.on("error", (err) => {
      logger.error(err);
    });

    const subClient = pubClient.duplicate();

    this._socketIOServer = io(server, {
      path: this.options.path,
      pingTimeout: 5000,
      //Set cors origin
      cors: {
        methods: ["GET", "POST", "OPTIONS"],
        origin: this.options.allowedOrigins,
        credentials: true,
      },
      adapter: createAdapter(pubClient, subClient),
    });

    // setup connection with the primary process
    setupWorker(this._socketIOServer);
    logger.info(['Worker started', this.options.workerId])

    this._socketIOServer.use((socket, next) => {

      let auth = [];

      if (
        socket.handshake.headers &&
        socket.handshake.headers['x-token'] &&
        socket.handshake.headers['x-userid'] &&
        socket.handshake.headers['x-uuid']
      ) {
        auth = socket.handshake.headers;
      } else if (socket.handshake.auth) {
        auth = socket.handshake.auth;
      } else {
        next(new Error('Authentication not provided'));
      }

      verify(
        auth["x-token"],
        this.options.authSecretKey,
        {
          algorithms: ['HS256'],
        },
        function (err, decoded) {
          if (err) {
            logger.error(['Authentication error invalid JWT', err])
            return next(new Error('Authentication error invalid JWT'));
          }
          if (parseInt(auth['x-userid']) !== decoded.context.uid) {
            logger.error(['Authentication error invalid user id', auth['x-userid'], decoded.context.uid]);
            return next(new Error('Authentication error invalid user id'));
          }
          socket.user_id = auth['x-userid'];
          socket.uuid = auth['x-uuid'];
          if (auth['x-jobid']) {
            socket.jobId = auth['x-jobid'];
          }
          next();
        }
      );

    });

    this._amqConnector = amqConnector;
    this._amqMessageHandler = new MessageHandler(this);
    this._Reader = new Reader(
      amqConnector.read_queue,
      this._amqMessageHandler.onReceive,
      amqConnector.channelFactory
    );
  }

  start = () => {
    this._socketIOServer.on('connection', (socket) => {

      /*
       * Initialize some custom socket.io features
       */
      this.setClientIpAddressOnSocket(socket);

      socket.join([socket.user_id, socket.uuid]);
      if (socket.jobId) {
        socket.join(socket.jobId);
      }

      this.logger.debug({
        message: 'Client connected ' + socket.id,
        remote_ip: socket.getClientAddress(),
        user_id: socket.user_id,
        uuid: socket.uuid,
      });

      socket.on('disconnect', () => {
        logger.debug({
          message: 'Client disconnected ' + socket.id,
          remote_ip: socket.getClientAddress(),
          user_id: socket.user_id,
          uuid: socket.uuid,
        });
      });

      socket.emit(MESSAGE_NAME, {
        data: {
          _type: 'ack',
          clientId: socket.uuid,
          user_id: socket.user_id,
          serverVersion: this.options.serverVersion
        }
      });

      this.dispatchGlobalMessages();
    });

    return this;

  }

  /**
   * Extends the socket object with a property and a function to retrieve the client ip address
   * @param socket
   */
  setClientIpAddressOnSocket = (socket) => {
    socket.clientAddress = null;
    socket.getClientAddress = () => {
      return socket.clientAddress;
    };
    socket.clientAddress = getWebSocketClientAddress(socket);
  };

  sendBroadcastServiceMessage = (type, message) => {
    this._socketIOServer.emit(type, message);
  };

  /**
   *
   * @param room
   * @param type
   * @param message
   */
  sendRoomNotifications = (room, type, message) => {
    this._socketIOServer.to(room).emit(type, message);
  };

  /**
   * Dispatch global messages
   */
  dispatchGlobalMessages = () => {
    const GLOBAL_MESSAGES_LIST_KEY = 'global_message_list_ids';
    const GLOBAL_MESSAGES_ELEMENT_KEY = 'global_message_list_element_';

    const pubClient = new Redis(this.options.redis);
    const socketIOServer = this._socketIOServer;

    pubClient.smembers(GLOBAL_MESSAGES_LIST_KEY, function(err, ids) {

      ids.length > 0 && ids.map((id) => {
        pubClient.get( GLOBAL_MESSAGES_ELEMENT_KEY + id, function (err, element) {
          if ( element !== null ) {
            socketIOServer.emit(MESSAGE_NAME, {
              data: {
                _type: GLOBAL_MESSAGES,
                message: JSON.parse( element )
              }
            });
          } else {
            pubClient.srem( GLOBAL_MESSAGES_LIST_KEY + id );
          }
        });
      });
    });
  };
};
