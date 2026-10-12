import {describe, test, expect, beforeEach, vi} from 'vitest';
import {Reader} from '../src/amq/AMQconnector.js';
import {logger} from '../src/utils.js';

vi.mock('@stomp/stompjs', () => ({
  Client: vi.fn(function () {
    return {
      activate: vi.fn(),
      subscribe: vi.fn(),
      onConnect: null,
      onWebSocketError: null,
      onWebSocketClose: null,
      onStompError: null,
    };
  }),
}));

vi.mock('../src/utils.js', () => ({
  logger: {
    info: vi.fn(),
    error: vi.fn(),
    debug: vi.fn(),
  },
}));

function mockMessage(body) {
  return {
    body: typeof body === 'string' ? body : JSON.stringify(body),
    ack: vi.fn(),
    nack: vi.fn(),
  };
}

describe('Reader.subscribe', () => {
  let reader, handler;

  beforeEach(() => {
    handler = vi.fn();
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, handler);
    vi.clearAllMocks();
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
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, vi.fn());
    vi.clearAllMocks();
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
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, vi.fn());
    vi.clearAllMocks();
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
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, vi.fn());
    vi.clearAllMocks();
  });

  test('logs close code', () => {
    reader.onWebSocketClose({code: 1006});
    expect(logger.error).toHaveBeenCalledWith('WebSocket closed. Code:', 1006);
  });
});

describe('Reader.onStompError', () => {
  let reader;

  beforeEach(() => {
    reader = new Reader('test-queue', {brokerURL: 'ws://localhost:61614'}, vi.fn());
    vi.clearAllMocks();
  });

  test('logs broker error message and body', () => {
    reader.onStompError({headers: {message: 'queue not found'}, body: 'details here'});
    expect(logger.error).toHaveBeenCalledWith('Broker reported error: queue not found');
    expect(logger.error).toHaveBeenCalledWith('Additional details: details here');
  });
});
