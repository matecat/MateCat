

var buttons = React.createClass({
    getInitialState: function() {
        return {
            status : this.props.status.toUpperCase()
        }
    },

    handleSegmentUpdate : function(data) {
        if ( this.props.sid == data.sid ) {
            this.setState( { status : data.status.toUpperCase() } );
        }
    },
    componentDidMount: function() {
        MateCat.db.segments.on('update', this.handleSegmentUpdate );
    },

    componentWillUnmount: function() {
        MateCat.db.segments.removeListener('update', this.handleSegmentUpdate );
    },

    render : function() {
        var bothButtons = <div className="react-buttons">
            <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} />
            &nbsp;
            <MC.SegmentRebuttedButton status={this.state.status} sid={this.props.sid} />
        </div>

        var fixedButton = <div className="react-buttons">
            <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} />
        </div>

        var rebuttedButton = <div className="react-buttons">
            <MC.SegmentRebuttedButton status={this.state.status} sid={this.props.sid} />
        </div>

        if ( this.state.status == 'REJECTED' ) {
            return bothButtons ;
        }
        if ( this.state.status == 'FIXED' ) {
            return rebuttedButton ;
        }
        if ( this.state.status == 'REBUTTED' ) {
            return fixedButton ;
        }
    }

});

export default buttons;
