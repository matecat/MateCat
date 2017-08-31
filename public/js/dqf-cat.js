/**
 * Created by fregini on 10/07/2017.
 */

/**
 * If this file is included in CatTool it means that DQF is enabled for the project.
 */

(function(UI, undefined) {
    var STATUS_USER_NOT_ASSIGNED         = 'not_assigned' ;
    var STATUS_USER_NOT_MATCHING         = 'not_matching' ;
    var STATUS_USER_NO_CREDENTIALS       = 'no_credentials'  ;
    var STATUS_USER_INVALID_CREDENTIALS  = 'invalid_credentials' ;

    var original_isReadonlySegment = UI.isReadonlySegment ;
    var original_messageForClickOnReadonly = UI.messageForClickOnReadonly ;

    var isReadonlySegment = function( segment ) {
        return readonlyStatus() || original_isReadonlySegment( segment ) ;
    }

    var messageForClickOnReadonly = function( section ) {
        if ( readonlyStatus() ) {
            return getSegmentClickMessage();
        }
        else {
            return original_messageForClickOnReadonly() ;
        }
    };

    var getSegmentClickMessage = function( section ) {
        if ( ! config.isLoggedIn ) {
            return 'You must be signed in and have valid DQF credentials to edit this project.';
        }

        switch( config.dqf_user_status ) {
            case STATUS_USER_NOT_MATCHING :
                return 'This DQF project is already assigned to another user.';
            case STATUS_USER_NOT_ASSIGNED :
                return 'You need to set DQF credentials in order to edit this project.';
            case STATUS_USER_INVALID_CREDENTIALS :
            default :
                return 'Generic error' ;
                break ;
        }

    };

    function readonlyStatus() {
        console.log('readonlyStatus');

        switch( config.dqf_user_status ) {
            case STATUS_USER_NOT_MATCHING :
            case STATUS_USER_NOT_ASSIGNED :
            case STATUS_USER_NOT_MATCHING :
            case STATUS_USER_INVALID_CREDENTIALS :
                return true ;
                break ;
            default :
                return false ;
                break ;
        }
    }

    $.extend( UI, {
        isReadonlySegment         : isReadonlySegment,
        messageForClickOnReadonly : messageForClickOnReadonly
    });

})(UI);
