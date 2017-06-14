
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class JobTableHeader extends React.Component {

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
        return <div className="ui grid job-table-header">
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
        </div>;


    }
}

export default JobTableHeader;
