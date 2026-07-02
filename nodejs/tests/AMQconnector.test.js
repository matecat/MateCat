jest.mock('@stomp/stompjs', () => ({
  Client: jest.fn().mockImplementation(() => ({
    activate: jest.fn(),
    subscribe: jest.fn(),
    onConnect: null,
    onWebSocketError: null,
    onWebSocketClose: null,
    onStompError: null,
  })),
}));

jest.mock('../src/utils', () => ({
  logger: {
    info: jest.fn(),
    error: jest.fn(),
    debug: jest.fn(),
  },
}));

const {Reader} = require('../src/amq/AMQconnector');
const {logger} = require('../src/utils');

function mockMessage(body) {
  return {
    body: typeof body === 'string' ? body : JSON.stringify(body),
    ack: jest.fn(),
    nack: jest.fn(),
  };
}

describe('Reader.subscribe', () => {
  let reader, handler;

  beforeEach(() => {
    handler = jest.fn();
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, handler);
    jest.clearAllMocks();
  });

  test('acks after successful handler call', () => {
    const msg = mockMessage({type: 'test', data: 'hello'});
    reader.subscribe(msg);
    expect(handler).toHaveBeenCalledWith({type: 'test', data: 'hello'});
    expect(msg.ack).toHaveBeenCalled();
    expect(msg.nack).not.toHaveBeenCalled();
  });

  test('nacks on invalid JSON', () => {
    const msg = mockMessage('not valid json{{{');
    msg.body = 'not valid json{{{';
    reader.subscribe(msg);
    expect(handler).not.toHaveBeenCalled();
    expect(msg.nack).toHaveBeenCalled();
    expect(msg.ack).not.toHaveBeenCalled();
  });

  test('nacks when handler throws', () => {
    handler.mockImplementation(() => { throw new Error('handler failed'); });
    const msg = mockMessage({type: 'test'});
    reader.subscribe(msg);
    expect(msg.nack).toHaveBeenCalled();
    expect(msg.ack).not.toHaveBeenCalled();
  });
});

describe('Reader.onConnect', () => {
  let reader;

  beforeEach(() => {
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, jest.fn());
    jest.clearAllMocks();
  });

  test('logs connection and subscribes to queue', () => {
    reader.onConnect({body: 'connected OK'});
    expect(logger.info).toHaveBeenCalledWith('Connected details: connected OK');
    expect(reader.client.subscribe).toHaveBeenCalledWith(
        'test-queue',
        reader.subscribe,
        {ack: 'client-individual'},
    );
  });
});

describe('Reader.onWebSocketError', () => {
  let reader;

  beforeEach(() => {
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, jest.fn());
    jest.clearAllMocks();
  });

  test('logs websocket error event', () => {
    const event = {message: 'connection refused'};
    reader.onWebSocketError(event);
    expect(logger.error).toHaveBeenCalledWith('WebSocket error:', event);
  });
});

describe('Reader.onWebSocketClose', () => {
  let reader;

  beforeEach(() => {
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, jest.fn());
    jest.clearAllMocks();
  });

  test('logs close code', () => {
    reader.onWebSocketClose({code: 1006});
    expect(logger.error).toHaveBeenCalledWith('WebSocket closed. Code:', 1006);
  });
});

describe('Reader.onStompError', () => {
  let reader;

  beforeEach(() => {
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, jest.fn());
    jest.clearAllMocks();
  });

  test('logs broker error message and body', () => {
    reader.onStompError({headers: {message: 'queue not found'}, body: 'details here'});
    expect(logger.error).toHaveBeenCalledWith('Broker reported error: queue not found');
    expect(logger.error).toHaveBeenCalledWith('Additional details: details here');
  });
});
