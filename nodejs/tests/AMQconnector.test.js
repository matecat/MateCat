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
