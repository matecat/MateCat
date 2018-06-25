/*
 * Analyze Store
 */

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let CatToolConstants = require('../constants/CatToolConstants');
let assign = require('object-assign');

EventEmitter.prototype.setMaxListeners(0);


let CatToolStore = assign({}, EventEmitter.prototype, {

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    }

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {
    switch(action.actionType) {
        case CatToolConstants.SHOW_CONTAINER:
            CatToolStore.emitChange(CatToolConstants.SHOW_CONTAINER, action.container);
            break;
        case CatToolConstants.CLOSE_SUBHEADER:
            CatToolStore.emitChange(CatToolConstants.CLOSE_SUBHEADER);
            break;
        case CatToolConstants.TOGGLE_CONTAINER:
            CatToolStore.emitChange(CatToolConstants.TOGGLE_CONTAINER, action.container);
            break;
        case CatToolConstants.SET_SEGMENT_FILTER:
            CatToolStore.emitChange(CatToolConstants.SET_SEGMENT_FILTER, action.data, action.state);
            break;
        case CatToolConstants.SET_SEARCH_RESULTS:
            CatToolStore.emitChange(CatToolConstants.SET_SEARCH_RESULTS, action.total, action.segments);
            break;
        default:
            CatToolStore.emitChange(action.actionType, action.data);
    }
});
module.exports = CatToolStore;


