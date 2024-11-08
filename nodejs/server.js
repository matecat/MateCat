const http = require('http');
const stompit = require('stompit');
const winston = require('winston');
const path = require('path');
const ini = require('node-ini');
const uuid = require('uuid');

// Carica configurazione
const config = ini.parseSync(path.resolve(__dirname, 'config.ini'));
const SERVER_VERSION = config.server.version.replace(/['"]+/g, '');

const AI_ASSISTANT_EXPLAIN_MEANING = 'ai_assistant_explain_meaning';
const LOGOUT_TYPE = 'logout';
const COMMENTS_TYPE = 'comment';
const GLOSSARY_TYPE_G = 'glossary_get';
const GLOSSARY_TYPE_S = 'glossary_set';
const GLOSSARY_TYPE_D = 'glossary_delete';
const GLOSSARY_TYPE_U = 'glossary_update';
const GLOSSARY_TYPE_DO = 'glossary_domains';
const GLOSSARY_TYPE_SE = 'glossary_search';
const GLOSSARY_TYPE_CH = 'glossary_check';
const GLOSSARY_TYPE_K = 'glossary_keys';
const CONTRIBUTIONS_TYPE = 'contribution';
const CONCORDANCE_TYPE = 'concordance';
const CROSS_LANG_CONTRIBUTIONS = 'cross_language_matches';
const BULK_STATUS_CHANGE_TYPE = 'bulk_segment_status_change';
const DISCONNECT_UPGRADE = 'upgrade';
const RELOAD = 'force_reload';

const logger = winston.createLogger({
  level: 'info',
  format: winston.format.json(),
  transports: [
    new winston.transports.Console({ level: config.log.level }),
    new winston.transports.File({ filename: path.resolve(__dirname, config.log.file), level: config.log.level }),
  ],
});

const allowedOrigins = config.cors.allowedOrigins;

const corsAllow = (req, res) => {
  return allowedOrigins.some((origin) => {
    if (origin === '*' || (req.headers['origin'] && req.headers['origin'] === origin)) {
      res.setHeader('Access-Control-Allow-Origin', origin);
      res.setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET');
      logger.debug(['Allowed domain ' + req.headers['origin']]);
      return true;
    } else if (!req.headers['origin']) {
      logger.debug(['Allowed Request from same origin ' + req.headers['host']]);
      return true;
    }
  });
};

const clients = [];

// Server HTTP con SSE
const server = http.createServer((req, res) => {
  const parsedUrl = new URL(req.url, `http://${req.headers.host}/`);

  if (corsAllow(req, res)) {
    if (parsedUrl.pathname.indexOf(config.server.path) === 0) {
      const params = parsedUrl.searchParams;
      const clientId = uuid.v4();

      res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
      });

      const client = {
        clientId,
        matecatJobId: parseInt(params.get('jid')),
        matecatPw: params.get('pw'),
        userId: parseInt(params.get('uid')),
        res,
      };

      sendEvent(res, { _type: 'ack', clientId, serverVersion: SERVER_VERSION });
      clients.push(client);

      logger.verbose(['New client connection ' + clientId, 'total clients', clients.length]);

      res.on('close', () => {
        const index = clients.findIndex(c => c.clientId === clientId);
        if (index !== -1) {
          clients.splice(index, 1);
          logger.verbose(['Client disconnected', clientId]);
        }
        res.end();  // Chiudi la risposta per liberare risorse
      });

      const intervalId = setInterval(() => {
        if (res.writableEnded) {
          clearInterval(intervalId);
        } else {
          sendEvent(res, { _type: 'ping' });
        }
      }, 5000);
    } else {
      res.writeHead(404);
      res.end();
    }
  } else {
    res.writeHead(401);
    res.end();
  }
});

const sendEvent = (res, data) => {
  res.write(`data: ${JSON.stringify(data)}\n\n`);
};

const notifyClients = (message) => {
  clients.forEach((client) => {
    if (checkCandidate(message._type, client, message)) {
      sendEvent(client.res, { ...message.data.payload, _type: message._type });
      logger.debug(['Message sent to client', client.clientId]);
    }
  });
};

const notifyUpgradeOrReload = (isReboot = true) => {
  const messageType = isReboot ? DISCONNECT_UPGRADE : RELOAD;
  clients.forEach((client) => {
    sendEvent(client.res, { _type: messageType });
    logger.info(`Sent ${messageType} message to client ${client.clientId}`);
  });
};

['SIGINT', 'SIGTERM'].forEach(signal =>
    process.on(signal, () => {
      logger.info(`${signal} received...`);
      notifyUpgradeOrReload(); // Invia messaggio di disconnessione/riavvio ai client
      setTimeout(() => process.exit(0), 1000); // Attende e poi termina il processo
    })
);

const connectOptions = {
  host: config.queue.host,
  port: config.queue.port,
  connectHeaders: {
    host: '/',
    login: config.queue.login,
    passcode: config.queue.passcode,
    'heart-beat': '5000,5000',
  },
};

const subscribeHeaders = {
  destination: config.queue.name,
  ack: 'client-individual',
};

const startStompConnection = () => {
  stompit.connect(connectOptions, (error, client) => {
    if (error || !client) {
      logger.error("STOMP connection error, retrying in 10 seconds");
      setTimeout(startStompConnection, 10000);
      return;
    }

    client.on("error", () => {
      client.disconnect();
      startStompConnection();
    });

    client.subscribe(subscribeHeaders, (error, message) => {
      if (error) {
        logger.error("Subscription error: " + error.message);
        client.disconnect();
        startStompConnection();
        return;
      }

      message.readString("utf-8", (error, body) => {
        if (error) {
          logger.error("Message read error: " + error.message);
          return;
        }
        try {
          const parsedMessage = JSON.parse(body);
          notifyClients(parsedMessage);
          client.ack(message);
        } catch (parseError) {
          logger.error("JSON parse error: " + parseError.message);
        }
      });
    });
  });
};

const checkCandidate = (type, connection, message) => {
  const hasValidJobId = connection.matecatJobId === message.data.id_job;
  const hasValidPassword = message.data.passwords.indexOf( connection.matecatPw ) > -1;
  const isSameClient = connection.clientId === message.data.id_client;

  switch (type) {
    case AI_ASSISTANT_EXPLAIN_MEANING:
      return isSameClient;
    case LOGOUT_TYPE:
      return connection.userId === message.data.uid;
    case COMMENTS_TYPE:
      return hasValidJobId && hasValidPassword && !isSameClient;
    case CONTRIBUTIONS_TYPE:
    case CONCORDANCE_TYPE:
    case BULK_STATUS_CHANGE_TYPE:
    case CROSS_LANG_CONTRIBUTIONS:
    case GLOSSARY_TYPE_G:
    case GLOSSARY_TYPE_S:
    case GLOSSARY_TYPE_D:
    case GLOSSARY_TYPE_U:
    case GLOSSARY_TYPE_DO:
    case GLOSSARY_TYPE_SE:
    case GLOSSARY_TYPE_CH:
    case GLOSSARY_TYPE_K:
      return hasValidJobId && hasValidPassword && isSameClient;
    default:
      return false;
  }
};
server.listen(config.server.port, config.server.address, () => {
  logger.info(`Server version ${SERVER_VERSION} listening on //${config.server.address}:${config.server.port}/`);
});

startStompConnection();
