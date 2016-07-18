/*
 * TodoStore
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var SegmentConstants = require('../constants/SegmentConstants');
var assign = require('object-assign');

EventEmitter.prototype.setMaxListeners(0);
// Todo : Possiamo gestire la persistenza qui dentro con LokiJS

var segments = {};

var SegmentStore = assign({}, EventEmitter.prototype, {

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
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
            SegmentStore.emitChange(action.actionType, action.id);
            break;
        case SegmentConstants.REPLACE_CONTENT:
            SegmentStore.emitChange(action.actionType, action.id, action.text);
            break;
        default:
    }
});

module.exports = SegmentStore;