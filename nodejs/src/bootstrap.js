/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 * Time: 11:38
 */
import cluster from 'node:cluster';
import http from 'node:http';
import fs from 'node:fs';
import {setupMaster} from '@socket.io/sticky';
import {setupPrimary} from '@socket.io/cluster-adapter';
import ini from 'node-ini';
import {Application} from './Application.js';
import {logger} from './utils.js';
import {notifyUpgrade} from './amq/MessageHandler.js';

export const loadConfig = (configPath, secretPath) => {
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

export const startMaster = (appConfig, deps = {}) => {
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

export const startWorker = (appConfig, deps = {}) => {
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
