class ReviewSidePanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            visible: false,
            sid: null,
            selection : null
        };

    }

    openPanel(e, data) {
        this.setState({
            sid: absoluteId(data.sid),
            visible: true,
            selection : data.selection
        });
    }

    closePanel(e, data) {
        this.setState({visible: false});
    }

    closePanelClick(e, data) {
        this.props.closePanel();
    }

    componentDidMount() {
        $(document).on('review-panel:opened', this.openPanel.bind(this));
        $(document).on('review-panel:closed', this.closePanel.bind(this));

        $(window).on('segmentOpened', this.segmentOpened.bind(this));

    }

    componentWillUnmount() {
        $(document).off('review-panel:opened', this.openPanel);
        $(document).off('review-panel:closed', this.closePanel);

        $(window).off('segmentOpened', this.segmentOpened);
    }

    segmentOpened(event) {
        this.setState({sid: event.segment.absId, selection: null});
    }

    submitIssueCallback() {
        this.setState({ selection : null });
    }

    render() {
        let innerPanel;
        let classes = classnames({
            'hidden' : !this.state.visible
        });

        if ( this.state.visible && this.state.selection != null ) {
            innerPanel = <div className="review-side-inner1">
                <ReviewIssueSelectionPanel submitIssueCallback={this.submitIssueCallback.bind(this)}
                                           selection={this.state.selection} sid={this.state.sid} />
            </div>
        }
        else if ( this.state.visible ) {
            innerPanel = <div className="review-side-inner1">
                <TranslationIssuesOverviewPanel sid={this.state.sid} />
            </div>;
        }

        return <div className={classes} id="review-side-panel">
            <div className="review-side-panel-close" onClick={this.closePanelClick.bind(this)}>x</div>
            {innerPanel}
        </div>;
    }
}

export default ReviewSidePanel ;
