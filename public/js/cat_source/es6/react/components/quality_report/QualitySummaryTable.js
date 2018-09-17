
class QualitySummaryTable extends React.Component {
    constructor (props) {
        super(props);
        this.lqaFlatCategories = JSON.parse('[{"id":"3304","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"id_model":"325","id_parent":null,"label":"Accuracy (Addition,' +
            ' Omission,' +
        ' Mistranslation, Untranslated,' +
        ' Inconsistency)","options":{"code":"ACC"}},{"id":"3305","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"id_model":"325","id_parent":null,"label":"Client' +
            ' guidelines (Terminology, Style Guide, Project instructions)","options":{"code":"GUI"}},{"id":"3306","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"id_model":"325","id_parent":null,"label":"Linguistic (Punctuation, Spelling, Grammar, Locale conventions)","options":{"code":"LNG"}},{"id":"3307","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"id_model":"325","id_parent":null,"label":"Style (Inconsistent style, Readability, Unidiomatic)","options":{"code":"STY"}}]');

        this.lqaNestedCategories = JSON.parse('{"categories":[{"label":"Accuracy (Addition, Omission, Mistranslation, Untranslated,' +
            ' Inconsistency)","id":"3304","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"options":{"code":"ACC"},"subcategories":[]},{"label":"Client guidelines' +
            ' (Terminology, Style Guide, Project instructions)","id":"3305","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"options":{"code":"GUI"},"subcategories":[]},{"label":"Linguistic (Punctuation, Spelling, Grammar, Locale conventions)","id":"3306","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"options":{"code":"LNG"},"subcategories":[]},{"label":"Style (Inconsistent style, Readability, Unidiomatic)","id":"3307","severities":[{"label":"Minor","penalty":1},{"label":"Major","penalty":2}],"options":{"code":"STY"},"subcategories":[]}]}');
        // this.lqaNestedCategories = JSON.parse('{"categories":[{"label":"Accuracy","id":"3187","severities":null,"options":null,"subcategories":[{"label":"Addition","id":"3188","options":null,"severities":[{"label":"pippo","penalty":0},{"label":"franco","penalty":1},{"label":"cicco","penalty":2},{"label":"checco","penalty":3}]},{"label":"Omission","id":"3189","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]},{"label":"Mistranslation","id":"3190","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]},{"label":"Untranslated","id":"3191","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]}]},{"label":"Style","id":"3192","severities":null,"options":null,"subcategories":[{"label":"Company style","id":"3193","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]}]},{"label":"Fluency","id":"3194","severities":null,"options":null,"subcategories":[{"label":"Grammar","id":"3195","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]},{"label":"Punctuation","id":"3196","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]},{"label":"Spelling","id":"3197","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]}]},{"label":"Terminology","id":"3198","severities":null,"options":null,"subcategories":[{"label":"Inconsistent with Termbase","id":"3199","options":null,"severities":[{"label":"Neutral","penalty":0},{"label":"Minor","penalty":1},{"label":"Major","penalty":2},{"label":"Critical","penalty":3}]}]}]}');
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
            }
        });
    }
    getHeader() {
        let html = [];
        this.severities.forEach((sev)=>{
            let item = <th className="one wide center aligned qr-title qr-severity critical">
                        <div className="qr-info">{sev.label}</div>
                        <div className="qr-label">Weight: <b>{sev.penalty}</b></div>
                    </th>;
            html.push(item);
        });

        return <tr>
            <th className="four wide qr-title qr-issue">Issues</th>
            {html}
            <th className="wide center aligned qr-title">Total weight</th>
        </tr>
    }
    getBody() {
        let html = [];
        this.lqaNestedCategories.categories.forEach((cat)=>{
            let catHtml = []
            catHtml.push(
                <td><b>{cat.label}</b></td>
            );
            this.severities.forEach((currentSev)=>{
                let severityFound = cat.severities.filter((sev)=>{
                    return sev.label === currentSev.label;
                });
                if (severityFound.length > 0) {
                    catHtml.push(<td className="center aligned">Value {currentSev.label}</td>);
                } else {
                    catHtml.push(<td className="center aligned">NotFound</td>);
                }
            });
            let totalWeight = <td className="right aligned">0</td>;
            catHtml.push(totalWeight);
            let line = <tr key={cat.id}>
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