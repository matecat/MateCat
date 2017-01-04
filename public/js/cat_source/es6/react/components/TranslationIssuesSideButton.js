
export default React.createClass({
    readDatabaseAndReturnState : function() {
        var segment = MateCat.db.segments.by('sid', this.props.sid); 
        var issues = MateCat.db.segment_translation_issues
            .findObjects({ 
                id_segment :  '' + this.props.sid, 
                translation_version : segment.version_number 
            });

        return {
            issues_on_latest_version : issues 
        };
    },

    setStateReadingFromDatabase: function() {
        this.setState( this.readDatabaseAndReturnState() );
    },

    componentDidMount: function() {
        MateCat.db.addListener('segments', ['update'], this.setStateReadingFromDatabase );
        MateCat.db.addListener('segment_translation_issues', 
                                  ['insert', 'update', 'delete'], 
                                  this.setStateReadingFromDatabase );

    },

    componentWillUnmount: function() {
        MateCat.db.removeListener('segments', ['update'], this.setStateReadingFromDatabase );
        MateCat.db.removeListener('segment_translation_issues', 
                                  ['insert', 'update', 'delete'], 
                                  this.setStateReadingFromDatabase ); 
    },

    getInitialState : function() {
        return this.readDatabaseAndReturnState(); 
    }, 
    handleClick : function(e) {
        ReviewImproved.openPanel({sid: this.props.sid});
    },

    render: function() {
        var count = this.state.issues_on_latest_version.length ; 
        var plus = config.isReview ? <span className="revise-button-counter">+</span> : null;
        if ( count > 0 ) {
            return (<div onClick={this.handleClick}><div className="review-triangle"></div><a className="revise-button has-object" href="javascript:void(0);"><span className="icon-error_outline" /><span className="revise-button-counter">{count}</span></a></div>); 
        } else  {
            return (<div onClick={this.handleClick}><div className="review-triangle"></div><a className="revise-button" href="javascript:void(0);"><span className="icon-error_outline" />{plus}</a></div>);
        }

    }
}); 
