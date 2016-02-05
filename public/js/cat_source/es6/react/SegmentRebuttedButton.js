
var button = React.createClass({

    getInitialState: function() {
        return {
            disabled: false,
        };
    },

    handleClick : function() {
        console.log('rebutted clicked');
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
