
var SegmentFixedButton = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick: function() {
        var el = UI.Segment.findEl(this.props.sid);
        UI.changeStatus(el, 'fixed', true);
        UI.gotoNextSegment(); // NOT ideal behaviour, would be better to have a callback chain of sort.
    },

    handleTranslationSuccess : function(e, data) {
        console.log('handleTranslationSuccess', data);
    },
    componentDidMount: function() {
    },

    componentWillUnmount: function() {
    },

    render: function() {
        var cmd = ((UI.isMac) ? 'CMD' : 'CTRL');

        var fixedButton = <li>
            <a className="button status-fixed"
                onClick={this.handleClick}
                href="javascript:;"
                disabled={!this.state.disabled} >
                FIXED
            </a>
          </li>
          ;

        return fixedButton ;
    }
});

export default SegmentFixedButton ;
