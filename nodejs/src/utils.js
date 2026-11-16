/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
import winston from 'winston';
import 'winston-daily-rotate-file';
import path from 'node:path';
import v8 from 'node:v8';
import {PerformanceObserver} from 'node:perf_hooks';
import ini from 'node-ini';
import ipRegex from 'ip-regex';

const {format} = winston;

export const enrichLogContext = winston.format((info) => {
  info['timestamp'] = new Date().toJSON();
  info['worker_id'] = process.env.worker_id || 'primary';
  return info;
});

export const createLogger = (logConfig) => {
  const transports = [
    new winston.transports.Console({
      level: logConfig.level,
      format: format.combine(enrichLogContext(), format.json()),
    }),
  ];

  if (logConfig.file) {
    transports.push(new winston.transports.DailyRotateFile({
      filename: path.resolve(import.meta.dirname, logConfig.file),
      zippedArchive: true,
      maxSize: '100m',
      level: logConfig.level,
      format: format.combine(enrichLogContext(), format.json()),
    }));
  }

  return winston.createLogger({
    levels: winston.config.npm.levels,
    transports,
  });
};

const config = ini.parseSync(path.resolve(import.meta.dirname, '../config.ini'));
export const logger = createLogger(config.log);

export const parseHeaderRemoteAddress = (headersList) => {
  for (let key of [
    'client-ip',
    'x-forwarded-for',
    'x-forwarded',
    'x-cluster-client-ip',
    'forwarded-for',
    'forwarded',
    'remote-addr',
  ]) {
    if (headersList[key]) {
      const ip = headersList[key].split(',')[0].trim();
      if (ipRegex.v4({exact: true}).test(ip)) {
        return ip;
      }
    }
  }
};

export const getWebSocketClientAddress = (socket) => {
  let remoteAddress = parseHeaderRemoteAddress(socket.handshake.headers);
  return remoteAddress ? remoteAddress : socket.handshake.address;
};

export const setMonitoring = () => {

  v8.setFlagsFromString('--trace-gc');
  const obs = new PerformanceObserver(list => {
    const entry = list.getEntries()[0];
    logger.verbose(['GC: ', entry]);
  });

  obs.observe({entryTypes: ['gc']});
  setInterval(() => { logger.verbose(['Memory: ', process.memoryUsage()]); }, 30000);

}
