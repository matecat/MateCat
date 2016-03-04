
export default React.createClass({

    render : function() {

        var severities = this.props.category.severities.map(function(severity, i) {
            var name = 'severity'; 
            var key = name + '-' + i; 
            return <input key={key} type="radio" name={name} title={severity.label} 
                onClick={this.props.severitySelected.bind(null, this.props.category, severity)}/>; 
        }.bind(this)); 

        return <tr>
        <td>{this.props.category.label}</td>
        <td>
        {severities}
        </td>
        </tr> ; 
    }
}); 
