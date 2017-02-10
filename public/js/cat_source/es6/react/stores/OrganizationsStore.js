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

    updateOrganization: function (organization) {
        let organizationOld = this.organizations.find(function (org) {
            return org.get('id') == organization.id;
        });
        let index = this.organizations.indexOf(organizationOld);
        this.organizations = this.organizations.setIn([index], Immutable.fromJS(organization));
        return this.organizations.get(index);
    },

    updateOrganizationName: function (organization) {
        let organizationOld = this.organizations.find(function (org) {
            return org.get('id') == organization.id;
        });
        let index = this.organizations.indexOf(organizationOld);
        this.organizations = this.organizations.setIn([index, 'name'], organization.name);
    },

    updateOrganizationWorkspace: function (organization, workspace) {
        let index = this.organizations.indexOf(Immutable.fromJS(organization));
        let workspaces = organization.get('workspaces').push(Immutable.fromJS(workspace));
        this.organizations = this.organizations.setIn([index,'workspaces'], workspaces);
        return this.organizations.get(index);
    },

    updateOrganizationMembers: function (organization, members) {
        let index = this.organizations.indexOf(Immutable.fromJS(organization));
        this.organizations = this.organizations.setIn([index,'members'], Immutable.fromJS(members));
        return this.organizations.get(index);
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
            OrganizationsStore.emitChange(action.actionType, OrganizationsStore.organizations);
            break;
        case ManageConstants.UPDATE_ORGANIZATION_NAME:
            OrganizationsStore.updateOrganizationName(action.organization);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
        case ManageConstants.UPDATE_ORGANIZATION_MEMBERS:
            let org = OrganizationsStore.updateOrganizationMembers(action.organization, action.members);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATION, org);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
        case ManageConstants.UPDATE_ORGANIZATION:
            let updated = OrganizationsStore.updateOrganization(action.organization);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATION, updated);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
        case ManageConstants.CHOOSE_ORGANIZATION:
            OrganizationsStore.emitChange(action.actionType, action.organizationId);
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
            OrganizationsStore.emitChange(ManageConstants.RENDER_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
        case ManageConstants.CREATE_WORKSPACE:
            let updated_ws =OrganizationsStore.updateOrganizationWorkspace(Immutable.fromJS(action.organization), Immutable.fromJS(action.workspace));
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATION, updated_ws);
            OrganizationsStore.emitChange(ManageConstants.UPDATE_ORGANIZATIONS, OrganizationsStore.organizations);
            break;
    }
});
module.exports = OrganizationsStore;


