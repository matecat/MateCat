/**
 * Created by PhpStorm.
 * @author: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 11/11/2024
 */
const {logger} = require("../utils");

const AI_ASSISTANT_EXPLAIN_MEANING = 'ai_assistant_explain_meaning';
const COMMENTS_TYPE = 'comment';
const GLOSSARY_TYPE_G = 'glossary_get';
const GLOSSARY_TYPE_S = 'glossary_set';
const GLOSSARY_TYPE_D = 'glossary_delete';
const GLOSSARY_TYPE_U = 'glossary_update';
const GLOSSARY_TYPE_DO = 'glossary_domains';
const GLOSSARY_TYPE_SE = 'glossary_search';
const GLOSSARY_TYPE_CH = 'glossary_check';
const GLOSSARY_TYPE_K = 'glossary_keys';
const CONTRIBUTIONS_TYPE = 'contribution';
const CONCORDANCE_TYPE = 'concordance';
const CROSS_LANG_CONTRIBUTIONS = 'cross_language_matches';
const BULK_STATUS_CHANGE_TYPE = 'bulk_segment_status_change';

const LOGOUT = 'logout';
const UPGRADE = 'upgrade';
const RELOAD = 'force_reload';
const ENGINE_QUOTA_EXCEEDED = 'quota_exceeded';
const MESSAGE_NAME = 'message';
const GLOBAL_MESSAGES = 'global_messages';

module.exports.MESSAGE_NAME = MESSAGE_NAME;
module.exports.GLOBAL_MESSAGES = GLOBAL_MESSAGES;

module.exports.MessageHandler = class {

  constructor(application) {
    this.application = application;
  }

  onReceive = (message) => {

    if (!message?.data?.payload) {
      logger.error('Malformed AMQ message: missing data.payload', {message});
      return;
    }

    let room;
    message.data.payload._type = message._type;
    switch (message._type) {
      case RELOAD:
        logger.info('RELOAD: ' + RELOAD + ' message received...');
        notifyUpgrade(this.application, false);
        return;
      case LOGOUT:
        logger.info('Forced logout: ' + LOGOUT + ' message received for user ' + message.data.uid + '...');
        room = message.data.uid.toString();
        break;
      case ENGINE_QUOTA_EXCEEDED:
      case COMMENTS_TYPE:
        room = message.data.id_job.toString();
        break;
      case GLOBAL_MESSAGES:
        this.application.sendBroadcastServiceMessage(
            MESSAGE_NAME,
            {data: message.data.payload}
        );

        return;
      default:
        if (!message.data.id_client) {
          logger.error('Missing id_client in AMQ message', {type: message._type, data: message.data});
          return;
        }
        room = message.data.id_client;
        break;
    }

    logger.debug([
      "Sending message to room",
      MESSAGE_NAME,
      room,
      {data: message.data.payload}
    ]);

    this.application.sendRoomNotifications(
      room,
      MESSAGE_NAME,
      {data: message.data.payload}
    );
  }
}

const notifyUpgrade = (application, isRebooting = true) => {

  const disconnectMessage = {
    payload: {
      _type: isRebooting ? UPGRADE : RELOAD
    }
  };

  logger.info(['Disconnecting clients...', disconnectMessage]);

  application.sendBroadcastServiceMessage(
    MESSAGE_NAME,
    {
      _type: (isRebooting ? UPGRADE : RELOAD),
      data: disconnectMessage.payload
    }
  );

  if (isRebooting) {
    setTimeout(() => {
      logger.info('Exit...');
      application._socketIOServer.disconnectSockets(true);
      setTimeout(() => {
        process.exit(0);
      }, 500);
    }, 1000);
  }

}

module.exports.notifyUpgrade = notifyUpgrade;
