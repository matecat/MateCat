/*
 * Projects Store
 */

let AppDispatcher = require('../dispatcher/AppDispatcher');
let EventEmitter = require('events').EventEmitter;
let ManageConstants = require('../constants/ManageConstants');
let assign = require('object-assign');
let Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);

let OrganizationsStore = assign({}, EventEmitter.prototype, {

    organizations : [],

    users : [],

    updateAll: function (organizations) {
        this.organizations = Immutable.fromJS(organizations);
    },


    addOrganization: function(organization) {
        this.organizations = this.organizations.concat(Immutable.fromJS([organization]));
    },

    updateOrganization: function (organization, workspace) {
        let index = this.organizations.indexOf(organization);
        let workspaces = organization.get('workspaces').push(workspace);
        this.organizations = this.organizations.setIn([index,'workspaces'], workspaces);
    },

    removeOrganization: function (organization) {
        let index = this.organizations.indexOf(organization);
        this.organizations = this.organizations.delete(index);
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {
    switch(action.actionType) {
        case ManageConstants.RENDER_ORGANIZATIONS:
            OrganizationsStore.updateAll(action.organizations);
            OrganizationsStore.emitChange(action.actionType, OrganizationsStore.organizations, Immutable.fromJS(action.defaultOrganization));
            break;
        case ManageConstants.REMOVE_ORGANIZATION:
            OrganizationsStore.removeOrganization(action.organization);
            OrganizationsStore.emitChange(ManageConstants.RENDER_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
        case ManageConstants.OPEN_CHANGE_ORGANIZATION_MODAL:
            ProjectsStore.emitChange(action.actionType, action.organization, action.projectId, OrganizationsStore.organizations);
            break;
        case ManageConstants.ADD_ORGANIZATION:
            OrganizationsStore.addOrganization(action.organization);
            OrganizationsStore.emitChange(ManageConstants.RENDER_ORGANIZATIONS, OrganizationsStore.organizations, Immutable.fromJS(action.organization));
            break;
        case ManageConstants.CREATE_WORKSPACE:
            OrganizationsStore.updateOrganization(action.organization, action.workspace);
            break;
    }
});
module.exports = OrganizationsStore;


