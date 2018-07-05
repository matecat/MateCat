const AppDispatcher = require('../dispatcher/AppDispatcher');
const EventEmitter = require('events').EventEmitter;
const assign = require('object-assign');
const Immutable = require('immutable');
import QRConstants from "./../constants/QualityReportConstants";

EventEmitter.prototype.setMaxListeners(0);

let QualityReportStore = assign({}, EventEmitter.prototype, {
    _segmentsFiles: Immutable.fromJS({}),
    storeSegments: function(files) {
        this._segmentsFiles = Immutable.fromJS(files);
    },

    emitChange: function(event, args) {
        this.emit.apply(this, arguments);
    }

});


// Register callback to handle all updates
AppDispatcher.register(function(action) {

    switch(action.actionType) {
        case QRConstants.RENDER_SEGMENTS:
            QualityReportStore.storeSegments(action.files);
            QualityReportStore.emitChange(action.actionType, QualityReportStore._segmentsFiles);
            break;

    }
});

export default QualityReportStore ;