/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
const io = require('socket.io');
const {setupWorker} = require('@socket.io/sticky');
const {createAdapter} = require('@socket.io/redis-adapter');
const {Reader} = require('./amq/AMQconnector');
const {MessageHandler, MESSAGE_NAME, GLOBAL_MESSAGES} = require('./amq/MessageHandler');
const {logger, getWebSocketClientAddress} = require('./utils');
const {verify} = require('jsonwebtoken');
const Redis = require('ioredis');

const createAuthMiddleware = (authSecretKey, log) => {
  return (socket, next) => {
    let auth = null;

    if (
        socket.handshake.headers &&
        socket.handshake.headers['x-token'] &&
        socket.handshake.headers['x-userid'] &&
        socket.handshake.headers['x-uuid']
    ) {
      auth = socket.handshake.headers;
    } else if (
        socket.handshake.auth &&
        socket.handshake.auth['x-token'] &&
        socket.handshake.auth['x-userid'] &&
        socket.handshake.auth['x-uuid']
    ) {
      auth = socket.handshake.auth;
    } else {
      return next(new Error('Authentication not provided'));
    }

    verify(
        auth['x-token'],
        authSecretKey,
        {
          algorithms: ['HS256'],
        },
        (err, decoded) => {
          if (err) {
            log.error(['Authentication error invalid JWT', err]);
            return next(new Error('Authentication error invalid JWT'));
          }
          if (!Object.prototype.hasOwnProperty.call(decoded, decoded.iss)) {
            log.error(['Authentication error invalid iss claim', decoded.iss]);
            return next(new Error('Authentication error invalid iss claim'));
          }
          if (parseInt(auth['x-userid']) !== decoded[decoded.iss].uid) {
            log.error([
              'Authentication error invalid user id',
              auth['x-userid'],
              decoded[decoded.iss].uid,
            ]);
            return next(new Error('Authentication error invalid user id'));
          }
          socket.user_id = auth['x-userid'].toString();
          socket.uuid = auth['x-uuid'].toString();
          if (auth['x-jobid']) {
            socket.jobId = auth['x-jobid'].toString();
          }
          next();
        },
    );
  };
};

module.exports.createAuthMiddleware = createAuthMiddleware;

module.exports.Application = class {

  constructor(server, amqParameters, options, deps = {}) {
    const RedisClient = deps.Redis || Redis;
    const socketIO = deps.io || io;
    const socketSetupWorker = deps.setupWorker || setupWorker;
    const ReaderClass = deps.Reader || Reader;
    const MessageHandlerClass = deps.MessageHandler || MessageHandler;
    const redisCreateAdapter = deps.createAdapter || createAdapter;

    this.logger = deps.logger || logger;
    this.options = options;

    this.pubGlobalMessageClient = new RedisClient(this.options.redis);

    this.logger.info(['Connecting redis for adapter started', this.options.redis]);
    const pubClient = new RedisClient(this.options.redis);

    pubClient.on('error', (err) => {
      this.logger.error(err);
    });

    const subClient = pubClient.duplicate();

    this._socketIOServer = socketIO(server, {
      path: this.options.path,
      pingTimeout: 5000,
      cors: {
        methods: ['GET', 'POST', 'OPTIONS'],
        origin: this.options.allowedOrigins,
        credentials: true,
      },
      adapter: redisCreateAdapter(pubClient, subClient),
    });

    // setup connection with the primary process
    socketSetupWorker(this._socketIOServer);
    this.logger.info(['Worker started', this.options.workerId]);

    this._socketIOServer.use(createAuthMiddleware(this.options.authSecretKey, this.logger));

    this._Reader = new ReaderClass(
        amqParameters.read_queue,
        amqParameters.connectOptions,
        (new MessageHandlerClass(this)).onReceive,
    );
  }

  start = () => {
    this._socketIOServer.on('connection', (socket) => {

      this.setClientIpAddressOnSocket(socket);

      socket.join([socket.user_id, socket.uuid]);
      if (socket.jobId) {
        socket.join(socket.jobId);
      }

      this.logger.debug('JOINED USER:', {
        'user_id': socket.user_id,
        'uuid': socket.uuid,
        'jobId': socket.jobId,
      });

      this.logger.debug({
        message: 'Client connected ' + socket.id,
        remote_ip: socket.clientAddress,
        user_id: socket.user_id,
        uuid: socket.uuid,
      });

      socket.on('disconnect', () => {
        this.logger.debug({
          message: 'Client disconnected ' + socket.id,
          remote_ip: socket.clientAddress,
          user_id: socket.user_id,
          uuid: socket.uuid,
        });
      });

      socket.emit(MESSAGE_NAME, {
        data: {
          _type: 'ack',
          clientId: socket.uuid,
          user_id: socket.user_id,
          serverVersion: this.options.serverVersion,
        },
      });

      this.dispatchGlobalMessages(socket.uuid);
    });

    return this;

  };

  /**
   * Extends the socket object with a property and a function to retrieve the client ip address
   * @param socket
   */
  setClientIpAddressOnSocket = (socket) => {
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
  dispatchGlobalMessages = (uuid) => {

    const GLOBAL_MESSAGES_LIST_KEY = 'global_message_list_ids';
    const GLOBAL_MESSAGES_ELEMENT_KEY = 'global_message_list_element_';

    this.pubGlobalMessageClient.smembers(
        GLOBAL_MESSAGES_LIST_KEY,
        (err, ids) => {

          if (err || !ids) {
            this.logger.error('Failed to fetch global message ids', err);
            return;
          }

          ids.forEach((id) => {

            this.pubGlobalMessageClient.get(
                GLOBAL_MESSAGES_ELEMENT_KEY + id,
                (err, element) => {

                  if (err) {
                    this.logger.error('Failed to fetch global message element', err);
                    return;
                  }

                  if (element !== null) {
                    let parsed;

                    try {
                      parsed = JSON.parse(element);
                    } catch (parseErr) {
                      this.logger.error('Failed to parse global message element', {id, error: parseErr.message});
                      return;
                    }

                    this.sendRoomNotifications(uuid, MESSAGE_NAME, {
                      data: {
                        _type: GLOBAL_MESSAGES,
                        message: parsed,
                      },
                    });

                    this.logger.debug('Dispatched global message to user: ' + uuid);
                  } else {
                    this.pubGlobalMessageClient.srem(GLOBAL_MESSAGES_LIST_KEY, id);
                  }
                },
            );
          });
        },
    );

  };
};
