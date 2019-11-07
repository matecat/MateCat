
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
        let select = null;
        let severities;
        let classCatName = this.props.category.options.code;
        let containerClass = (this.props.category.severities > 0) ? "" : "severity-buttons" ;
        if ( this.props.category.severities.length > 3 ) {
            severities = this.props.category.severities.map((severity, i) =>{
                return <div onClick={this.onChangeSelect.bind(this)}
                            className="item"  key={'value-' + severity.label}
                            data-value={severity.label}>
                        <b>{severity.label}</b>
                    </div> ;
            });

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

            severities = this.props.category.severities.map((severity, i) =>{
                let buttonClass = (i === 0 && this.props.category.severities.length > 1) ?  'left' :
                    ( i === this.props.category.severities.length - 1 || this.props.category.severities.length === 1) ? 'right' : '';
                let label = (this.props.category.severities.length === 1) ? severity.label : severity.label.substring(0,3);
                return <button key={'value-' + severity.label}
                               onClick={this.onClick.bind(this, severity.label)}
                               className={"ui " + buttonClass + " attached button"}
                               title={severity.label}>
                    {label}
                </button>;
            });

            select = <div className="re-severities-buttons ui tiny buttons" ref={(input) => { this.selectRef = input;}}
                          title="Select severities">
                {severities}
            </div>;

        }
        return <div className={"re-item re-category-item " + containerClass + " " + classCatName}>
            <div className="re-item-box re-error">
                <div className="error-name">
                    {/*{this.props.category.options && this.props.category.options.code ? (*/}
                        {/*<div className="re-abb-issue">{this.props.category.options.code}</div>*/}
                    {/*) : (null)}*/}
                    {this.props.category.label}</div>
                <div className="error-level">
                    { select }
                </div>
            </div>
		</div>;
    }
}

export default ReviewExtendedCategorySelector;
