
var button = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick : function() {
        var el = UI.Segment.findEl(this.props.sid);
        UI.changeStatus(el, 'rebutted', true);
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
