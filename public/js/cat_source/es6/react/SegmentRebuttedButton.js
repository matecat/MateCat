
var button = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick : function() {
        var el = UI.Segment.findEl(this.props.sid);
        el.removeClass('modified');
        UI.changeStatus(el, 'rebutted', true);
        UI.gotoNextSegment();
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

        return <li>
            <a className="button status-rebutted"
                onClick={this.handleClick}
                href="javascript:;"
                disabled={!this.state.disabled} >
                Rebutted
            </a>
          </li>
          ;

    }
});

export default button ;
