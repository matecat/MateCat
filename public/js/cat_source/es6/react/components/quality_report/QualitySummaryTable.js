
class QualitySummaryTable extends React.Component {
    constructor (props) {
        super(props);
        this.lqaNestedCategories = JSON.parse(this.props.jobInfo.get('quality_summary').get('categories'));
        this.thereAreSubCategories = false;
        this.getTotalSeverities();
        this.qaLimit = JSON.parse(this.props.jobInfo.get('quality_summary').get('passfail')).options.limit;
        if (this.thereAreSubCategories) {
            this.htmlBody = this.getBodyWithSubtagories();
        } else {
            this.htmlBody = this.getBody();
        }
        this.htmlHead = this.getHeader();

    }
    getTotalSeverities() {
        this.severities = [];
        this.severitiesNames = [];
        this.lqaNestedCategories.categories.forEach((cat)=>{
            if (cat.subcategories.length === 0) {
                cat.severities.forEach((sev)=>{
                    if (this.severitiesNames.indexOf(sev.label) === -1 ) {
                        this.severities.push(sev);
                        this.severitiesNames.push(sev.label);
                    }
                });
            } else {
                this.thereAreSubCategories = true;
                cat.subcategories.forEach((subCat)=>{
                    subCat.severities.forEach((sev)=>{
                        if (this.severitiesNames.indexOf(sev.label) === -1 ) {
                            this.severities.push(sev);
                            this.severitiesNames.push(sev.label);
                        }
                    });
                });
            }
        });
    }
    getIssuesForCategory(categoryId) {
        if (this.props.jobInfo.get('quality_summary').size > 0 ) {
            return this.props.jobInfo.get('quality_summary').get('revise_issues').find((item, key)=>{
                return parseInt(key) === parseInt(categoryId);
            });
        }
    }
    getIssuesForCategoryWithSubcategory(category, sevLabel) {
        let total = 0;
        if (this.props.jobInfo.get('quality_summary').size > 0 ) {
            if ( category.subcategories.length > 0 ) {
                category.subcategories.forEach((sub)=>{
                    if ( !_.isUndefined(this.props.jobInfo.get('quality_summary').get('revise_issues').get(sub.id) ) &&
                        this.props.jobInfo.get('quality_summary').get('revise_issues').get(sub.id).get('founds').get(sevLabel)
                    ) {
                        total +=   this.props.jobInfo.get('quality_summary').get('revise_issues').get(sub.id).get('founds').get(sevLabel);
                    }
                });
            } else {
                if ( this.props.jobInfo.get('quality_summary').get('revise_issues').get(category.id) ) {
                    total = this.props.jobInfo.get('quality_summary').get('revise_issues').get(category.id).get('founds').get(sevLabel)
                }
            }
        }
        return total;
    }
    getCategorySeverities(categoryId) {
        let severities;
        this.lqaNestedCategories.categories.forEach((cat)=>{
            if ( categoryId === cat.id ) {
                severities = (cat.severities) ? cat.severities : cat.subcategories[0].severities;
            }
        });
        return severities;
    }
    getHeader() {
        let html = [];
        this.severities.forEach((sev, index)=>{
            let item = <div className="qr-title qr-severity" key={sev.label+index}>
                        <div className="qr-info">{sev.label}</div>
                        <div className="qr-label">Weight: <b>{sev.penalty}</b></div>
                    </div>;
            html.push(item);
        });
        let totalScore = Math.round(this.props.jobInfo.get('quality_summary').get('total_issues_weight'));
        return <div className="qr-head">
            <div className="qr-title qr-issue">Issues</div>
            {html}
            <div className="qr-title qr-total-severity">
                <div className="qr-info">Total error points</div>
                <div className="qr-info"><b>{totalScore}</b></div>
            </div>

            </div>
    }
    getBody() {
        let  html = [];
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = [];
            catHtml.push(
                <div className="qr-element qr-issue-name">{cat.label}</div>
            );
            let totalIssues = this.getIssuesForCategory(cat.id);
            let catTotalWeightValue = 0;
            this.severities.forEach((currentSev)=>{
                let severityFound = cat.severities.filter((sev)=>{
                    return sev.label === currentSev.label;
                });
                if (severityFound.length > 0 && !_.isUndefined(totalIssues) && totalIssues.get('founds').get(currentSev.label) ) {
                    catTotalWeightValue = catTotalWeightValue + (totalIssues.get('founds').get(currentSev.label) * severityFound[0].penalty);
                    catHtml.push(<div className="qr-element severity">{totalIssues.get('founds').get(currentSev.label)}</div>);
                } else {
                    catHtml.push(<div className="qr-element severity"/>);
                }
            });
            let catTotalWeightHtml = <div className="qr-element total-severity">{catTotalWeightValue}</div>;
            let line = <div className="qr-body-list" key={cat.id+index}>
                        {catHtml}
                        {catTotalWeightHtml}
                    </div>;
            html.push(line);
        });
        return <div className="qr-body">
        {html}
        </div>
    }
    getBodyWithSubtagories() {
        let  html = [];
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = [];
            catHtml.push(
                <div className="qr-element qr-issue-name">{cat.label}</div>
            );
            let catTotalWeightValue = 0;
            this.severities.forEach((currentSev)=>{
                let catSeverities = this.getCategorySeverities(cat.id);
                let severityFound = catSeverities.filter((sev)=>{
                    return sev.label === currentSev.label;
                });
                let totalIssues = this.getIssuesForCategoryWithSubcategory(cat, currentSev.label);
                if (severityFound.length > 0 && totalIssues > 0 ) {
                    catTotalWeightValue = catTotalWeightValue + (totalIssues * severityFound[0].penalty);
                    catHtml.push(<div className="qr-element severity">{totalIssues}</div>);
                } else {
                    catHtml.push(<div className="qr-element severity"/>);
                }
            });
            let catTotalWeightHtml = <div className="qr-element total-severity">{catTotalWeightValue}</div>;
            let line = <div className="qr-body-list" key={cat.id+index}>
                        {catHtml}
                        {catTotalWeightHtml}
                    </div>;
            html.push(line);
        });
        return <div className="qr-body">
        {html}
        </div>
    }
    render () {
        return <div className="qr-quality shadow-1">
                {this.htmlHead}
                {this.htmlBody}
        </div>
    }
}

export default QualitySummaryTable ;