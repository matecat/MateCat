
class ReviewExtendedCategorySelector extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            value : this.props.selectedValue
        };

    }

    componentWillReceiveProps(nextProps) {
        this.setState({ value: nextProps.selectedValue });
    }

    componentDidMount(){
    	$(this.selectRef).dropdown();
	}

    render() {
        // It may happen for a category to come with no severities. In this case
        // the category should be considered to be a header for the nested
        // subcategories. Don't print the select box if no severity is found.
        var select = null;

        if ( this.props.category.severities ) {
            var default_severity = <option key={'value-'} value="" >---</option>;
            var severities = this.props.category.severities.map(function(severity, i) {
                return <option key={'value-' + severity.label} value={severity.label}>{severity.label}</option> ;
            }.bind(this));

            var full_severities = [default_severity].concat( severities );

            select = <select
                ref={(input) => { this.selectRef = input;}}
                value={this.state.value}
				className="ui dropdown"
                autoFocus={this.props.focus}
                onChange={this.props.severitySelected.bind(null, this.props.category)}
                name="severities">
                {full_severities}
            </select>
        }
		return <div className="error-item">
			<div className="error-name">{this.props.category.label}</div>
			<div className="error-level">
				{ select }
			</div>
		</div>;
    }
}

export default ReviewExtendedCategorySelector;
