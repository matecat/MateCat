
export default React.createClass({

    handleClick : function() {
        console.log('clicked'); 
    },

    render: function() {
        return (<div onClick={this.handleClick}><a href="javascript:void(0);">test</a></div>); 
    }
}); 
