jest.mock('../src/utils', () => ({
  logger: {
    info: jest.fn(),
    error: jest.fn(),
    debug: jest.fn(),
    verbose: jest.fn(),
  },
}));

jest.mock('../src/Application', () => ({
  Application: jest.fn().mockImplementation(() => ({
    start: jest.fn().mockReturnThis(),
  })),
  createAuthMiddleware: jest.fn(),
}));

jest.mock('../src/amq/MessageHandler', () => ({
  notifyUpgrade: jest.fn(),
  MessageHandler: jest.fn().mockImplementation(() => ({onReceive: jest.fn()})),
  MESSAGE_NAME: 'message',
  GLOBAL_MESSAGES: 'global_messages',
}));

jest.mock('@socket.io/sticky', () => ({
  setupMaster: jest.fn(),
}));

jest.mock('@socket.io/cluster-adapter', () => ({
  setupPrimary: jest.fn(),
}));

const path = require('path');
const {loadConfig, startMaster} = require('../server');

describe('loadConfig', () => {
  const configPath = path.resolve(__dirname, '../config.ini');
  const secretPath = path.resolve(__dirname, '../../inc/login_secret.dat');

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

describe('startMaster', () => {
  let mockCluster, mockHttp, mockLog, mockServer;

  beforeEach(() => {
    mockServer = {listen: jest.fn((port, addr, cb) => cb && cb())};
    mockHttp = {createServer: jest.fn(() => mockServer)};
    mockCluster = {
      fork: jest.fn(),
      on: jest.fn(),
    };
    mockLog = {info: jest.fn(), debug: jest.fn(), error: jest.fn()};
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
