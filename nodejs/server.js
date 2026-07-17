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
const {logger} = require('./src/utils');
const ini = require("node-ini");
const path = require("path");
const fs = require('node:fs');
const {notifyUpgrade} = require("./src/amq/MessageHandler");

const loadConfig = (configPath, secretPath) => {
  const config = ini.parseSync(configPath);
  const serverVersion = config.server.version.replace(/['"]+/g, '');
  const authSecretKey = fs.readFileSync(secretPath, 'utf8');

  return {
    config,
    serverVersion,
    allowedOrigins: config.cors.allowedOrigins,
    authSecretKey,
    serverPath: config.server.path,
    numCPUs: parseInt(config.server.parallelization, 10) || 1,
    amqParameters: {
      read_queue: config.queue.name,
      connectOptions: {
        brokerURL: 'ws://' + config.queue.host + ':' + config.queue.port + '',
        reconnectDelay: 5000,
        heartbeatIncoming: 4000,
        heartbeatOutgoing: 4000,
      },
    },
  };
};

const startMaster = (appConfig, deps = {}) => {
  const clusterMod = deps.cluster || cluster;
  const httpMod = deps.http || http;
  const log = deps.logger || logger;

  const server = httpMod.createServer();

  setupMaster(server, {
    loadBalancingMethod: "least-connection",
  });

  setupPrimary();

  for (let i = 0; i < appConfig.numCPUs; i++) {
    clusterMod.fork();
  }

  clusterMod.on('exit', (worker, code, signal) => {
    log.debug(`Worker ${worker.id} : ${worker.process.pid} died`);
    if (code !== null && code !== 0) {
      log.debug(`Starting a replacement: exit code ${signal || code}`);
      clusterMod.fork();
    }
  });

  log.info(['Server version ' + appConfig.serverVersion]);
  log.info(['Listening on //' + appConfig.config.server.address + ':' + appConfig.config.server.port + '/' + appConfig.serverPath]);

  ['SIGINT', 'SIGTERM'].forEach(
    signal => process.on(signal, (sig) => {
      log.info(['*** Master: ' + sig + ' received ***']);
      setTimeout(() => {
        process.exit(0);
      }, 3000);
    })
  );

  server.listen(appConfig.config.server.port, appConfig.config.server.address, () => {
    log.info(['Master started']);
  });

  return server;
};

const startWorker = (appConfig, deps = {}) => {
  const clusterMod = deps.cluster || cluster;
  const httpMod = deps.http || http;
  const log = deps.logger || logger;

  process.env.worker_id = clusterMod.worker.id;

  const server = httpMod.createServer();

  const app = new Application(server, appConfig.amqParameters, {
    path: appConfig.serverPath,
    workerId: clusterMod.worker.id,
    serverVersion: appConfig.serverVersion,
    allowedOrigins: appConfig.allowedOrigins,
    authSecretKey: appConfig.authSecretKey,
    redis: appConfig.config.redis,
  }).start();

  ['SIGINT', 'SIGTERM'].forEach(
    signal => process.on(signal, (sig) => {
      log.info([sig + ' received...']);
      notifyUpgrade(app, false);
    })
  );

  return app;
};

// --- Main entry point ---
if (require.main === module) {
  const appConfig = loadConfig(
      path.resolve(__dirname, './config.ini'),
      path.resolve(__dirname, '../inc/login_secret.dat'),
  );

  if (cluster.isPrimary) {
    startMaster(appConfig);
  } else {
    startWorker(appConfig);
  }
}

module.exports = {loadConfig, startMaster, startWorker};
