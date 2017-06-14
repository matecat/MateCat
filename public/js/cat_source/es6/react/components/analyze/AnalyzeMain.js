
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeHeader = require('./AnalyzeHeader').default;

class AnalyzeMain extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidUpdate() {
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    render() {
        return <div className="ui container">
                <div className="project ui grid shadow-1">
                    <div className="sixteen wide column">
                        <div className="analyze-header">
                            <AnalyzeHeader/>
                        </div>

                        <div className="project-body ui grid">
                            <div className="jobs sixteen wide column">
                                <div className="job ui grid">
                                    <div className="job-body sixteen wide column">

                                        <div className="ui grid chunks">
                                            <div className="chunk-container sixteen wide column">
                                                <div className="ui grid analysis">
                                                    <div className="head-chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="source-target">
                                                            <div className="source-box">English</div>
                                                            <div className="in-to">
                                                                <i className="icon-chevron-right icon"></i>
                                                            </div>
                                                            <div className="target-box">Haitian Creole French</div>
                                                        </div>
                                                        <div className="job-not-payable">
                                                            <span id="raw-words">7'845,783</span>
                                                        </div>
                                                        <div className="job-payable">
                                                            <a href="#">
                                                                <span id="words">5'485,384</span> words
                                                            </a>
                                                        </div>
                                                        <div className="merge ui button"><i className="icon-compress icon"></i> Merge</div>
                                                        {/*<!--<div className="split ui button"><i className="icon-expand icon"></i> Split</div>-->*/}
                                                    </div>

                                                    <div className="chunks-title-table sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">

                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">Total</div>
                                                            <div className="single payable-words">Payable</div>
                                                            <div className="single new">New</div>
                                                            <div className="single repetition">Repetition</div>
                                                            <div className="single internal-matches">Internal Matches <span>(75/99)%</span></div>
                                                            <div className="single p-50-74">TM Partial <span>(50/74)%</span></div>
                                                            <div className="single p-75-84">TM Partial <span>(75/84)%</span></div>
                                                            <div className="single p-65-94">TM Partial <span>(85/94)%</span></div>
                                                            <div className="single p-95-99">TM Partial <span>(95/99)%</span></div>
                                                            <div className="single tm-100">TM <span>100%</span></div>
                                                            <div className="single tm-public">Public TM 100%</div>
                                                            <div className="single tm-context">TM 100% in context</div>
                                                            <div className="single machine-translation">Machine Translation</div>
                                                        </div>

                                                    </div>

                                                    <div className="chunks-pay-table sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">

                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">Pay</div>
                                                            <div className="single payable-words"> > </div>
                                                            <div className="single new">100%</div>
                                                            <div className="single repetition">30%</div>
                                                            <div className="single internal-matches">60%</div>
                                                            <div className="single p-50-74">100%</div>
                                                            <div className="single p-75-84">60%</div>
                                                            <div className="single p-65-94">60%</div>
                                                            <div className="single p-95-99">60%</div>
                                                            <div className="single tm-100">30%</div>
                                                            <div className="single tm-public">30%</div>
                                                            <div className="single tm-context">0%</div>
                                                            <div className="single machine-translation">80%</div>
                                                        </div>
                                                    </div>

                                                    <div className="chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <div className="job-id">(780777-1)</div>
                                                            <div className="file-details">
                                                                <a href="#">
                                                                    File <span className="details">details</span>
                                                                </a>
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                        <div className="right-box">
                                                            <div className="open-translate ui primary button open">Translate</div>
                                                        </div>
                                                    </div>
                                                    <div className="chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <div className="job-id">(780777-1)</div>
                                                            <div className="file-details">
                                                                <a href="#">
                                                                    File <span className="details">details</span>
                                                                </a>
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">654</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">245</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                        <div className="right-box">
                                                            <div className="open-translate ui primary button open">Translate</div>
                                                        </div>
                                                    </div>
                                                    <div className="chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <div className="job-id">(780777-1)</div>
                                                            <div className="file-details">
                                                                <a href="#">
                                                                    File <span className="details">details</span>
                                                                </a>
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">656</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">625</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                        <div className="right-box">
                                                            <div className="open-translate ui primary button open">Translate</div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                                <div className="job ui grid">
                                    <div className="job-body sixteen wide column">

                                        <div className="ui grid chunks">
                                            <div className="chunk-container sixteen wide column">
                                                <div className="ui grid analysis">
                                                    <div className="head-chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="source-target">
                                                            <div className="source-box">English</div>
                                                            <div className="in-to">
                                                                <i className="icon-chevron-right icon"></i>
                                                            </div>
                                                            <div className="target-box">Haitian Creole French</div>
                                                        </div>
                                                        <div className="job-not-payable">
                                                            <span id="raw-words">7'845,783</span>
                                                        </div>
                                                        <div className="job-payable">
                                                            <a href="#">
                                                                <span id="words">5'485,384</span> words
                                                            </a>
                                                        </div>
                                                        <div className="merge ui button"><i className="icon-compress icon"></i> Merge</div>
                                                        {/*<!--<div className="split ui button"><i className="icon-expand icon"></i> Split</div>-->*/}
                                                    </div>

                                                    <div className="chunks-title-table sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">

                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">Total</div>
                                                            <div className="single payable-words">Payable</div>
                                                            <div className="single new">New</div>
                                                            <div className="single repetition">Repetition</div>
                                                            <div className="single internal-matches">Internal Matches <span>(75/99)%</span></div>
                                                            <div className="single p-50-74">TM Partial <span>(50/74)%</span></div>
                                                            <div className="single p-75-84">TM Partial <span>(75/84)%</span></div>
                                                            <div className="single p-65-94">TM Partial <span>(85/94)%</span></div>
                                                            <div className="single p-95-99">TM Partial <span>(95/99)%</span></div>
                                                            <div className="single tm-100">TM <span>100%</span></div>
                                                            <div className="single tm-public">Public TM 100%</div>
                                                            <div className="single tm-context">TM 100% in context</div>
                                                            <div className="single machine-translation">Machine Translation</div>
                                                        </div>

                                                    </div>

                                                    <div className="chunks-pay-table sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">

                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">Pay</div>
                                                            <div className="single payable-words"> > </div>
                                                            <div className="single new">100%</div>
                                                            <div className="single repetition">30%</div>
                                                            <div className="single internal-matches">60%</div>
                                                            <div className="single p-50-74">100%</div>
                                                            <div className="single p-75-84">60%</div>
                                                            <div className="single p-65-94">60%</div>
                                                            <div className="single p-95-99">60%</div>
                                                            <div className="single tm-100">30%</div>
                                                            <div className="single tm-public">30%</div>
                                                            <div className="single tm-context">0%</div>
                                                            <div className="single machine-translation">80%</div>
                                                        </div>
                                                    </div>

                                                    <div className="chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <div className="job-id">(780777-1)</div>
                                                            <div className="file-details">
                                                                <a href="#">
                                                                    File <span className="details">details</span>
                                                                </a>
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                        <div className="right-box">
                                                            <div className="open-translate ui primary button open">Translate</div>
                                                        </div>
                                                    </div>
                                                    <div className="chunk-detail sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <i className="icon-make-group icon"></i>
                                                            <div className="file-title-details">
                                                                Job Title detail
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                    </div>
                                                    <div className="chunk-detail sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <i className="icon-make-group icon"></i>
                                                            <div className="file-title-details">
                                                                Job Title detail
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                    </div>

                                                    <div className="chunk sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <div className="job-id">(780777-1)</div>
                                                            <div className="file-details">
                                                                <a href="#">
                                                                    File <span className="details">details</span>
                                                                </a>
                                                                (<span className="f-details-number">1</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                        <div className="right-box">
                                                            <div className="open-translate ui primary button open">Translate</div>
                                                        </div>
                                                    </div>
                                                    <div className="chunk-detail sixteen wide column shadow-1 pad-right-10">
                                                        <div className="left-box">
                                                            <i className="icon-make-group icon"></i>
                                                            <div className="file-title-details">
                                                                Job Title detail
                                                                (<span className="f-details-number">2</span>)
                                                            </div>
                                                        </div>
                                                        <div className="single-analysis">
                                                            <div className="single total">5'434,234</div>
                                                            <div className="single payable-words">235,234</div>
                                                            <div className="single new">456</div>
                                                            <div className="single repetition">256,342</div>
                                                            <div className="single internal-matches">356</div>
                                                            <div className="single p-50-74">5,0403</div>
                                                            <div className="single p-75-84">7,942</div>
                                                            <div className="single p-65-94">6,942</div>
                                                            <div className="single p-95-99">3,942</div>
                                                            <div className="single tm-100">645</div>
                                                            <div className="single tm-public">8,435</div>
                                                            <div className="single tm-context">4,524</div>
                                                            <div className="single machine-translation">6'754,648</div>
                                                        </div>
                                                    </div>


                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>

                        <div className="project-footer ui grid">

                        </div>
                    </div>
                </div>
            </div>;


    }
}

export default AnalyzeMain;
