import AppDispatcher from '../stores/AppDispatcher';
import CattolConstants from '../constants/CatToolConstants';
import Notifications from '../sse/sse';
import Review_QualityReportButton from '../components/review/QualityReportButton';
import SubHeaderContainer from '../components/header/cattol/SubHeaderContainer';
import SegmentFilter from "../components/header/cattol/segment_filter/segment_filter";

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
    reloadSegmentFilter: function ( segments, state ) {
        AppDispatcher.dispatch({
            actionType: CattolConstants.RELOAD_SEGMENT_FILTER
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
        $( '#mbc-history' ).removeClass( 'open' );
        AppDispatcher.dispatch({
            actionType: CattolConstants.CLOSE_SUBHEADER
        });
    },
    closeSearch: function (  ) {
        AppDispatcher.dispatch({
            actionType: CattolConstants.CLOSE_SEARCH
        });
    },
    startNotifications: function ( ) {
        Notifications.start();
    },
    renderQualityReportButton() {
        var revision_number = (config.revisionNumber) ? config.revisionNumber : '1';
        var qrParam = (config.secondRevisionsCount) ? '?revision_type=' + revision_number : '' ;
        window.quality_report_btn_component = ReactDOM.render(
            React.createElement( Review_QualityReportButton, {
                vote                : config.overall_quality_class,
                quality_report_href : config.quality_report_href + qrParam
            }), $('#quality-report-button')[0] );
    },
    renderSubHeader() {
        ReactDOM.render(
            React.createElement(
                SubHeaderContainer, {
                    filtersEnabled: SegmentFilter.enabled()
                }),
            $('#header-bars-wrapper')[0]
        );
    }
};

module.exports = CatToolActions;