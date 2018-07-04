var React = require('react');
var CatToolConstants = require('../../constants/CatToolConstants');
var CatToolStore = require('../../stores/CatToolStore');
var SegmentSelectionPanel = require('./bulk_selection_bar/BulkSelectionBar').default ;
var Search = require('./search/Search').default;
var QaComponent = require('./QAComponent').default;

class SubHeaderContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            selectionBar: true,
            search: false,
            segmentFilter: false,
            qaComponent: false
        };
        this.closeSubHeader = this.closeSubHeader.bind(this);
        this.toggleContainer = this.toggleContainer.bind(this);
        this.showContainer = this.showContainer.bind(this);
    }
    showContainer(container) {
        switch(container) {
            case 'search':
                this.setState({
                    search: true,
                    segmentFilter: false,
                    qaComponent: false
                });
                break;
            case 'segmentFilter':
                this.setState({
                    search: false,
                    segmentFilter: true,
                    qaComponent: false
                });
                break;
            case 'qaComponent':
                this.setState({
                    search: false,
                    segmentFilter: false,
                    qaComponent: true
                });
                break;
        }
    }
    toggleContainer(container) {
        switch(container) {
            case 'search':
                this.setState({
                    search: !this.state.search,
                    segmentFilter: false,
                    qaComponent: false
                });
                break;
            case 'segmentFilter':
                this.setState({
                    search: false,
                    segmentFilter: !this.state.segmentFilter,
                    qaComponent: false
                });
                break;
            case 'qaComponent':
                this.setState({
                    search: false,
                    segmentFilter: false,
                    qaComponent: !this.state.qaComponent
                });
                break;
        }
    }
    closeSubHeader() {
        this.setState({
            search: false,
            segmentFilter: false,
            qaComponent: false
        });
    }
    componentDidMount() {
        CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, this.showContainer);
        CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, this.toggleContainer);
        CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, this.closeSubHeader);
    }

    componentWillUnmount() {
        CatToolStore.removeListener(CatToolConstants.SHOW_CONTAINER, this.showContainer);
        CatToolStore.removeListener(CatToolConstants.TOGGLE_CONTAINER, this.toggleContainer);
        CatToolStore.removeListener(CatToolConstants.CLOSE_SUBHEADER, this.closeSubHeader);
    }

    render() {
        return <div>
            <Search
                active={this.state.search}
                isReview={config.isReview}
                searchable_statuses ={config.searchable_statuses}
            />
            { this.props.filtersEnabled ? (
                <SegmentFilter
                    active={this.state.segmentFilter}
                    isReview={config.isReview}
                />
            ) : (null) }
            <QaComponent
                active={this.state.qaComponent}
                isReview={config.isReview}
            />
            <SegmentSelectionPanel
                active={this.state.selectionBar}
                isReview={config.isReview}
            />
        </div>
    }
}

export default SubHeaderContainer;