import jwt from 'jsonwebtoken';
import {describe, test, expect, beforeEach, vi} from 'vitest';
import {createAuthMiddleware, Application} from '../src/Application.js';

vi.mock('../src/utils.js', () => ({
  logger: {
    info: vi.fn(),
    error: vi.fn(),
    debug: vi.fn(),
  },
  getWebSocketClientAddress: vi.fn(() => '1.2.3.4'),
}));

const SECRET = 'test-secret-key';

function signToken(payload) {
  return jwt.sign(payload, SECRET, {algorithm: 'HS256'});
}

function mockSocket(overrides = {}) {
  return {
    handshake: {
      headers: {},
      auth: {},
      address: '127.0.0.1',
    },
    ...overrides,
  };
}

describe('createAuthMiddleware', () => {
  const log = {error: vi.fn(), info: vi.fn(), debug: vi.fn()};
  let middleware;
  const runMiddleware = (socket) => new Promise((resolve) => middleware(socket, resolve));

  beforeEach(() => {
    log.error.mockClear();
    middleware = createAuthMiddleware(SECRET, log);
  });

  test('authenticates from headers', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 42}});
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': token, 'x-userid': '42', 'x-uuid': 'abc-123'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.user_id).toBe('42');
    expect(socket.uuid).toBe('abc-123');
  });

  test('authenticates from handshake.auth fallback', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 7}});
    const socket = mockSocket({
      handshake: {
        headers: {},
        auth: {'x-token': token, 'x-userid': '7', 'x-uuid': 'def-456'},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.user_id).toBe('7');
    expect(socket.uuid).toBe('def-456');
  });

  test('rejects missing auth', async () => {
    const socket = mockSocket();

    const err = await runMiddleware(socket);
    expect(err).toBeInstanceOf(Error);
    expect(err.message).toBe('Authentication not provided');
  });

  test('rejects invalid JWT', async () => {
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': 'bad-token', 'x-userid': '1', 'x-uuid': 'x'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeInstanceOf(Error);
    expect(err.message).toBe('Authentication error invalid JWT');
  });

  test('rejects invalid iss claim', async () => {
    const token = signToken({iss: 'bogus', matecat: {uid: 1}});
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': token, 'x-userid': '1', 'x-uuid': 'x'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeInstanceOf(Error);
    expect(err.message).toBe('Authentication error invalid iss claim');
  });

  test('rejects uid mismatch', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 42}});
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': token, 'x-userid': '999', 'x-uuid': 'x'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeInstanceOf(Error);
    expect(err.message).toBe('Authentication error invalid user id');
  });

  test('sets jobId when x-jobid present', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 1}});
    const socket = mockSocket({
      handshake: {
        headers: {
          'x-token': token,
          'x-userid': '1',
          'x-uuid': 'u1',
          'x-jobid': '55',
        },
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.jobId).toBe('55');
  });

  test('does not set jobId when x-jobid absent', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 1}});
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': token, 'x-userid': '1', 'x-uuid': 'u1'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.jobId).toBeUndefined();
  });

  test('sets projectId when x-projectid present', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 1}});
    const socket = mockSocket({
      handshake: {
        headers: {
          'x-token': token,
          'x-userid': '1',
          'x-uuid': 'u1',
          'x-projectid': '123',
        },
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.projectId).toBe('123');
  });

  test('does not set projectId when x-projectid absent', async () => {
    const token = signToken({iss: 'matecat', matecat: {uid: 1}});
    const socket = mockSocket({
      handshake: {
        headers: {'x-token': token, 'x-userid': '1', 'x-uuid': 'u1'},
        auth: {},
        address: '127.0.0.1',
      },
    });

    const err = await runMiddleware(socket);
    expect(err).toBeUndefined();
    expect(socket.projectId).toBeUndefined();
  });
});

// --- Application constructor + instance method tests ---

