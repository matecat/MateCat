import path from 'node:path';
import {describe, test, expect, beforeEach, afterEach, vi} from 'vitest';
import {loadConfig, startMaster, startWorker} from '../src/bootstrap.js';
import {Application} from '../src/Application.js';
import {notifyUpgrade} from '../src/amq/MessageHandler.js';

vi.mock('../src/utils.js', () => ({
  logger: {
    info: vi.fn(),
    error: vi.fn(),
    debug: vi.fn(),
    verbose: vi.fn(),
  },
}));

vi.mock('../src/Application.js', () => ({
  Application: vi.fn(function () {
    return {start: vi.fn().mockReturnThis()};
  }),
  createAuthMiddleware: vi.fn(),
}));

vi.mock('../src/amq/MessageHandler.js', () => ({
  notifyUpgrade: vi.fn(),
  MessageHandler: vi.fn(function () {
    return {onReceive: vi.fn()};
  }),
  MESSAGE_NAME: 'message',
  GLOBAL_MESSAGES: 'global_messages',
}));

vi.mock('@socket.io/sticky', () => ({
  setupMaster: vi.fn(),
}));

vi.mock('@socket.io/cluster-adapter', () => ({
  setupPrimary: vi.fn(),
}));

describe('loadConfig', () => {
  const configPath = path.resolve(import.meta.dirname, '../config.ini');
  const secretPath = path.resolve(import.meta.dirname, '../../inc/login_secret.dat');

  test('parses config and returns structured result', () => {
    const result = loadConfig(configPath, secretPath);
    expect(result.serverVersion).toBeDefined();
    expect(typeof result.serverVersion).toBe('string');
    expect(result.serverVersion).not.toMatch(/['"]/);
  });

  test('returns numCPUs as number', () => {
    const result = loadConfig(configPath, secretPath);
    expect(typeof result.numCPUs).toBe('number');
    expect(result.numCPUs).toBeGreaterThanOrEqual(1);
  });

  test('returns amqParameters with brokerURL', () => {
    const result = loadConfig(configPath, secretPath);
    expect(result.amqParameters.read_queue).toBeDefined();
    expect(result.amqParameters.connectOptions.brokerURL).toMatch(/^ws:\/\//);
  });

  test('returns serverPath', () => {
    const result = loadConfig(configPath, secretPath);
    expect(result.serverPath).toBeDefined();
  });

  test('returns authSecretKey as string', () => {
    const result = loadConfig(configPath, secretPath);
    expect(typeof result.authSecretKey).toBe('string');
    expect(result.authSecretKey.length).toBeGreaterThan(0);
  });
});

/**
 * Captures the handlers registered with process.on() instead of attaching them to the test runner.
 */
function captureProcessHandlers() {
  const handlers = {};
  vi.spyOn(process, 'on').mockImplementation((event, handler) => {
    handlers[event] = handler;
    return process;
  });
  return handlers;
}

describe('startMaster', () => {
  let mockCluster, mockHttp, mockLog, mockServer, processHandlers;

  beforeEach(() => {
    mockServer = {listen: vi.fn((port, addr, cb) => cb && cb())};
    mockHttp = {createServer: vi.fn(() => mockServer)};
    mockCluster = {
      fork: vi.fn(),
      on: vi.fn(),
    };
    mockLog = {info: vi.fn(), debug: vi.fn(), error: vi.fn()};
    processHandlers = captureProcessHandlers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  test('registers SIGINT and SIGTERM handlers', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(processHandlers.SIGINT).toEqual(expect.any(Function));
    expect(processHandlers.SIGTERM).toEqual(expect.any(Function));
  });

  test('signal handler logs and exits with code 0 after 3 seconds', () => {
    vi.useFakeTimers();
    const mockExit = vi.spyOn(process, 'exit').mockImplementation(() => {});
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    processHandlers.SIGTERM('SIGTERM');

    expect(mockLog.info).toHaveBeenCalledWith(['*** Master: SIGTERM received ***']);
    vi.advanceTimersByTime(2999);
    expect(mockExit).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(mockExit).toHaveBeenCalledWith(0);
  });

  test('forks correct number of workers', () => {
    const appConfig = {
      numCPUs: 3,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(mockCluster.fork).toHaveBeenCalledTimes(3);
  });

  test('registers exit handler on cluster', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(mockCluster.on).toHaveBeenCalledWith('exit', expect.any(Function));
  });

  test('exit handler restarts worker on non-zero exit', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});

    const exitHandler = mockCluster.on.mock.calls.find(c => c[0] === 'exit')[1];
    mockCluster.fork.mockClear();

    exitHandler({id: 1, process: {pid: 123}}, 1, null);
    expect(mockCluster.fork).toHaveBeenCalledTimes(1);
  });

  test('exit handler does NOT restart worker on clean exit (code 0)', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});

    const exitHandler = mockCluster.on.mock.calls.find(c => c[0] === 'exit')[1];
    mockCluster.fork.mockClear();

    exitHandler({id: 1, process: {pid: 123}}, 0, null);
    expect(mockCluster.fork).not.toHaveBeenCalled();
  });

  test('exit handler does NOT restart on null code (signal kill)', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});

    const exitHandler = mockCluster.on.mock.calls.find(c => c[0] === 'exit')[1];
    mockCluster.fork.mockClear();

    exitHandler({id: 1, process: {pid: 123}}, null, 'SIGTERM');
    expect(mockCluster.fork).not.toHaveBeenCalled();
  });

  test('listens on configured address and port', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 8080}},
    };

    startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(mockServer.listen).toHaveBeenCalledWith(8080, '0.0.0.0', expect.any(Function));
  });

  test('returns server instance', () => {
    const appConfig = {
      numCPUs: 1,
      serverVersion: '1.0.0',
      serverPath: '/ws',
      config: {server: {address: '0.0.0.0', port: 3000}},
    };

    const result = startMaster(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(result).toBe(mockServer);
  });
});

