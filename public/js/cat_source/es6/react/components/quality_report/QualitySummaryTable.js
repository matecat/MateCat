
class QualitySummaryTable extends React.Component {
    constructor (props) {
        super(props);
        this.lqaNestedCategories = JSON.parse(config.categories);
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
                return parseInt(key) === parseInt(categoryId);
            });
        }
    }
    getHeader() {
        let html = [];
        this.severities.forEach((sev, index)=>{
            let item = <th className="two wide center aligned qr-title qr-severity" key={sev.label+index}>
                        <div className="qr-info">{sev.label}</div>
                        <div className="qr-label">Weight: <b>{sev.penalty}</b></div>
                    </th>;
            html.push(item);
        });

        return <tr>
            <th className="eight wide qr-title qr-issue">Issues</th>
            {html}
            <th className="wide center aligned qr-title">
                <div className="qr-info">Job Passed/Not Passed</div>
                <div className="qr-label">Total Weight: <b>{this.totalWeight}/soglia?</b></div>
            </th>
        </tr>
    }
    getBody() {
        let html = [];
        this.totalWeight = 0;
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = []
            catHtml.push(
                <td><b>{cat.label}</b></td>
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
                        catHtml.push(<td className="center aligned">{totalIssues.get('founds').get(currentSev.label)}</td>);
                    } else {
                        catHtml.push(<td className="center aligned"/>);
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

            let catTotalWeightHtml = <td className="right aligned">{catTotalWeightValue}</td>;
            let line = <tr key={cat.id+index}>
                        {catHtml}
                        {catTotalWeightHtml}
                    </tr>;
            html.push(line);
            this.totalWeight = this.totalWeight + catTotalWeightValue;
        });
        return <tbody>
        {html}
        </tbody>
    }
    render () {
        return <div className="qr-quality">
            <table className="ui celled table shadow-1">
                <thead>
                {this.htmlHead}
                </thead>
                {this.htmlBody}
            </table>

        </div>
    }
}

export default QualitySummaryTable ;