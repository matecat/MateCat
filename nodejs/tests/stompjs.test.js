import {describe, test, expect} from 'vitest';
import {Client} from '@stomp/stompjs';

/**
 * Loads the real package, unlike AMQconnector.test.js which mocks it.
 * @stomp/stompjs 7.4.0 broke require() and crashed every worker with "Client is not a constructor".
 */
describe('@stomp/stompjs', () => {
  test('exposes Client as a constructor through an ESM import', () => {
    expect(Client).toBeTypeOf('function');
    expect(new Client({})).toBeInstanceOf(Client);
  });
});
