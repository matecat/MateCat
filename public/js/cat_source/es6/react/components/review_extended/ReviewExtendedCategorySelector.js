
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
    onChangeSelect(){
        let severity = $(this.selectRef).data('value');
        if(severity){
            this.props.sendIssue(this.props.category, severity);
            $(this.selectRef).dropdown('clear');
        }
    }
    render() {
        // It may happen for a category to come with no severities. In this case
        // the category should be considered to be a header for the nested
        // subcategories. Don't print the select box if no severity is found.
        var select = null;

        if ( this.props.category.severities ) {
            var severities = this.props.category.severities.map(function(severity, i) {
                return <div onClick={this.onChangeSelect.bind(this)}
                            className="item"  key={'value-' + severity.label}
                            data-value={severity.label}>
                        {severity.label}
                    </div> ;
            }.bind(this));

            select = <div className="ui icon top right pointing dropdown button"
                ref={(input) => { this.selectRef = input;}}
                data-value={this.state.value}
                autoFocus={this.props.focus}
                name="severities"
                title="Select severities">
                <i className="icon-sort-down icon" />
                <div className="menu">
                    {severities}
                </div>

            </div>
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
