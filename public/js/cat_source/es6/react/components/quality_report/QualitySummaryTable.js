
class QualitySummaryTable extends React.Component {
    constructor (props) {
        super(props);
        this.lqaNestedCategories = JSON.parse(config.categories);
        this.getTotalSeverities();
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
            let item = <th className="two wide center aligned qr-title qr-severity major" key={sev.label+index}>
                        <div className="qr-info">{sev.label}</div>
                        <div className="qr-label">Weight: <b>{sev.penalty}</b></div>
                    </th>;
            html.push(item);
        });

        return <tr>
            <th className="eight wide qr-title qr-issue">Issues</th>
            {html}
            <th className="wide center aligned qr-title">Total weight</th>
        </tr>
    }
    getBody() {
        let html = [];
        this.lqaNestedCategories.categories.forEach((cat, index)=>{
            let catHtml = []
            catHtml.push(
                <td><b>{cat.label}</b></td>
            );
            let totalIssues = this.getIssuesForCategory(cat.id);
            // if (cat.subcategories.length === 0) {
                this.severities.forEach((currentSev)=>{
                    let severityFound = cat.severities.filter((sev)=>{
                        return sev.label === currentSev.label;
                    });
                    if (severityFound.length > 0 && !_.isUndefined(totalIssues) && totalIssues.get('founds').get(currentSev.label) ) {
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

            let totalWeight = <td className="right aligned">0</td>;
            catHtml.push(totalWeight);
            let line = <tr key={cat.id+index}>
                        {catHtml}
                    </tr>;
            html.push(line);
        });
        return <tbody>
        {html}
        </tbody>
    }
    render () {

        return <div className="qr-quality">

            <table className="ui celled table shadow-1">
                <thead>
                {this.getHeader()}
                </thead>
                {this.getBody()}
                {/*<tbody>*/}
                {/*<tr>*/}
                    {/*<td><b>Tag issues</b></td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*/!*<td className="center aligned"></td>*!/*/}
                    {/*<td className="right aligned">0</td>*/}
                    {/*/!*<td className="right aligned">1.8</td>*!/*/}
                    {/*/!*<td className="positive center aligned">Excellent</td>*!/*/}
                {/*</tr>*/}
                {/*<tr>*/}
                    {/*<td><b>Translation errors</b></td>*/}
                    {/*<td className="center aligned"> </td>*/}
                    {/*<td className="center aligned">1</td>*/}
                    {/*/!*<td className="center aligned"> </td>*!/*/}
                    {/*<td className="right aligned">1</td>*/}
                    {/*/!*<td className="right aligned">1.8</td>*!/*/}
                    {/*/!*<td className="warning center aligned">Poor</td>*!/*/}
                {/*</tr>*/}
                {/*<tr>*/}
                    {/*<td><b>Terminology and translation consistency</b></td>*/}
                    {/*<td className="center aligned">1</td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*/!*<td className="center aligned"></td>*!/*/}
                    {/*<td className="right aligned">3</td>*/}
                    {/*/!*<td className="right aligned">0.9</td>*!/*/}
                    {/*/!*<td className="negative center aligned">Fail</td>*!/*/}
                {/*</tr>*/}
                {/*<tr>*/}
                    {/*<td><b>Language quality</b></td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*/!*<td className="center aligned"></td>*!/*/}
                    {/*<td className="right aligned">0</td>*/}
                    {/*/!*<td className="right aligned">2.6</td>*!/*/}
                    {/*/!*<td className="positive center aligned">Eccelent</td>*!/*/}
                {/*</tr>*/}
                {/*<tr>*/}
                    {/*<td><b>Style</b></td>*/}
                    {/*<td className="center aligned"></td>*/}
                    {/*<td className="center aligned">1</td>*/}
                    {/*/!*<td className="center aligned">1</td>*!/*/}
                    {/*<td className="right aligned">1.03</td>*/}
                    {/*/!*<td className="right aligned">6.1</td>*!/*/}
                    {/*/!*<td className="positive center aligned">Very good</td>*!/*/}
                {/*</tr>*/}
                {/*</tbody>*/}
            </table>

        </div>
    }
}

export default QualitySummaryTable ;