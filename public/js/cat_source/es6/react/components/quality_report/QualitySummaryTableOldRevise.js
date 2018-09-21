
class QualitySummaryTableOldRevise extends React.Component {
    constructor (props) {
        super(props);
        this.lqaNestedCategories = JSON.parse(this.props.jobInfo.get('quality_summary').get('categories'));
        this.getTotalSeverities();
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
                return key === categoryId;
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
        let qualityVote = this.props.jobInfo.get('quality_summary').get('quality_overall');
        let passedClass =  (qualityVote === 'poor' || qualityVote === 'fail') ? 'job-not-passed' : "job-passed";
        return <div className="qr-head">
            <div className="qr-title qr-issue">Issues</div>
            {html}

            <div className="qr-title qr-total-severity qr-old">
                <div className="qr-info">Total Weight</div>
            </div>

            <div className="qr-title qr-total-severity qr-old">
                <div className="qr-info">Tolerated Issues</div>
            </div>

            <div className={"qr-title qr-total-severity qr-old " + passedClass}>
                <div className="qr-info">{this.props.jobInfo.get('quality_summary').get('quality_overall')}</div>
                <div className="qr-label">Total Score</div>
            </div>


        </div>
    }
    getBody() {
        let  html = [];
        this.totalWeight = 0;
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = [];
            catHtml.push(
                <div className="qr-element qr-issue-name">{cat.label}</div>
            );
            let totalIssues = this.getIssuesForCategory(cat.id);
            let catTotalWeightValue = 0, toleratedIssuesValue = 0, voteValue = "";
            this.severities.forEach((currentSev)=>{
                let severityFound = cat.severities.filter((sev)=>{
                    return sev.label === currentSev.label;
                });
                if (severityFound.length > 0 && !_.isUndefined(totalIssues) && totalIssues.get('founds').get(currentSev.label) ) {
                    catTotalWeightValue = catTotalWeightValue + (totalIssues.get('founds').get(currentSev.label) * severityFound[0].penalty);
                    toleratedIssuesValue = totalIssues.get('allowed');
                    voteValue = totalIssues.get('vote');
                    catHtml.push(<div className="qr-element severity">{totalIssues.get('founds').get(currentSev.label)}</div>);
                } else {
                    catHtml.push(<div className="qr-element severity"/>);
                }
            });
            let catTotalWeightHtml = <div className="qr-element total-severity qr-old">{catTotalWeightValue}</div>;
            let toleratedIssuesHtml = <div className="qr-element total-severity qr-old">{toleratedIssuesValue}</div>;
            let voteClass = (voteValue.toLowerCase() === 'poor' || voteValue.toLowerCase() === 'fail') ? 'job-not-passed' : "job-passed";
            let voteHtml = <div className={"qr-element total-severity qr-old " + voteClass}>{voteValue}</div>;
            let line = <div className="qr-body-list" key={cat.id+index+cat.label}>
                {catHtml}
                {catTotalWeightHtml}
                {toleratedIssuesHtml}
                {voteHtml}
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

export default QualitySummaryTableOldRevise ;