

var buttons = React.createClass({

    getInitialState: function() {
        return {
            status : this.props.status.toUpperCase()
        }
    },

    handleTranslationSuccess : function(e, data) {

        console.debug( 'handleTranslationSuccess', data ) ;
        if ( this.props.sid == data.sid ) {
            this.setState( { status : data.status.toUpperCase() } );
        }
    },
    componentDidMount: function() {
        $(document).on('segment:change', this.handleTranslationSuccess);
    },

    componentWillUnmount: function() {
        console.debug('componentWillUnmount',
                      ReactDOM.findDOMNode( this ));

        $(document).off('segment:change', this.handleTranslationSuccess);
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
        return null;
    }

});

export default buttons;
