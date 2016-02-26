export default React.createClass({

    getInitialState: function() {
        return {

        }

    },
    componentDidMount: function() {
    },

    componentWillUnmount: function() {
    },

    render: function() {
        // When it's time to render we need to decide whether 
        // to show the error selection panel first, or the 
        // issues display panel. 
        //
        // It this the proper place to do that? 
        //
        // If we are rendering this, it means that either the 
        // icon has been clicked, or the error has been selected 
        // on the error selection area. 
        //
        // We don't have this information here 
        return <div className="review-issues-overview-panel"> 
            <strong>welcome to review panel</strong>
        </div>
        ;
    }
});
