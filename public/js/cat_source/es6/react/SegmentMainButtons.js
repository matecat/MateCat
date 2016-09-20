

var buttons = React.createClass({
    getInitialState: function() {
        return {
            status : this.props.status.toUpperCase(),
            anyRebuttedIssue : this.anyRebuttedIssue(),
            buttonDisabled : true,
        }
    },

    handleSegmentUpdate : function(data) {
        if ( this.props.sid == data.sid ) {
            this.setState( { status : data.status.toUpperCase() } );
        }
    },

    anyRebuttedIssue : function( data ) {
        var issuesRebutted = MateCat.db.segment_translation_issues.find( {
            '$and': [
                { id_segment: this.props.sid  },
                {
                    rebutted_at: {  '$ne': null }
                }
            ]
        } );

        return !!( issuesRebutted && issuesRebutted.length ) ;
    },

    updateButtonToShow : function( data ) {
        this.setState( { anyRebuttedIssue : this.anyRebuttedIssue() } );
    },

    componentDidMount: function() {
        MateCat.db.addListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
        MateCat.db.addListener('segment_translation_issues', ['insert', 'update', 'delete'], this.updateButtonToShow );

        var el = UI.Segment.findEl(this.props.sid);
        el.on( 'modified', this.segmentModifiedChanged ) ;
    },

    segmentModifiedChanged : function(event) {
        this.setState({ buttonDisabled : !this.isSegmentModified });
    },

    componentWillUnmount: function() {
        MateCat.db.removeListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
        MateCat.db.removeListener('segment_translation_issues', ['insert', 'update', 'delete'], this.updateButtonToShow );

        var el = UI.Segment.findEl(this.props.sid);
        el.off( 'modified', this.segmentModifiedChanged ) ;
    },

    render : function() {
        var disabledButton ;

        if ( this.state.anyRebuttedIssue ) {
            return  <div className="react-buttons">
                <MC.SegmentRebuttedButton status={this.state.status} sid={this.props.sid} />
            </div>
        } else {

            return <div className="react-buttons">
                <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} disabled={this.state.buttonDisabled}  />
            </div>
        }
    },

    isSegmentModified: function() {
        var el = UI.Segment.findEl(this.props.sid);
        var isModified = el.data('modified');
        return isModified === true ;
    }
});

export default buttons;
