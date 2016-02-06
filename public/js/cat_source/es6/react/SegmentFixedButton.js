
var SegmentFixedButton = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick: function() {
        console.log(' fixed clicked');
        var el = UI.Segment.findEl(this.props.sid);
        UI.changeStatus(el, 'fixed', true);
    },

    handleTranslationSuccess : function(e, data) {
        console.log('handleTranslationSuccess', data);
    },
    componentDidMount: function() {
        $(document).on('setTranslation:success', this.handleTranslationSuccess);
    },

    componentWillUnmount: function() {
        $(document).off('setTranslation:success', this.handleTranslationSuccess);
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
