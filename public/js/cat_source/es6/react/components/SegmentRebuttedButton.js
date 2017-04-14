
var button = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick : function() {
        window.ReviewImproved.clickOnRebutted(this.props.sid);
    },

    componentDidMount: function() {
    },

    componentWillUnmount: function() {
    },

    render: function() {
        var cmd = ((UI.isMac) ? 'CMD' : 'CTRL');

        return <li>
            <a className="button button-rebutted status-rebutted"
                onClick={this.handleClick}
                href="javascript:;"
                disabled={!this.state.disabled} >
                Rebutted
            </a>
            <p>{window.UI.shortcutLeader}+ENTER</p>
          </li>
          ;

    }
});

export default button ;
