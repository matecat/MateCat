/*
 * TodoStore
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var SegmentConstants = require('../constants/SegmentConstants');
var assign = require('object-assign');


// Todo : Possiamo gestire la persistenza qui dentro con LokiJS

var SegmentStore = assign({}, EventEmitter.prototype, {

    emitChange: function(args) {
        this.emit(SegmentConstants.HIGHLIGHT_EDITAREA, args);
    },
    /**
     * @param {string} event
     * @param {function} callback
     */
    /*addListener: function(event, callback) {
        this.on(event, callback);
    },*/

    /**
     * @param {function} callback
     */
    /*removeStoreListener: function(event, callback) {
        this.removeListener(event, callback);
    }*/
});


// Register callback to handle all updates
AppDispatcher.register(function(action) {
    var text;

    switch(action.actionType) {
        case SegmentConstants.HIGHLIGHT_EDITAREA:
            SegmentStore.emitChange(action.id);
            break;
        default:
        // no op
    }
});

module.exports = SegmentStore;