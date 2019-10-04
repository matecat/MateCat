import ReviewExtendedCategorySelector  from './ReviewExtendedCategorySelector';
class ReviewExtendedIssuePanel extends React.Component{

    constructor(props) {
        super( props );
        this.issueCategoriesFlat = JSON.parse( config.lqa_flat_categories );
        this.state = {
            submitDisabled: true,
            categorySelectedIndex: 0,
            categorySelectedId: this.issueCategoriesFlat[0].id,
            enableArrows: false,
            severityIndex:0
        };
        this.issueCategories = JSON.parse( config.lqa_nested_categories ).categories;

        this.handleShortcutsKeyDown = this.handleShortcutsKeyDown.bind(this);
        this.handleShortcutsKeyUp = this.handleShortcutsKeyUp.bind(this);
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
                    SegmentActions.setStatus(segment.sid, segment.id_file, segment.status);
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
		});

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
                    active={this.state.enableArrows && this.state.categorySelectedId === category.id}
                    severityActiveIndex={(this.state.enableArrows && this.state.categorySelectedId === category.id)? this.state.severityIndex : null}
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
                            active={this.state.enableArrows && this.state.categorySelectedId === category.id}
                            severityActiveIndex={(this.state.enableArrows && this.state.categorySelectedId === category.id)? this.state.severityIndex : null}
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
                        active={this.state.enableArrows && this.state.categorySelectedId === category.id}
                        severityActiveIndex={(this.state.enableArrows && this.state.categorySelectedId === category.id)? this.state.severityIndex : null}
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
    getNextCategoryIndex(direction) {
        let idx = this.state.categorySelectedIndex;
        let length = this.issueCategoriesFlat.length;
        switch (direction) {
            case 'next': return (idx + 1) % length;
            case 'prev': return (idx === 0) && length - 1 || idx - 1;
            default:     return idx;
        }
    }
    getNextSeverityIndex(direction) {
        let idx = this.state.severityIndex;
        let length = this.issueCategoriesFlat[this.state.categorySelectedIndex].severities.length;
        switch (direction) {
            case 'next': return (idx + 1) % length;
            case 'prev': return (idx === 0) && length - 1 || idx - 1;
            default:     return idx;
        }
    }
    handleShortcutsKeyDown(e) {
        if (e.ctrlKey && e.altKey && !this.state.enableArrows) {
            this.setState({
                enableArrows: true
            });
        }
        if ( this.state.enableArrows && e.code === "ArrowDown" ){
            let index = this.getNextCategoryIndex('next');
            this.setState({
                categorySelectedIndex: index,
                categorySelectedId: this.issueCategoriesFlat[index].id,
                severityIndex:0
            });
        } else if ( this.state.enableArrows && e.code === "ArrowUp" ){
            let index = this.getNextCategoryIndex('prev');
            this.setState({
                categorySelectedIndex: index,
                categorySelectedId: this.issueCategoriesFlat[index].id,
                severityIndex:0
            });
        } else if ( this.state.enableArrows && e.code === "ArrowLeft" ){
            let index = this.getNextSeverityIndex('prev');
            this.setState({
                severityIndex:index
            });
        } else if ( this.state.enableArrows && e.code === "ArrowRight" ){
            let index = this.getNextSeverityIndex('next');
            this.setState({
                severityIndex:index
            });
        }
        else if ( this.state.enableArrows && e.code === "Enter" ){
            this.sendIssue(this.issueCategoriesFlat[this.state.categorySelectedIndex], this.issueCategoriesFlat[this.state.categorySelectedIndex].severities[this.state.severityIndex]);
            setTimeout(()=>SegmentActions.setFocusOnEditArea(), 1000);
        }
    }

    handleShortcutsKeyUp(e) {
        if ( (!e.ctrlKey || !e.altKey) && this.state.enableArrows ) {
            e.preventDefault();
            e.stopPropagation();
            this.setState({
                enableArrows: false,
                categorySelectedIndex: 0,
                categorySelectedId: this.issueCategoriesFlat[0].id,
                severityIndex: 0
            });
        }
    }

    componentDidMount() {
        document.addEventListener('keydown', this.handleShortcutsKeyDown);
        document.addEventListener('keyup', this.handleShortcutsKeyUp);
    }

    componentWillUnmount() {
        document.removeEventListener('keyup', this.handleShortcutsKeyUp);
        document.removeEventListener('keydown', this.handleShortcutsKeyDown);
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