function createMockRedis() {
  const instance = {
    on: vi.fn(),
    duplicate: vi.fn(),
    smembers: vi.fn(),
    get: vi.fn(),
    srem: vi.fn(),
  };
  instance.duplicate.mockReturnValue(instance);
  return vi.fn(function () {
    return instance;
  });
}

function createMockSocketIO() {
  const server = {
    use: vi.fn(),
    on: vi.fn(),
    emit: vi.fn(),
    to: vi.fn(() => ({emit: vi.fn()})),
  };
  return {Server: vi.fn(function () {
    return server;
  }), server};
}

function createMockDeps() {
  const RedisMock = createMockRedis();
  const {Server: ServerMock, server: ioServer} = createMockSocketIO();
  return {
    Redis: RedisMock,
    Server: ServerMock,
    setupWorker: vi.fn(),
    createAdapter: vi.fn(),
    Reader: vi.fn(),
    MessageHandler: vi.fn(function () {
      return {onReceive: vi.fn()};
    }),
    logger: {info: vi.fn(), error: vi.fn(), debug: vi.fn()},
    _ioServer: ioServer,
    _redisInstance: new RedisMock(),
  };
}

function buildApp(deps) {
  const d = deps || createMockDeps();
  const app = new Application(
      {},
      {read_queue: 'test-q', connectOptions: {brokerURL: 'ws://localhost'}},
      {
        redis: {},
        path: '/ws',
        allowedOrigins: '*',
        authSecretKey: SECRET,
        workerId: 1,
        serverVersion: '1.0.0',
      },
      d,
  );
  return {app, deps: d};
}

describe('Application constructor', () => {
  test('creates Redis clients', () => {
    const {deps} = buildApp();
    expect(deps.Redis).toHaveBeenCalled();
  });

  test('creates socket.io server with correct options', () => {
    const {deps} = buildApp();
    expect(deps.Server).toHaveBeenCalledWith(
        {},
        expect.objectContaining({path: '/ws', pingTimeout: 5000}),
    );
  });

  test('calls setupWorker', () => {
    const {deps} = buildApp();
    expect(deps.setupWorker).toHaveBeenCalled();
  });

  test('registers auth middleware', () => {
    const {deps} = buildApp();
    expect(deps._ioServer.use).toHaveBeenCalledWith(expect.any(Function));
  });

  test('creates Reader with queue name', () => {
    const {deps} = buildApp();
    expect(deps.Reader).toHaveBeenCalledWith(
        'test-q',
        {brokerURL: 'ws://localhost'},
        expect.any(Function),
    );
  });

  test('creates MessageHandler with app reference', () => {
    const {deps} = buildApp();
    expect(deps.MessageHandler).toHaveBeenCalledWith(expect.any(Application));
  });
});

describe('Application.start', () => {
  test('registers connection handler and returns this', () => {
    const {app, deps} = buildApp();
    const result = app.start();
    expect(result).toBe(app);
    expect(deps._ioServer.on).toHaveBeenCalledWith('connection', expect.any(Function));
  });

  test('connection handler joins rooms and emits ack', () => {
    const {app, deps} = buildApp();
    app.start();

    const connectionHandler = deps._ioServer.on.mock.calls.find(c => c[0] === 'connection')[1];
    const socket = {
      id: 'sock-1',
      user_id: '42',
      uuid: 'u-abc',
      jobId: '99',
      projectId: '123',
      clientAddress: null,
      join: vi.fn(),
      emit: vi.fn(),
      on: vi.fn(),
    };

    connectionHandler(socket);

    expect(socket.join).toHaveBeenCalledWith(['42', 'u-abc']);
    expect(socket.join).toHaveBeenCalledWith('99');
    expect(socket.join).toHaveBeenCalledWith('123');
    expect(socket.emit).toHaveBeenCalledWith('message', expect.objectContaining({
      data: expect.objectContaining({_type: 'ack', clientId: 'u-abc'}),
    }));
  });

  test('connection handler skips jobId room when absent', () => {
    const {app, deps} = buildApp();
    app.start();

    const connectionHandler = deps._ioServer.on.mock.calls.find(c => c[0] === 'connection')[1];
    const socket = {
      id: 'sock-2',
      user_id: '1',
      uuid: 'u-x',
      clientAddress: null,
      join: vi.fn(),
      emit: vi.fn(),
      on: vi.fn(),
    };

    connectionHandler(socket);
    expect(socket.join).toHaveBeenCalledTimes(1);
    expect(socket.join).toHaveBeenCalledWith(['1', 'u-x']);
  });

  test('connection handler skips projectId room when absent', () => {
    const {app, deps} = buildApp();
    app.start();

    const connectionHandler = deps._ioServer.on.mock.calls.find(c => c[0] === 'connection')[1];
    const socket = {
      id: 'sock-3',
      user_id: '1',
      uuid: 'u-y',
      jobId: '99',
      clientAddress: null,
      join: vi.fn(),
      emit: vi.fn(),
      on: vi.fn(),
    };

    connectionHandler(socket);
    expect(socket.join).toHaveBeenCalledTimes(2);
    expect(socket.join).toHaveBeenCalledWith(['1', 'u-y']);
    expect(socket.join).toHaveBeenCalledWith('99');
  });
});

