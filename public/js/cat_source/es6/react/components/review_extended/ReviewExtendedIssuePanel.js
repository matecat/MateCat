let ReviewExtendedCategorySelector = require('./ReviewExtendedCategorySelector').default;
class ReviewExtendedIssuePanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            submitDisabled : true
        };
        this.issueCategories = JSON.parse(config.lqa_nested_categories).categories;
    }

    sendIssue(category, severity) {
        this.props.setCreationIssueLoader(true);
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

        let segment = this.props.segment;
        if ( segment.status.toLowerCase() !== 'approved' || segment.revision_number !== ReviewExtended.number ) {
            segment.status = 'approved';
            API.SEGMENT.setTranslation( segment )
                .done( function ( response ) {
                    issue.version = response.translation.version_number;
                    SegmentActions.addClassToSegment(segment.sid , 'modified');
                    deferred.resolve();
                } )
                .fail( self.handleFail.bind( self ) );

        } else {
            deferred.resolve();
        }
		data.push(issue);

		deferred.then(function () {
            SegmentActions.setStatus(segment.sid, segment.fid, segment.status);
            UI.currentSegment.data('modified', false);
			SegmentActions.submitIssue(self.props.sid, data)
				.done(function ( data ) {
                    self.props.submitIssueCallback();
                    setTimeout(function (  ) {
                        SegmentActions.issueAdded(self.props.sid, data.issue.id);
                    });
                } )
				.fail( (response) => self.handleFail(response.responseJSON) ) ;
		})

    }

    handleFail(response) {
        if ( response.errors && response.errors[0].code === -2000 ) {
            UI.processErrors(response.errors, 'createIssue');
        } else {
            genericErrorAlertMessage() ;
        }
        this.props.setCreationIssueLoader(false);
        this.props.handleFail();
        this.setState({ submitDone : false, submitDisabled : false });
    }

    thereAreSubcategories() {
        return this.issueCategories[0].subcategories && this.issueCategories[0].subcategories.length > 0 ||
            this.issueCategories[1].subcategories && this.issueCategories[1].subcategories.length > 0;
    }

    getCategoriesHtml() {

        let categoryComponents = [];
        this.issueCategories.forEach(function(category, i) {
            let selectedValue = "";

            categoryComponents.push(
                <ReviewExtendedCategorySelector
                    key={'category-selector-' + i}
                    sendIssue={this.sendIssue.bind(this)}
                    selectedValue={selectedValue}
                    nested={false}
                    category={category}
                    sid={this.props.sid}
                />);
        }.bind(this));

        return <div>
                    <div className="re-item-head pad-left-10">Type of issue</div>
                    {categoryComponents}
                </div>;
    }

    getSubCategoriesHtml() {
        let categoryComponents = [];
        this.issueCategories.forEach(function(category, i) {
            let selectedValue = "";
            let subcategoriesComponents = [];

            if ( category.subcategories.length > 0 ) {
                category.subcategories.forEach( (category, ii) => {
                    let key = '' + i + '-' + ii;
                    let kk = 'category-selector-' + key ;
                    let selectedValue = "";

                    subcategoriesComponents.push(
                        <ReviewExtendedCategorySelector
                            key={kk}
                            selectedValue={selectedValue}
                            sendIssue={this.sendIssue.bind(this)}
                            nested={true}
                            category={category}
                            sid={this.props.sid}
                        />
                    );
                } );
            } else {
                subcategoriesComponents.push(
                    <ReviewExtendedCategorySelector
                        key={'default'}
                        selectedValue={selectedValue}
                        sendIssue={this.sendIssue.bind(this)}
                        nested={true}
                        category={category}
                        sid={this.props.sid}
                    />
                );
            }
            let html = <div key={category.id}>
                <div className="re-item-head pad-left-10">{category.label}</div>
                {subcategoriesComponents}
            </div>;
            categoryComponents.push(html);
        }.bind(this));

        return categoryComponents;
    }

    render() {
        let html = [];

        if (this.thereAreSubcategories()) {
            html = this.getSubCategoriesHtml();
        } else {
            html = this.getCategoriesHtml()
        }

        return<div className="re-issues-box re-to-create" >
            {/*<h4 className="re-issues-box-title">Error list</h4>*/}
            {/*<div className="mbc-triangle mbc-triangle-topleft"></div>*/}
            <div className="re-list errors" id={"re-category-list-" + this.props.sid}  ref={(node)=>this.listElm=node}>
                {html}
            </div>
        </div>
    }
}

ReviewExtendedIssuePanel.defaultProps = {
    handleFail: function () {},
};

export default ReviewExtendedIssuePanel ;