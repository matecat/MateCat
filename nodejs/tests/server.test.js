import path from 'node:path';
import {describe, test, expect, beforeEach, vi} from 'vitest';

const clusterState = vi.hoisted(() => ({isPrimary: true}));

vi.mock('node:cluster', () => ({
  default: {
    get isPrimary() {
      return clusterState.isPrimary;
    },
  },
}));

vi.mock('../src/bootstrap.js', () => ({
  loadConfig: vi.fn(() => ({serverVersion: '1.0.0'})),
  startMaster: vi.fn(),
  startWorker: vi.fn(),
}));

/**
 * Runs the entry point in a fresh module registry, so each test evaluates server.js again.
 */
async function runEntryPoint() {
  vi.resetModules();
  await import('../server.js');
  return import('../src/bootstrap.js');
}

describe('server.js entry point', () => {
  const nodejsDir = path.resolve(import.meta.dirname, '..');

  beforeEach(() => {
    clusterState.isPrimary = true;
  });

  test('loads config.ini and the login secret relative to the nodejs directory', async () => {
    const {loadConfig} = await runEntryPoint();
    expect(loadConfig).toHaveBeenCalledWith(
        path.join(nodejsDir, 'config.ini'),
        path.resolve(nodejsDir, '../inc/login_secret.dat'),
    );
  });

  test('starts the master in the primary process', async () => {
    const {loadConfig, startMaster, startWorker} = await runEntryPoint();
    expect(startMaster).toHaveBeenCalledWith(loadConfig.mock.results[0].value);
    expect(startWorker).not.toHaveBeenCalled();
  });

  test('starts a worker in a forked process', async () => {
    clusterState.isPrimary = false;
    const {loadConfig, startMaster, startWorker} = await runEntryPoint();
    expect(startWorker).toHaveBeenCalledWith(loadConfig.mock.results[0].value);
    expect(startMaster).not.toHaveBeenCalled();
  });
});