describe('Application.dispatchGlobalMessages', () => {
  test('dispatches valid messages to room', () => {
    const {app} = buildApp();
    const emitMock = vi.fn();
    app._socketIOServer.to = vi.fn(() => ({emit: emitMock}));

    app.pubGlobalMessageClient.smembers.mockImplementation((key, cb) => {
      cb(null, ['msg-1']);
    });
    app.pubGlobalMessageClient.get.mockImplementation((key, cb) => {
      cb(null, JSON.stringify({alert: 'hello'}));
    });

    app.dispatchGlobalMessages('user-uuid');

    expect(app._socketIOServer.to).toHaveBeenCalledWith('user-uuid');
    expect(emitMock).toHaveBeenCalledWith('message', {
      data: {_type: 'global_messages', message: {alert: 'hello'}},
    });
  });

  test('handles smembers error', () => {
    const {app, deps} = buildApp();
    app.pubGlobalMessageClient.smembers.mockImplementation((key, cb) => {
      cb(new Error('redis down'), null);
    });

    app.dispatchGlobalMessages('user-uuid');
    expect(deps.logger.error).toHaveBeenCalledWith(
        'Failed to fetch global message ids',
        expect.any(Error),
    );
  });

  test('handles get error', () => {
    const {app, deps} = buildApp();
    app.pubGlobalMessageClient.smembers.mockImplementation((key, cb) => {
      cb(null, ['msg-1']);
    });
    app.pubGlobalMessageClient.get.mockImplementation((key, cb) => {
      cb(new Error('timeout'), null);
    });

    app.dispatchGlobalMessages('user-uuid');
    expect(deps.logger.error).toHaveBeenCalledWith(
        'Failed to fetch global message element',
        expect.any(Error),
    );
  });

  test('removes stale id when element is null', () => {
    const {app} = buildApp();
    app.pubGlobalMessageClient.smembers.mockImplementation((key, cb) => {
      cb(null, ['stale-id']);
    });
    app.pubGlobalMessageClient.get.mockImplementation((key, cb) => {
      cb(null, null);
    });

    app.dispatchGlobalMessages('user-uuid');
    expect(app.pubGlobalMessageClient.srem).toHaveBeenCalledWith(
        'global_message_list_ids', 'stale-id',
    );
  });

  test('handles corrupt JSON in element', () => {
    const {app, deps} = buildApp();
    app.pubGlobalMessageClient.smembers.mockImplementation((key, cb) => {
      cb(null, ['bad-json']);
    });
    app.pubGlobalMessageClient.get.mockImplementation((key, cb) => {
      cb(null, 'not{json');
    });

    app.dispatchGlobalMessages('user-uuid');
    expect(deps.logger.error).toHaveBeenCalledWith(
        'Failed to parse global message element',
        expect.objectContaining({id: 'bad-json'}),
    );
  });
});
