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
    this.client.onConnect = this.onConnect.bind(this);
    this.client.onWebSocketError = this.onWebSocketError.bind(this);
    this.client.onWebSocketClose = this.onWebSocketClose.bind(this);
    this.client.onStompError = this.onStompError.bind(this);

    this.client.activate();
  }

  onConnect(frame) {
    logger.info('Connected details: ' + frame.body);
    this.client.subscribe(
        this.queue_name,
        this.subscribe,
        {ack: 'client-individual'},
    );
  }

  onWebSocketError(event) {
    logger.error('WebSocket error:', event);
  }

  onWebSocketClose(event) {
    logger.error('WebSocket closed. Code:', event.code);
  }

  onStompError(frame) {
    logger.error('Broker reported error: ' + frame.headers['message']);
    logger.error('Additional details: ' + frame.body);
  }

  subscribe(message) {
    try {
      const quote = JSON.parse(message.body);
      logger.debug('Received message from new queue', quote);
      this.messageHandler(quote);
      message.ack();
    } catch (err) {
      logger.error('Failed to process AMQ message: ' + err.message, {body: message.body});
      message.nack();
    }
  }

};
