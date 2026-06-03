const {parseHeaderRemoteAddress, getWebSocketClientAddress} = require('../src/utils');

describe('parseHeaderRemoteAddress', () => {
  test('returns first IP from x-forwarded-for chain', () => {
    const headers = {'x-forwarded-for': '1.2.3.4, 10.0.0.1, 172.16.0.1'};
    expect(parseHeaderRemoteAddress(headers)).toBe('1.2.3.4');
  });

  test('returns single IP from x-forwarded-for', () => {
    const headers = {'x-forwarded-for': '192.168.1.1'};
    expect(parseHeaderRemoteAddress(headers)).toBe('192.168.1.1');
  });

  test('client-ip takes priority over x-forwarded-for', () => {
    const headers = {
      'client-ip': '10.0.0.1',
      'x-forwarded-for': '172.16.0.1',
    };
    expect(parseHeaderRemoteAddress(headers)).toBe('10.0.0.1');
  });

  test('returns undefined when no headers present', () => {
    expect(parseHeaderRemoteAddress({})).toBeUndefined();
  });

  test('skips header with invalid IP', () => {
    const headers = {
      'client-ip': 'not-an-ip',
      'x-forwarded-for': '8.8.8.8',
    };
    expect(parseHeaderRemoteAddress(headers)).toBe('8.8.8.8');
  });

  test('returns undefined when all IPs invalid', () => {
    const headers = {'x-forwarded-for': 'garbage'};
    expect(parseHeaderRemoteAddress(headers)).toBeUndefined();
  });
});

describe('getWebSocketClientAddress', () => {
  test('returns parsed header IP when available', () => {
    const socket = {
      handshake: {
        headers: {'x-forwarded-for': '1.2.3.4'},
        address: '127.0.0.1',
      },
    };
    expect(getWebSocketClientAddress(socket)).toBe('1.2.3.4');
  });

  test('falls back to handshake address when no valid header', () => {
    const socket = {
      handshake: {
        headers: {},
        address: '::ffff:127.0.0.1',
      },
    };
    expect(getWebSocketClientAddress(socket)).toBe('::ffff:127.0.0.1');
  });
});
