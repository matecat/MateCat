/*
 * Projects Store
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var ManageConstants = require('../constants/ManageConstants');
var assign = require('object-assign');
var Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

var TeamsStore = assign({}, EventEmitter.prototype, {
    
    teams : [
        {
            name: 'Ebay',
            users: [{
                userMail: 'chloe.king@translated.net',
                userFullName: 'Chloe King',
                userShortName: 'CK'

            },{
                userMail: 'owen.james@translated.net',
                userFullName: 'Owen	James',
                userShortName: 'OJ'

            },{
                userMail: 'stephen.powell@translated.net',
                userFullName: 'Stephen Powell',
                userShortName: 'SP'

            }]
        },
        {
            name: 'MSC',
            users: [{
                userMail: 'lillian.lambert@translated.net',
                userFullName: 'Lillian	Lambert',
                userShortName: 'LL'

            },{
                userMail: 'joe.watson@translated.net',
                userFullName: 'Joe	Watson',
                userShortName: 'JW'

            },{
                userMail: 'rachel.sharp@translated.net',
                userFullName: 'Rachel	Sharp',
                userShortName: 'RS'

            },{
                userMail: 'dan.marshall@translated.net',
                userFullName: 'Dan	Marshall',
                userShortName: 'DM'

            }]
        },
        {
            name: 'Translated',
            users: [{
                userMail: 'vanessa.simpson@translated.net',
                userFullName: 'Vanessa	Simpson',
                userShortName: 'VS'

            },{
                userMail: 'dan.howard@translated.net',
                userFullName: 'Dan	Howard',
                userShortName: 'DH'

            },{
                userMail: 'keith.kelly@translated.net',
                userFullName: 'Keith	Kelly',
                userShortName: 'KC'

            }]
        }
    ],

    users : [],

    updateAll: function (teams) {
        this.projects = Immutable.fromJS(teams);
    },


    addTeam: function(team) {
        this.teams = this.teams.concat(Immutable.fromJS([team]));
    },

    removeTeam: function (team) {
        var index = this.teams.indexOf(team);
        this.teams = this.teams.delete(index);
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case ManageConstants.RENDER_TEAMS:
            TeamsStore.updateAll(action.teams);
            TeamsStore.emitChange(action.actionType, TeamsStore.teams);
            break;
        case ManageConstants.REMOVE_TEAM:
            TeamsStore.removeTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams);
            break;
        case ManageConstants.ADD_TEAM:
            TeamsStore.addTeam(action.team);
            TeamsStore.emitChange(ManageConstants.RENDER_TEAMS, TeamsStore.teams);
            break;


    }
});

module.exports = TeamsStore;
