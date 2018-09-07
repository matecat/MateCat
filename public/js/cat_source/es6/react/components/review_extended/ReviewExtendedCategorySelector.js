
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
    	$(this.selectRef).dropdown({
            // direction: "auto",
            keepOnScreen: true,
            context: document.getElementById("re-category-list-" + this.props.sid)
        });
	}
    onChangeSelect(){
        let severity = $(this.selectRef).data('value');
        if(severity){
            this.props.sendIssue(this.props.category, severity);
            $(this.selectRef).dropdown('clear');
        }
    }
    onClick(severity) {
        if(severity){
            this.props.sendIssue(this.props.category, severity);
        }
    }
    render() {
        // It may happen for a category to come with no severities. In this case
        // the category should be considered to be a header for the nested
        // subcategories. Don't print the select box if no severity is found.
        var select = null;
        var severities;
        if ( this.props.category.severities > 2 ) {
            severities = this.props.category.severities.map(function(severity, i) {
                return <div onClick={this.onChangeSelect.bind(this)}
                            className="item"  key={'value-' + severity.label}
                            data-value={severity.label}>
                        <b>{severity.label}</b>
                    </div> ;
            }.bind(this));

            select = <div className="ui icon top right pointing dropdown basic tiny button"
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
        } else {
            let button1 =  <button key={'value-' + this.props.category.severities[0].label}
                                   onClick={this.onClick.bind(this, this.props.category.severities[0].label)}
                                   className="ui left attached tiny button">{this.props.category.severities[0].label.substring(0,3)}
                                   </button>;
            let button2 =  <button key={'value-' + this.props.category.severities[1].label}
                                   onClick={this.onClick.bind(this, this.props.category.severities[1].label)}
                                   className="ui right attached tiny button">{this.props.category.severities[1].label.substring(0,3)}
                                   </button>;
            select = <div className="re-severities-buttons" ref={(input) => { this.selectRef = input;}}
                          name="severities"
                          title="Select severities">
                            {button1}
                            {button2}
                    </div>
        }
		return <div className="re-item re-category-item">
            <div className="re-item-box re-error">
                <div className="error-name">
                    {this.props.category.options && this.props.category.options.code ? (
                        <div className="re-abb-issue">{this.props.category.options.code}</div>
                    ) : (null)}
                    {this.props.category.label}</div>
                <div className="error-level">
                    { select }
                </div>
            </div>
		</div>;
    }
}

export default ReviewExtendedCategorySelector;
