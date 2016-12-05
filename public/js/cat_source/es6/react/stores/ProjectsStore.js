/*
 * TodoStore
 * Segment structure example
 * {
 "last_opened_segment":"61079",
 "sid":"60984",
 "segment":"INDIETRO",
 "segment_hash":"0a7e4ea10d93b636d9de15132300870c",
 "raw_word_count":"1.00",
 "internal_id":"P147242AB-tu19",
 "translation":"",
 "version":null,
 "original_target_provied":"0",
 "status":"NEW",
 "time_to_edit":"0",
 "xliff_ext_prec_tags":"",
 "xliff_ext_succ_tags":"",
 "warning":"0",
 "suggestion_match":"85",
 "source_chunk_lengths":[],
 "target_chunk_lengths":{
 "len":[0],
 "statuses":["DRAFT"]
 },
 "readonly":"false",
 "autopropagated_from":"0",
 "repetitions_in_chunk":"1",
 "has_reference":"false",
 "parsed_time_to_edit":["00","00","00","00"],
 "notes":null
 }
 */

var AppDispatcher = require('../dispatcher/AppDispatcher');
var EventEmitter = require('events').EventEmitter;
var ManageConstants = require('../constants/ManageConstants');
var assign = require('object-assign');
var Immutable = require('immutable');

EventEmitter.prototype.setMaxListeners(0);





var ProjectsStore = assign({}, EventEmitter.prototype, {

    projects : null,

    /**
     * Update all
     */
    updateAll: function (projects) {
        this.projects = Immutable.fromJS(projects);
    },
    /**
     * Add Projects (pagination)
     */
    addProjects: function(projects) {
        this.projects = this.projects.concat(Immutable.fromJS(projects));
    },
    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    },

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case ManageConstants.RENDER_PROJECTS:
            ProjectsStore.updateAll(action.project);
            ProjectsStore.emitChange(action.actionType, ProjectsStore.projects);
            break;
        case ManageConstants.RENDER_MORE_PROJECTS:
            ProjectsStore.addProjects(action.project);
            ProjectsStore.emitChange(ManageConstants.RENDER_PROJECTS, ProjectsStore.projects);
            break;

    }
});

module.exports = ProjectsStore;