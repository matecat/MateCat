

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
        MateCat.db.addListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
    },

    componentWillUnmount: function() {
        MateCat.db.removeListener('segments', ['insert', 'update'], this.handleSegmentUpdate );
    },

    render : function() {
        var disabledFixedButton = <div className="react-buttons">
            <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} disabled={true} />
        </div>

        var fixedButton = <div className="react-buttons">
            <MC.SegmentFixedButton status={this.state.status} sid={this.props.sid} disabled={false} />
        </div>

        var rebuttedButton = <div className="react-buttons">
            <MC.SegmentRebuttedButton status={this.state.status} sid={this.props.sid} />
        </div>

        if ( this.state.status == 'REJECTED' ) {
            return disabledFixedButton ;
        }
        if ( this.state.status == 'FIXED' ) {
            return fixedButton ;
        }
        if ( this.state.status == 'REBUTTED' ) {
            return rebuttedButton ;
        }
    }

});

export default buttons;
