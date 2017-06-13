/*
 * Projects Store
 */

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let AnalyzeConstants = require('../constants/AnalyzeConstants');
let assign = require('object-assign');
let Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

let AnalyzeStore = assign({}, EventEmitter.prototype, {

    updateAll: function (teams) {

    },


    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {
    switch(action.actionType) {

    }
});
module.exports = AnalyzeStore;


