import AppDispatcher from '../dispatcher/AppDispatcher';
import CommentsConstants from '../constants/CommentsConstants';
import MBC from '../utils/mbc.main';
const CommentsActions = {

    storeComments: function (comments, user) {
        AppDispatcher.dispatch({
            actionType: CommentsConstants.STORE_COMMENTS,
            comments: comments,
            user: user
        });
    },
    sendComment: function ( text, sid ) {
        MBC.submitComment(text, sid).done((resp)=>{
            if ( resp.errors.length ) {
                // showGenericWarning();
            } else {
                AppDispatcher.dispatch({
                    actionType: CommentsConstants.ADD_COMMENT,
                    comment: resp.data.entries[0],
                    sid: sid
                });
            }
        }).fail(()=>{
            // showGenericWarning();
        })
    },
    resolveThread: function (  sid ) {
        MBC.resolveThread( sid).done((resp)=>{
            if ( resp.errors.length ) {
                // showGenericWarning();
            } else {
                AppDispatcher.dispatch({
                    actionType: CommentsConstants.ADD_COMMENT,
                    comment: resp.data.entries[0],
                    sid: sid
                });
            }
        }).fail(()=>{
            // showGenericWarning();
        })
    },
    updateCommentsFromSse: function ( data ) {
        AppDispatcher.dispatch({
            actionType: CommentsConstants.ADD_COMMENT,
            comment: data,
            sid: data.id_segment
        });
    },
    updateTeamUsers: function ( users ) {
        AppDispatcher.dispatch({
            actionType: CommentsConstants.SET_TEAM_USERS,
            users: users
        });
    },
    setFocusOnCurrentInput: function () {
        AppDispatcher.dispatch({
            actionType: CommentsConstants.SET_FOCUS,
        });
    }

};

module.exports = CommentsActions;
