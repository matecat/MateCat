let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
let ReviewIssueSelectionPanel = require('./ReviewIssueSelectionPanel').default;
let TranslationIssuesOverviewPanel = require('./TranslationIssuesOverviewPanel').default;
class ReviewSidePanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            visible: false,
            sid: null,
            selection : null,
            loader: true
        };

    }

    openPanel(data) {
        this.setState({
            visible: true,
            selection : data.selection
        });
    }

    closePanel(data) {
        this.setState({
            visible: false,
            selection : null
        });
    }

    closePanelClick(e, data) {
        this.props.closePanel();
    }

    setLoader(boolean){
        this.setState({
            loader: boolean
        })
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.OPEN_ISSUES_PANEL, this.openPanel.bind(this));
        SegmentStore.addListener(SegmentConstants.CLOSE_ISSUES_PANEL, this.closePanel.bind(this));
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.segmentOpened.bind(this));

        // $(window).on('segmentOpened', this.segmentOpened.bind(this));

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.OPEN_ISSUES_PANEL, this.openPanel);
        SegmentStore.removeListener(SegmentConstants.CLOSE_ISSUES_PANEL, this.closePanel);
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_VERSIONS_ISSUES, this.segmentOpened);

        // $(window).off('segmentOpened', this.segmentOpened);
    }

    segmentOpened(sid, segment) {
        this.setState({
            sid: sid,
            selection: null,
            segment: segment
        });
    }

    submitIssueCallback() {
        this.setState({ selection : null });
    }

    render() {
        let innerPanel = '';
        let classes = classnames({
            'hidden' : !this.state.visible,
            'review-improved-panel': true
        });
        let idContainer = classnames({
            'review-side-panel' : true,
        });
        if (this.state.visible && this.state.selection != null) {
            innerPanel = <div className="review-side-inner1">
                <ReviewIssueSelectionPanel submitIssueCallback={this.submitIssueCallback.bind(this)}
                                           selection={this.state.selection} sid={this.state.sid}/>
            </div>
        } else if (this.state.visible) {
            innerPanel = <div className="review-side-inner1">
                <TranslationIssuesOverviewPanel
                    sid={this.state.sid}
                    reviewType={this.props.reviewType}
                    closePanel={this.props.closePanel}
                />
            </div>;
        }

        return <div className={classes} id="review-side-panel">
            {innerPanel}
        </div>;
    }
}

export default ReviewSidePanel ;
