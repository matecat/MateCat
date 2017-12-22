let ReviewExtendedCategorySelector = require('./ReviewExtendedCategorySelector').default;
class ReviewExtendedIssuePanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            submitDisabled : true
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
        let deferred = $.Deferred();
        let self = this;

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

        if(this.props.isDiffChanged){
        	let segment = this.props.segment;
        	segment.translation = this.props.newtranslation;
			API.SEGMENT.setTranslation(segment)
				.done(function(response){
					issue.version = response.translation.version_
					deferred.resolve();
				})
				.fail( self.handleFail.bind(self) ) ;
		}else{
        	deferred.resolve();
		}

		data.push(issue);

		deferred.then(function () {
			SegmentActions.submitIssue(self.props.sid, data, self.props.diffPatch)
				.done( self.props.submitIssueCallback )
				.fail( self.handleFail.bind(self) ) ;
		})

    }

    handleFail() {
        genericErrorAlertMessage() ;
        this.props.handleFail();
        this.setState({ submitDone : false, submitDisabled : false });
    }

    toggleList(){
		$(this.listElm).slideToggle('fast');
	}
    render() {
        let categoryComponents = [];
		let dropDownIcon = "icon-sort-up icon";
		if(this.state.listIsOpen){
			dropDownIcon = "icon-sort-down icon";
		}

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
					<h4 className="create-issue-title" onClick={this.toggleList.bind(this)}>
						Error list <i className={dropDownIcon}/>
					</h4>
					{/*<div className="issues-scroll">
						<a href="issues-created">Issues Created (<span className="issues-number">2</span>)</a>
					</div>*/}
					<div className="error-list active" ref={(node)=>this.listElm=node}>
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