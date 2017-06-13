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

    teams : [],

    users : [],

    updateAll: function (teams) {
        this.teams = Immutable.fromJS(teams);
    },


    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {
    switch(action.actionType) {
        case AnalyzeConstants.RENDER_TEAMS:
            AnalyzeStore.updateAll(action.teams);
            AnalyzeStore.emitChange(action.actionType, TeamsStore.teams);
            break;
    }
});
module.exports = AnalyzeStore;


