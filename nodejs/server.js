/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 * Time: 11:38
 */
import cluster from 'node:cluster';
import path from 'node:path';
import {loadConfig, startMaster, startWorker} from './src/bootstrap.js';

const appConfig = loadConfig(
    path.resolve(import.meta.dirname, './config.ini'),
    path.resolve(import.meta.dirname, '../inc/login_secret.dat'),
);

if (cluster.isPrimary) {
  startMaster(appConfig);
} else {
  startWorker(appConfig);
}
