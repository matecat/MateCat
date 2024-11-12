/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
const winston = require('winston');
require('winston-daily-rotate-file');
const path = require("path");
const {format} = winston;

//exports prototypes
if (!String.prototype.isEmpty) {
  String.prototype.isEmpty = function () {
    return !this || 0 === this.length || !this.trim();
  };
}

const enrichLogContext = winston.format((info) => {
  info['timestamp'] = new Date().toJSON();
  info['worker_id'] = process.env.worker_id || 'primary';
  return info;
});

const ini = require("node-ini");
const config = ini.parseSync(path.resolve(__dirname, '../config.ini'));
const logger = winston.createLogger({
  levels: winston.config.npm.levels,
  transports: [
    new winston.transports.Console({
      level: config.log.level,
      format: format.combine(enrichLogContext(), format.json()),
    }),
    new winston.transports.DailyRotateFile({
      filename: path.resolve(__dirname, config.log.file),
      zippedArchive: true,
      maxSize: '100m',
      level: config.log.level,
      format: format.combine(enrichLogContext(), format.json()),
    }),
  ],
});
exports.logger = logger;

const parseHeaderRemoteAddress = (headersList) => {
  const ipRegex = require('ip-regex');

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
      const ip = headersList[key].split(',').pop().trim(); // avoid ip spoofing by pop-ing
      if (ipRegex.v4({exact: true}).test(ip)) {
        return ip;
      }
    }
  }
};

exports.getWebSocketClientAddress = (socket) => {
  let remoteAddress = parseHeaderRemoteAddress(socket.handshake.headers);
  return remoteAddress ? remoteAddress : socket.handshake.address;
};

exports.setMonitoring = () => {

  const v8 = require('v8');
  // enabling trace-gc
  v8.setFlagsFromString('--trace-gc');
  const {PerformanceObserver} = require('node:perf_hooks');
  // Create a performance observer
  const obs = new PerformanceObserver(list => {
    const entry = list.getEntries()[0];
    /*
    The entry is an instance of PerformanceEntry containing
    metrics of a single garbage collection event.
    For example,
    PerformanceEntry {
      name: 'gc',
      entryType: 'gc',
      startTime: 2820.567669,
      duration: 1.315709,
      kind: 1
    }
    */
    logger.verbose(['GC: ', entry]);
  });

  obs.observe({entryTypes: ['gc']});
  setInterval(() => { logger.verbose(['Memory: ', process.memoryUsage()]); }, 30000);

}