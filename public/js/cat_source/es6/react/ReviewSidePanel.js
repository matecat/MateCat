export default React.createClass({
    getInitialState: function() {
        return {
            visible: false,
        }
    },

    openPanel : function(e, data) {
        this.setState({
            sid: absoluteId(data.sid),
            visible: true,
            selection : data.selection
        });
    }, 

    closePanel : function(e, data) {
        this.setState({visible: false}); 
    },

    closePanelClick : function(e, data) {
        this.props.closePanel();
    },

    componentDidMount: function() {
        $(document).on('review-panel:opened', this.openPanel);
        $(document).on('review-panel:closed', this.closePanel);

        $(window).on('segmentOpened', this.segmentOpened);

    },

    componentWillUnmount: function() {
        $(document).off('review-panel:opened', this.openPanel);
        $(document).off('review-panel:closed', this.closePanel);

        $(window).off('segmentOpened', this.segmentOpened);
    },

    segmentOpened : function(event) {
        this.setState({sid: event.segment.absId, selection: null});
    },

    submitIssueCallback : function() {
        console.log( 'submitIssueCallback' ); 

        this.setState({ selection : null }); 
    },
    render: function() {
        var innerPanel; 
        var classes = classnames({
            'hidden' : !this.state.visible 
        }); 

        if ( this.state.visible && this.state.selection != null ) {
            innerPanel = <div className="review-side-inner1">
                <ReviewIssueSelectionPanel submitIssueCallback={this.submitIssueCallback} 
                selection={this.state.selection} sid={this.state.sid} />
            </div>
        }
        else if ( this.state.visible ) {
            innerPanel = <div className="review-side-inner1">
                <TranslationIssuesOverviewPanel sid={this.state.sid} />
            </div>;
        } 

        return <div className={classes} id="review-side-panel">
            <div className="review-side-panel-close" onClick={this.closePanelClick}>x</div>
            {innerPanel}
        </div>;
    }
});
