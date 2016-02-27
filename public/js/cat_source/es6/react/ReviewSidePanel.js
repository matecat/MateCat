
export default React.createClass({

    getInitialState: function() {
        return {
            visible: false, 
        }
    },

    openPanel : function(e, data) {
        console.log( data );

        this.setState({sid: data.sid, visible: true}); 
    }, 

    closePanel : function(e, data) {
        this.setState({visible: false}); 
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
        console.log( event );
        this.setState({sid: event.segment.id}); 
    },
    render: function() {

        var innerPanel; 
        var classes = classnames({
            'hidden' : !this.state.visible 
        }); 

        if ( this.state.visible ) {
            innerPanel = <div className="review-side-inner1">
                <TranslationIssuesOverviewPanel sid={this.state.sid} />
            </div>;
        } 

        return <div className={classes} id="review-side-panel">
            {innerPanel}
        </div>;
    }
});