describe('startWorker', () => {
  let mockCluster, mockHttp, mockLog, mockServer, processHandlers, originalWorkerId;

  const appConfig = {
    serverVersion: '1.0.0',
    serverPath: '/ws',
    allowedOrigins: ['https://example.com'],
    authSecretKey: 'secret',
    amqParameters: {read_queue: '/queue/test', connectOptions: {brokerURL: 'ws://amq:61614'}},
    config: {redis: {host: 'redis', port: 6379, db: 14}},
  };

  beforeEach(() => {
    originalWorkerId = process.env.worker_id;
    mockServer = {};
    mockHttp = {createServer: vi.fn(() => mockServer)};
    mockCluster = {worker: {id: 7}};
    mockLog = {info: vi.fn(), debug: vi.fn(), error: vi.fn()};
    processHandlers = captureProcessHandlers();
    vi.mocked(Application).mockClear();
    vi.mocked(notifyUpgrade).mockClear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    if (originalWorkerId === undefined) {
      delete process.env.worker_id;
    } else {
      process.env.worker_id = originalWorkerId;
    }
  });

  test('sets worker_id env from cluster worker id', () => {
    startWorker(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(process.env.worker_id).toBe('7');
  });

  test('creates Application with server, amq parameters and options from config', () => {
    startWorker(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(Application).toHaveBeenCalledWith(mockServer, appConfig.amqParameters, {
      path: '/ws',
      workerId: 7,
      serverVersion: '1.0.0',
      allowedOrigins: ['https://example.com'],
      authSecretKey: 'secret',
      redis: {host: 'redis', port: 6379, db: 14},
    });
  });

  test('starts the application and returns it', () => {
    const app = startWorker(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    const instance = vi.mocked(Application).mock.results[0].value;
    expect(instance.start).toHaveBeenCalled();
    expect(app).toBe(instance);
  });

  test('signal handler notifies clients to reload without exiting', () => {
    const app = startWorker(appConfig, {cluster: mockCluster, http: mockHttp, logger: mockLog});
    expect(processHandlers.SIGINT).toEqual(expect.any(Function));

    processHandlers.SIGTERM('SIGTERM');
    expect(mockLog.info).toHaveBeenCalledWith(['SIGTERM received...']);
    expect(notifyUpgrade).toHaveBeenCalledWith(app, false);
  });
});
