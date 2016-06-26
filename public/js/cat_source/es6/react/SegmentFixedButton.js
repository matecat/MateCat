
var SegmentFixedButton = React.createClass({

    getInitialState: function() {
        return {
            disabled: this.props.disabled
        };
    },

    handleClick: function() {
        window.ReviewImproved.clickOnFixed(this.props.disabled, this.props.sid);
    },

    componentDidMount: function() {
    },

    componentWillUnmount: function() {
    },

    render: function() {
        if(this.state.disabled != this.props.disabled) {
            this.state.disabled = this.props.disabled;
        }

        var fixedButton = <li>
            <a className="button button-fixed status-fixed"
                onClick={this.handleClick}
                href="javascript:;"
                disabled={this.state.disabled} >
                FIXED
            </a>
            <p>{window.UI.shortcutLeader}+ENTER</p>
          </li>
          ;

        return fixedButton ;
    }
});

export default SegmentFixedButton ;
