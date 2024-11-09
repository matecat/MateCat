const {logger} = require( "../utils" );
module.exports.MessageHandler = class {

    constructor( application ) {
        this.application = application;
        this.onReceive = this.onReceive.bind( this );
    }

    onReceive = ( message ) => {
        message.data.payload._type = message._type;
        logger.debug( [
            "Sending message",
            message._type,
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
