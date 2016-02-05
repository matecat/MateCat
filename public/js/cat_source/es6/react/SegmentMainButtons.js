

var buttons = React.createClass({

    render : function() {

        return <div>
            <MC.SegmentFixedButton status={this.props.status} sid={this.props.sid} />
            &nbsp;
            <MC.SegmentRebuttedButton status={this.props.status} sid={this.props.sid} />
        </div>
    }

});

export default buttons;
