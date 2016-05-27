
var SegmentFixedButton = React.createClass({

    getInitialState: function() {
        return {
            disabled: this.props.disabled
        };
    },

    handleClick: function() {
        if ( this.props.disabled )
            return;

        var el = UI.Segment.findEl(this.props.sid);
        el.removeClass('modified');
        el.data('modified', false);
        UI.changeStatus(el, 'fixed', true);
        UI.gotoNextSegment(); // NOT ideal behaviour, would be better to have a callback chain of sort.
    },

    handleTranslationSuccess : function(e, data) {
        console.log('handleTranslationSuccess', data);
    },
    componentDidMount: function() {
        window.segment_fixed_button = this;
    },

    componentWillUnmount: function() {
        window.segment_fixed_button = null;
    },

    render: function() {
        var cmd = ((UI.isMac) ? 'CMD' : 'CTRL');

        var fixedButton = <li>
            <a className="button status-fixed"
                onClick={this.handleClick}
                href="javascript:;"
                disabled={this.state.disabled} >
                FIXED
            </a>
          </li>
          ;

        return fixedButton ;
    },

    enable: function() {
        this.setState( { disabled: false } );
    }
});

export default SegmentFixedButton ;
