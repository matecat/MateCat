const {logger} = require( "../utils" );

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

module.exports.MessageHandler = class {

    constructor( application ) {
        this.application = application;
        this.onReceive = this.onReceive.bind( this );
    }

    onReceive = ( message ) => {

        if ( message._type === RELOAD ) {
            logger.info( 'RELOAD: ' + RELOAD + ' message received...' );
            notifyUpgrade( this.application, false );
            return;
        }

        message.data.payload._type = message._type;
        logger.debug( [
            "Sending message",
            "message",
            message.data.id_client,
            {data: message.data.payload}
        ] );
        this.application.sendRoomNotifications(
            message.data.id_client,
            message._type,
            {data: message.data.payload}
        );
    }
}

module.exports.notifyUpgrade = notifyUpgrade = ( application, isReboot = true ) => {

    logger.info( 'Disconnecting clients...' );

    const disconnectMessage = {
        payload: {
            _type: isReboot ? UPGRADE : RELOAD
        }
    };

    application.sendBroadcastServiceMessage( "message", {
        _type: (isReboot ? UPGRADE : RELOAD),
        data: disconnectMessage.payload
    } );

    if ( isReboot ) {
        logger.info( 'Exit...' );
        application._socketIOServer.disconnectSockets( true );
        setTimeout( () => {
            process.exit( 0 );
        }, 1000 );
    }

}