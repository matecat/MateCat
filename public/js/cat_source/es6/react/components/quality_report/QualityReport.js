import Header from "./Header";
import JobSummary from "./JobSummary";
import SegmentsDetails from "./SegmentsDetails";
import ReactDom from "react-dom";

class QualityReport extends React.Component {

    componentDidMount() {

    }


    render () {

        return <div>
            {/*<Header/>
            <JobSummary/>
            <SegmentsDetails/>*/}
            <div className="qr-container">
                <div className="qr-container-inside">

                    <div className="qr-job-summary-container">
                        <div className="qr-job-summary">
                            <h3>Job Summary</h3>
                            <div className="qr-production-quality">
                                <div className="qr-production">
                                    <div className="id-job">ID: 123458-8</div>
                                    <div className="source-to-target">
                                        <div className="qr-source"><b>Haitian Creole French</b></div>
                                        <div className="qr-to"> >
                                            <i className=""/>
                                        </div>
                                        <div className="qr-target"><b>Haitian Creole French</b></div>
                                    </div>
                                    <div className="progress-percent">
                                        <div className="progress-bar">
                                            <div className="progr">
                                                <div className="meter">
                                                    <a className="warning-bar translate-tooltip" data-variation="tiny" data-html="Rejected 0%" />
                                                    <a className="approved-bar translate-tooltip" data-variation="tiny" data-html="Approved 3%" />
                                                    <a className="translated-bar translate-tooltip" data-variation="tiny" data-html="Translated 88%" />
                                                    <a className="draft-bar translate-tooltip" data-variation="tiny" data-html="Draft 9%" />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="percent">100%</div>
                                    </div>
                                    <div className="qr-effort">
                                        <div className="qr-label">Words</div>
                                        <div className="qr-info"><b>124,234</b></div>
                                    </div>
                                    <div className="qr-effort translator">
                                        <div className="qr-label">Translator</div>
                                        <div className="qr-info"><b>Silvia Corri</b></div>
                                    </div>
                                    <div className="qr-effort reviser">
                                        <div className="qr-label">Reviser</div>
                                        <div className="qr-info"><b>Naomi Lomartire</b></div>
                                    </div>
                                    <div className="qr-effort pee">
                                        <div className="qr-label">PEE</div>
                                        <div className="qr-info"><b>30%</b> <i className="icon-notice icon" /></div>
                                    </div>
                                    <div className="qr-effort time-edit">
                                        <div className="qr-label">Time Edit</div>
                                        <div className="qr-info"><b>30%</b> <i className="icon-notice icon" /></div>
                                    </div>
                                </div>
                                <div className="qr-quality">

                                    <table className="ui celled table">
                                        <thead>
                                            <tr>
                                                <th className="four wide qr-issue">Issues</th>
                                                <th className="one wide center aligned qr-severity critical">
                                                    <div className="qr-info">Critical</div>
                                                    <div className="qr-label">Weight: <b>3</b></div>
                                                </th>
                                                <th className="one wide center aligned qr-severity major">
                                                    <div className="qr-info">Major</div>
                                                    <div className="qr-label">Weight: <b>1</b></div>
                                                </th>
                                                <th className="one wide center aligned qr-severity enhacement">
                                                    <div className="qr-info">Enhacement</div>
                                                    <div className="qr-label">Weight: <b>0.03</b></div>
                                                </th>
                                                <th className="wide">Total weight</th>
                                                <th className="wide">*Tolerated Issues</th>
                                                <th className="two wide center aligned">
                                                    <div className="qr-label">Total Score</div>
                                                    <div className="qr-info">Good</div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><b>Tag issues</b></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td>0</td>
                                                <td>1.8</td>
                                                <td className="positive center aligned">Excellent</td>
                                            </tr>
                                            <tr>
                                                <td>Translation errors</td>
                                                <td> </td>
                                                <td>1</td>
                                                <td> </td>
                                                <td>1</td>
                                                <td>1.8</td>
                                                <td className="warning center aligned">Poor</td>
                                            </tr>
                                            <tr>
                                                <td>Terminology and translation consistency</td>
                                                <td>1</td>
                                                <td></td>
                                                <td></td>
                                                <td>3</td>
                                                <td>0.9</td>
                                                <td className="negative center aligned">Fail</td>
                                            </tr>
                                            <tr>
                                                <td>Language quality</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td>0</td>
                                                <td>2.6</td>
                                                <td className="positive center aligned">Eccelent</td>
                                            </tr>
                                            <tr>
                                                <td>Style</td>
                                                <td></td>
                                                <td>1</td>
                                                <td>1</td>
                                                <td>1.03</td>
                                                <td>6.1</td>
                                                <td className="positive center aligned">Very good</td>
                                            </tr>
                                        </tbody>
                                        {/*<tfoot>
                                            <tr>
                                                <th>3 People</th>
                                                <th>2 Approved</th>
                                            </tr>
                                        </tfoot>*/}
                                    </table>

                                </div>
                            </div>
                            <div className="qr-segment-details-container">
                                <div className="qr-segments-summary">
                                    <h3>Job Summary</h3>
                                    <div className="">Filters by</div>
                                    <div className="qr-segments">
                                        <div className="document-name">FILE Test_Project_For_New_QR.html</div>
                                        <div className="qr-segments-list">
                                            <div className="qr-single-segment">

                                                <table className="ui celled table">

                                                    <thead>
                                                        <tr>
                                                            <th className="three wide">
                                                                <div className="segment id">1243134</div>
                                                            </th>
                                                            <th className="wide">
                                                                <div className="segment-production">
                                                                    <div className="word-speed">Words speed: <b>7"</b></div>
                                                                    <div className="time-edit">Time to edit: <b>53"</b></div>
                                                                    <div className="pee">PEE: <b>30%</b></div>
                                                                </div>
                                                            </th>
                                                            <th className="two wide">
                                                                <div className="qr-label">Segment status</div>
                                                                <div className="qr-info status-translated">Translated</div>
                                                            </th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>
                                                        <tr>
                                                            <td>Source</td>
                                                            <td><b>Tag issues</b></td>
                                                            <td>Words: <b>10</b></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Suggestion</td>
                                                            <td>Translation errors</td>
                                                            <td>Public TM <span>97%</span></td>

                                                        </tr>
                                                        <tr>
                                                            <td>Translate</td>
                                                            <td>Terminology and translation consistency</td>
                                                            <td>ICE Match (Modified) </td>
                                                        </tr>
                                                        <tr>
                                                            <td>Revise</td>
                                                            <td>Terminology and translation consistency</td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Automated QA</td>
                                                            <td>
                                                                <div className="qr-issues-list">
                                                                    <div className="qr-issue automated">
                                                                        <i className="icon-3dglasses icon" />
                                                                        <div className="qr-error">Tag mismatch <b>(2)</b></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Human QA</td>
                                                            <td>
                                                                <div className="qr-issues-list">
                                                                    <div className="qr-issue human">
                                                                        <div className="qr-error"><b>Language quality</b></div>
                                                                        <div className="sub-type-error">Subtype </div>
                                                                        <div className="severity">Critical</div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    </tbody>

                                                </table>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    }
}

export default QualityReport ;

ReactDom.render(React.createElement(QualityReport), document.getElementById('qr-root'));