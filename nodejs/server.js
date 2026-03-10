/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 * Time: 11:38
 */
const cluster = require('cluster');
const http = require('http');
const {setupMaster} = require("@socket.io/sticky");
const {setupPrimary} = require("@socket.io/cluster-adapter");
const {Application} = require("./src/Application");
const {AmqConnectionManager} = require('./src/amq/AMQconnector');
const {logger} = require('./src/utils');
const ini = require("node-ini");
const path = require("path");
const fs = require('node:fs');
const {notifyUpgrade} = require("./src/amq/MessageHandler");

const config = ini.parseSync(path.resolve(__dirname, './config.ini'));
const SERVER_VERSION = config.server.version.replace(/['"]+/g, '');
const allowedOrigins = config.cors.allowedOrigins;
const auth_secret_key = fs.readFileSync(path.resolve(__dirname, '../inc/login_secret.dat'), 'utf8');
const serverPath = config.server.path;

// Connections Options for stompit
const amqParameters = {
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

const numCPUs = config.server.parallelization || 1;

if (cluster.isPrimary) {

  /**
   * We create an HTTP server listening to the address in config.path,
   * and add new clients to the browserChannel
   */
  const server = http.createServer();

  // setup sticky sessions
  setupMaster(server, {
    loadBalancingMethod: "least-connection",
  });

  // setup connections between the workers
  setupPrimary();

  for (let i = 0; i < numCPUs; i++) {
    cluster.fork();
  }

  cluster.on('exit', (worker, code, signal) => {
    logger.debug(`Worker ${worker.id} : ${worker.process.pid} died`);
    if (code !== null && code !== 0) {
      logger.debug(`Starting a replacement: exit code ${signal || code}`);
      cluster.fork(); // Restart worker
    }
  });

  // setMonitoring();
  logger.info(['Server version ' + SERVER_VERSION]);
  logger.info(['Listening on //' + config.server.address + ':' + config.server.port + '/' + serverPath]);

  ['SIGINT', 'SIGTERM'].forEach(
    signal => process.on(signal, (sig) => {
      logger.info(['*** Master: ' + sig + ' received ***']);
      setTimeout(() => {
        process.exit(0);
      }, 3000);
    })
  );

  server.listen(config.server.port, config.server.address, () => {
    logger.info(['Master started'])
  });

} else {

  process.env.worker_id = cluster.worker.id;

  /**
   * We create an HTTP server listening to the address in config.path,
   * and add new clients to the browserChannel
   */
  const server = http.createServer();

  //Initialize AMQ Connection pool
  const amqConnector = new AmqConnectionManager(amqParameters);

  let app = new Application(server, amqConnector, {
    path: serverPath,
    workerId: cluster.worker.id,
    serverVersion: SERVER_VERSION,
    allowedOrigins: allowedOrigins,
    authSecretKey: auth_secret_key,
    redis: config.redis
  }).start();

  ['SIGINT', 'SIGTERM'].forEach(
    signal => process.on(signal, (sig) => {
      logger.info([sig + ' received...']);
      notifyUpgrade(app);
    })
  );

}