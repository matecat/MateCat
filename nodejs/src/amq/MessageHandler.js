const { logger, eventEmitterKeys } = require( '../utils' );

module.exports.MessageHandler = class {

    constructor( application ) {
        this.application = application;
        this.onReceive = this.onReceive.bind( this );
    }

    onReceive = ( message ) => {

        let { socketId } = message;

        const { messageGroup, messageType } = eventEmitterKeys( message.messageType );

        message.messageType = messageType;

        //Global User messages
        if ( message.workspace_id ) {

            logger.debug( '*** Dispatch workspace notification', {
                socketId: socketId,
                user_id: message.user_id,
                workspace_id: message.workspace_id,
                target_id: message.target_id,
                messageGroup: messageGroup,
                messageType: messageType
            } );

            this.application.sendGroupNotifications( message.workspace_id, messageGroup, message );

        } else if ( message.target_id ) {

            // those events come from Lambda/SQS/SNS where there is not a direct gRPC response, but we need to know to which user we must send the message
            logger.debug( '*** *** Emit Event for Editors', {
                socketId: socketId,
                user_id: message.user_id,
                workspace_id: message.workspace_id,
                target_id: message.target_id,
                messageGroup: messageGroup,
                messageType: messageType
            } );

            this.application.sendGroupNotifications( message.target_id, messageGroup, message );

        } else if ( message.user_id ) {

            // those events come from Lambda/SQS/SNS where there is not a direct gRPC response, but we need to know to which user we must send the message
            logger.debug( '*** *** Emit Event for User', {
                socketId: socketId,
                user_id: message.user_id,
                workspace_id: message.workspace_id,
                target_id: message.target_id,
                messageGroup: messageGroup,
                messageType: messageType
            } );

            this.application.sendGroupNotifications( message.user_id, messageGroup, message );

        } else if ( !socketId ) {

            switch ( messageType ) {
                case "transcode-error": // handle transcode error
                    logger.debug( '*** Dispatch transcoding error', message );
                    const socketId = this.application.getSocketIdByEtagId( message.messageBody.eTag );
                    this.application.getEventEmitterBySocketId( socketId ).emit( messageType, { socketId: socketId, message } );
                    break;
                default:
                    logger.debug( 'Emit Event-broadcast', { messageGroup: messageGroup, messageType: messageType } );
                    this.application.sendBroadcastServiceMessage( messageGroup, message );
                    break;
            }


        }
    }
}
