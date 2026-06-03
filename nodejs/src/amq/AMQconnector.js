/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
const {logger} = require('../utils');
const {Client} = require('@stomp/stompjs');

module.exports.Reader = class {

  constructor(queue_name, connectOptions, handler) {
    this.queue_name = queue_name;
    this.messageHandler = handler;
    this.client = new Client(connectOptions);

    this.subscribe = this.subscribe.bind(this);
    this.client.onConnect = (frame) => {
      logger.info('Connected details: ' + frame.body);
      this.client.subscribe(
          this.queue_name,
          this.subscribe,
          {ack: 'client-individual'},
      );
    };

    this.client.onWebSocketError = (event) => {
      logger.error('WebSocket error:', event);
    };

    this.client.onWebSocketClose = (event) => {
      logger.error('WebSocket closed. Code:', event.code);
    };

    this.client.onStompError = (frame) => {
      logger.error('Broker reported error: ' + frame.headers['message']);
      logger.error('Additional details: ' + frame.body);
    };

    if (!this.client.connected) {
      this.client.activate();
    }

  }

  subscribe(message) {
    const quote = JSON.parse(message.body);
    logger.debug('Received message from new queue', quote);
    this.messageHandler(quote);
    message.ack();
  }

};
