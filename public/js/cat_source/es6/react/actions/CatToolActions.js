let AppDispatcher = require('../dispatcher/AppDispatcher');
let CattolConstants = require('../constants/CatToolConstants');


let CatToolActions = {

    openQaIssues: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.SHOW_CONTAINER,
            container: "qaComponent"
        });
    },
    openSearch: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.SHOW_CONTAINER,
            container: "search"
        });
    },
    openSegmentFilter: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.SHOW_CONTAINER,
            container: "segmentFilter"
        });
    },
    setSegmentFilter: function ( segments, state ) {
        AppDispatcher.dispatch({
            actionType: CattolConstants.SET_SEGMENT_FILTER,
            data: segments,
            state: state
        });
    },
    toggleQaIssues: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.TOGGLE_CONTAINER,
            container: "qaComponent"
        });
    },
    toggleSearch: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.TOGGLE_CONTAINER,
            container: "search"
        });
    },
    setSearchResults: function ( data ) {
        AppDispatcher.dispatch({
            actionType: CattolConstants.SET_SEARCH_RESULTS,
            total: data.total,
            segments: data.segments
        });
    },
    toggleSegmentFilter: function () {
        AppDispatcher.dispatch({
            actionType: CattolConstants.TOGGLE_CONTAINER,
            container: "segmentFilter"
        });
    },
    closeSubHeader: function (  ) {
        $('.mbc-history-balloon-outer').removeClass('mbc-visible');
        AppDispatcher.dispatch({
            actionType: CattolConstants.CLOSE_SUBHEADER
        });
    }
};

module.exports = CatToolActions;