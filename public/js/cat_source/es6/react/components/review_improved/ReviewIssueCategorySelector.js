
class ReviewIssueCategorySelector extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            value : this.props.selectedValue
        };

    }

    componentWillReceiveProps(nextProps) {
        this.setState({ value: nextProps.selectedValue });
    }

    render() {
        // It may happen for a category to come with no severities. In this case
        // the category should be considered to be a header for the nested
        // subcategories. Don't print the select box if no severity is found.
        let select = null;
        let classname = classnames({
            "category-title" : !this.props.category.severities
        });
        if ( this.props.category.severities ) {
            var default_severity = <option key={'value-'} value="" >---</option>;
            var severities = this.props.category.severities.map(function(severity, i) {
                return <option key={'value-' + severity.label} value={severity.label}>{severity.label}</option> ;
            }.bind(this));

            var full_severities = [default_severity].concat( severities );

            select = <select
                ref="select"
                value={this.state.value}
                autoFocus={this.props.focus}
                onChange={this.props.severitySelected.bind(null, this.props.category)}
                name="severities">
            {full_severities}
            </select>
        } else {

        }

        return <tr>
        <td className={classname}>{this.props.category.label}</td>
        <td>
            { select }
        </td>
        </tr> ; 
    }
}

export default ReviewIssueCategorySelector;
