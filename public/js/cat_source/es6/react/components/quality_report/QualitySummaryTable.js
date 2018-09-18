
class QualitySummaryTable extends React.Component {
    constructor (props) {
        super(props);
        this.lqaNestedCategories = JSON.parse(config.categories);
        this.getTotalSeverities();
        this.qaLimit = config.qa_limit;
        this.htmlBody = this.getBody();
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
    getHeader() {
        let html = [];
        this.severities.forEach((sev, index)=>{
            let item = <div className="qr-title qr-severity" key={sev.label+index}>
                        <div className="qr-info">{sev.label}</div>
                        <div className="qr-label">Weight: <b>{sev.penalty}</b></div>
                    </div>;
            html.push(item);
        });

        return <div className="qr-head">
            <div className="qr-title qr-issue">Issues</div>
            {html}
            { parseInt(this.totalWeight) > parseInt(this.qaLimit) ? (
                <div className="qr-title qr-total-severity">
                    <div className="qr-info">Job Not Passed</div>
                    <div className="qr-label">Total Weight: <b>{this.totalWeight}/{this.qaLimit}</b></div>
                </div>
            ) : (
                <div className="qr-title qr-total-severity">
                    <div className="qr-info">Job Passed</div>
                    <div className="qr-label">Total Weight: <b>{this.totalWeight}/{this.qaLimit}</b></div>
                </div>
            ) }

        </tr>
    }
    getBody() {
        let  html = [];
        this.totalWeight = 0;
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = []
            catHtml.push(
                <div className="qr-element qr-issue-name">{cat.label}</div>
            );
            let totalIssues = this.getIssuesForCategory(cat.id);
            // if (cat.subcategories.length === 0) {
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
            // } else {
            //     cat.subcategories.forEach((subCat)=>{
            //         this.severities.forEach((currentSev)=>{
            //             let severityFound = subCat.severities.filter((sev)=>{
            //                 return sev.label === currentSev.label;
            //             });
            //             if (severityFound.length > 0) {
            //                 catHtml.push(<td className="center aligned">Value {currentSev.label}</td>);
            //             } else {
            //                 catHtml.push(<td className="center aligned">NotFound</td>);
            //             }
            //         });
            //     });
            // }

            let catTotalWeightHtml = <div className="qr-element total-severity">{catTotalWeightValue}</div>;
            let line = <div className="qr-body-list" key={cat.id+index}>
                        {catHtml}
                        {catTotalWeightHtml}
                    </div>;
            html.push(line);
            this.totalWeight = this.totalWeight + catTotalWeightValue;
        });
        return <div className="qr-body">
        {html}
        </div>
    }
    render () {
        return <div className="qr-quality">
                {this.htmlHead}
                {this.htmlBody}
        </div>
    }
}

export default QualitySummaryTable ;