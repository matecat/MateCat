
var SegmentFixedButton = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick: function() {

        console.log(' fixed clicked');
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
