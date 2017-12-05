let ReviewExtendedCategorySelector = require('./ReviewExtendedCategorySelector').default;
class ReviewExtendedIssuePanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            submitDisabled : true,
        };

    }

    issueCategories() {
        return JSON.parse(config.lqa_nested_categories).categories ;
    }

    severitySelected( category, event ) {
        let severity = $(ReactDOM.findDOMNode( event.target )).val() ;
        this.sendIssue(category, severity);
    }

    sendIssue(category, severity) {

        let data = [];

        let issue = {
            'id_category'         : category.id,
            'severity'            : severity,
            'version'             : this.props.segmentVersion,
        };

        if (this.props.selection) {
            issue.target_text = this.props.selection.selected_string;
            issue.start_node = this.props.selection.start_node;
            issue.start_offset = this.props.selection.start_offset;
            issue.send_node = this.props.selection.end_node;
            issue.end_offset = this.props.selection.end_offset;
        } else {
            issue.start_node = 0;
            issue.start_offset = 0;
            issue.send_node = 0;
            issue.end_offset = 0;
        }

        data.push(issue);

        SegmentActions.submitIssue(this.props.sid, data, this.props.diffPatch)
            .done( this.props.submitIssueCallback )
            .fail( this.handleFail.bind(this) ) ;
    }

    handleFail() {
        genericErrorAlertMessage() ;
        this.props.handleFail();
        this.setState({ submitDone : false, submitDisabled : false });
    }

    render() {
        let categoryComponents = [];

        this.issueCategories().forEach(function(category, i) {
            let selectedValue = "";

            categoryComponents.push(
                <ReviewExtendedCategorySelector
                    key={'category-selector-' + i}
                    severitySelected={this.severitySelected.bind(this)}
                    selectedValue={selectedValue}
                    nested={false}
                    category={category} />);

            if ( category.subcategories.length > 0 ) {
                category.subcategories.forEach( function(category, ii) {
                    let key = '' + i + '-' + ii;
                    let kk = 'category-selector-' + key ;
                    let selectedValue = "";

                    categoryComponents.push(
                        <ReviewExtendedCategorySelector
                            key={kk}
                            selectedValue={selectedValue}
                            severitySelected={this.severitySelected.bind(this)}
                            nested={true}
                            category={category}  />
                    );
                }.bind(this) );
            }
        }.bind(this));


            return<div className="re-create-issue">
				<div className="ui accordion">
					<h4 className="title active">
						Error list <i className="dropdown icon"/>
					</h4>
					{/*<div className="issues-scroll">
						<a href="issues-created">Issues Created (<span className="issues-number">2</span>)</a>
					</div>*/}
					<div className="error-list active">
						{categoryComponents}
					</div>
				</div>
			</div>
    }
}

ReviewExtendedIssuePanel.defaultProps = {
    handleFail: function () {},
};

export default ReviewExtendedIssuePanel ;