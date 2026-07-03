jest.mock('../src/utils', () => ({
  logger: {
    info: jest.fn(),
    error: jest.fn(),
    debug: jest.fn(),
  },
}));

const {MessageHandler, notifyUpgrade} = require('../src/amq/MessageHandler');

function mockApp() {
  return {
    sendBroadcastServiceMessage: jest.fn(),
    sendRoomNotifications: jest.fn(),
    _socketIOServer: {disconnectSockets: jest.fn()},
  };
}

describe('MessageHandler.onReceive', () => {
  let app, handler;

  beforeEach(() => {
    app = mockApp();
    handler = new MessageHandler(app);
  });

  test('rejects malformed message without data.payload', () => {
    handler.onReceive({_type: 'comment', data: {}});
    expect(app.sendRoomNotifications).not.toHaveBeenCalled();
    expect(app.sendBroadcastServiceMessage).not.toHaveBeenCalled();
  });

  test('rejects message with no data at all', () => {
    handler.onReceive({_type: 'comment'});
    expect(app.sendRoomNotifications).not.toHaveBeenCalled();
  });

  test('routes LOGOUT to uid room', () => {
    handler.onReceive({
      _type: 'logout',
      data: {uid: 42, payload: {foo: 'bar'}},
    });
    expect(app.sendRoomNotifications).toHaveBeenCalledWith(
        '42', 'message', {data: {foo: 'bar', _type: 'logout'}},
    );
  });

  test('routes comment to id_job room', () => {
    handler.onReceive({
      _type: 'comment',
      data: {id_job: 99, payload: {text: 'hi'}},
    });
    expect(app.sendRoomNotifications).toHaveBeenCalledWith(
        '99', 'message', {data: {text: 'hi', _type: 'comment'}},
    );
  });

  test('routes quota_exceeded to id_job room', () => {
    handler.onReceive({
      _type: 'quota_exceeded',
      data: {id_job: 77, payload: {limit: 100}},
    });
    expect(app.sendRoomNotifications).toHaveBeenCalledWith(
        '77', 'message', {data: {limit: 100, _type: 'quota_exceeded'}},
    );
  });

  test('routes default type to id_client room', () => {
    handler.onReceive({
      _type: 'contribution',
      data: {id_client: 'abc-123', payload: {result: 'ok'}},
    });
    expect(app.sendRoomNotifications).toHaveBeenCalledWith(
        'abc-123', 'message', {data: {result: 'ok', _type: 'contribution'}},
    );
  });

  test('returns early when default type missing id_client', () => {
    handler.onReceive({
      _type: 'contribution',
      data: {payload: {result: 'ok'}},
    });
    expect(app.sendRoomNotifications).not.toHaveBeenCalled();
  });

  test('broadcasts GLOBAL_MESSAGES', () => {
    handler.onReceive({
      _type: 'global_messages',
      data: {payload: {alert: 'maintenance'}},
    });
    expect(app.sendBroadcastServiceMessage).toHaveBeenCalledWith(
        'message', {data: {alert: 'maintenance', _type: 'global_messages'}},
    );
    expect(app.sendRoomNotifications).not.toHaveBeenCalled();
  });

  test('RELOAD broadcasts and returns', () => {
    handler.onReceive({
      _type: 'force_reload',
      data: {payload: {info: 'reload'}},
    });
    expect(app.sendBroadcastServiceMessage).toHaveBeenCalled();
    expect(app.sendRoomNotifications).not.toHaveBeenCalled();
  });
});

describe('notifyUpgrade', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('broadcasts RELOAD without scheduling exit when isRebooting=false', () => {
    const app = mockApp();
    notifyUpgrade(app, false);
    expect(app.sendBroadcastServiceMessage).toHaveBeenCalledWith(
        'message',
        expect.objectContaining({_type: 'force_reload'}),
    );
    jest.advanceTimersByTime(5000);
    expect(app._socketIOServer.disconnectSockets).not.toHaveBeenCalled();
  });

  test('broadcasts UPGRADE and disconnects when isRebooting=true', () => {
    const mockExit = jest.spyOn(process, 'exit').mockImplementation(() => {});
    const app = mockApp();
    notifyUpgrade(app, true);
    expect(app.sendBroadcastServiceMessage).toHaveBeenCalledWith(
        'message',
        expect.objectContaining({_type: 'upgrade'}),
    );
    jest.advanceTimersByTime(1000);
    expect(app._socketIOServer.disconnectSockets).toHaveBeenCalledWith(true);
    jest.advanceTimersByTime(500);
    expect(mockExit).toHaveBeenCalledWith(0);
    mockExit.mockRestore();
  });
});
