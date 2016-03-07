
export default React.createClass({

    getInitialState : function() {
        return {
            value : this.props.selectedValue
        };
    },

    componentWillReceiveProps : function(nextProps) {
        this.setState({ value: nextProps.selectedValue });
    },

    componentDidMount: function() {
        var row = ReactDOM.findDOMNode( this );
        var slider = $(row).find('.issue.-slider').slider();
    },
    render : function() {
        var default_severity = <option key={'value-'} value="" >---</option>;
        var severities = this.props.category.severities.map(function(severity, i) {
            return <option key={'value-' + severity.label} value={severity.label}>{severity.label}</option> ;
        }.bind(this)); 

        var full_severities = [default_severity].concat( severities );

        return <tr>
        <td>{this.props.category.label}</td>
        <td>
        <select
            value={this.state.value}
            onChange={this.props.severitySelected.bind(null, this.props.category)}
            name="severities">

        {full_severities}
        </select>
        </td>
        </tr> ; 
    }
}); 
