let ReviewExtendedIssue =  require("./ReviewExtendedIssue").default;
let WrapperLoader =         require("../../common/WrapperLoader").default;
let SegmentConstants = require('../../constants/SegmentConstants');
class ReviewExtendedIssuesContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            lastIssueAdded: null,
            visible: true
        };
        this.issueFlatCategories = JSON.parse(config.lqa_flat_categories);
        this.issueNestedCategories = JSON.parse(config.lqa_nested_categories).categories;

    }

    parseIssues() {
        let issuesObj = {}
        this.props.issues.forEach( issue => {
            let cat = this.findCategory(issue.id_category);
            let id = (this.isSubCategory(cat)) ? cat.id_parent: cat.id;

            if (!issuesObj[id]) {
                issuesObj[id] = [];
            }
            issuesObj[id].push(issue);
        });
        return issuesObj;
    }

    findCategory( id ) {
        return this.issueFlatCategories.find( category => {
            return id == category.id
        } )
    }

    isSubCategory( category ) {
        return !_.isNull(category.id_parent);
    }

    thereAreSubcategories() {
        return this.issueNestedCategories[0].subcategories && this.issueNestedCategories[0].subcategories.length > 0;
    }

    getSubCategoriesHtml() {
        let parsedIssues = this.parseIssues();
        let html = [];
        _.each(parsedIssues, (issuesList, id) =>  {
            let cat = this.findCategory(id);
            let issues = this.getIssuesSortedComponentList(issuesList);
            let catHtml = <div key={cat.id}>
                <div className="re-item-head pad-left-5">{cat.label}</div>
                {issues}
            </div>;
            html.push(catHtml)
        });

        return html;
    }

    getCategoriesHtml() {
        let issues;

        if (this.props.issues.length > 0 ) {
            issues = this.getIssuesSortedComponentList(this.props.issues)
        }
        return <div>
                    <div className="re-item-head pad-left-1">Issues found</div>
                    {issues}
                </div>;
    }

    getIssuesSortedComponentList(list) {
        let issues;
        let sorted_issues = list.sort(function(a, b) {
            a = new Date(a.created_at);
            b = new Date(b.created_at);
            return a>b ? -1 : a<b ? 1 : 0;
        });

        issues = sorted_issues.map(function( item, index ) {
                return <ReviewExtendedIssue
                    lastIssueId={this.state.lastIssueAdded}
                    sid={this.props.segment.sid}
                    isReview={this.props.isReview}
                    issue={item}
                    key={item.id}
                    changeVisibility={this.changeVisibility.bind(this)}
                />
        }.bind(this) );

        return issues;
    }

    changeVisibility(id, visible) {
        let issues = this.props.issues.slice();
        let index = _.findIndex(issues, function ( item ) {
            return item.id == id;
        });
        issues[index].visible = visible;

        let visibleIssues = _.filter(this.props.issues, function ( item ) {
            return _.isUndefined(item.visible) || item.visible;
        });
        if (visibleIssues.length === 0) {
            this.setState({
                visible: false
            });
        } else {
            this.setState({
                visible: true
            });
        }
    }

    setLastIssueAdded(sid, id) {
        if ( sid === this.props.segment.sid ) {
            setTimeout((  ) => {
                SegmentActions.openIssueComments(this.props.segment.sid, id);
            }, 200);

        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.ISSUE_ADDED, this.setLastIssueAdded.bind(this));

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.ISSUE_ADDED, this.setLastIssueAdded);
        //Undo notification
        APP.removeAllNotifications();
    }

    componentDidUpdate(prevProps, prevState) {
        if ( prevProps.issues.length < this.props.issues.length ) {
            this.setState({
                visible: true
            });
        }
    }

    render () {

        if(this.props.issues.length > 0){

            let html;
            if (this.thereAreSubcategories()) {
                html = this.getSubCategoriesHtml();
            } else {
                html = this.getCategoriesHtml()
            }
            let classNotVisible = (!this.state.visible) ? 're-issues-box-empty' : ''
            return <div className={"re-issues-box re-created " + classNotVisible}>
                    {this.props.loader ? <WrapperLoader /> : null}
                    <div className="re-list issues">
                        {html}
                    </div>
            </div>;
        }
        return "";

    }
}

export default ReviewExtendedIssuesContainer;
