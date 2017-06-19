
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class ChunkAnalyzeHeader extends React.Component {

    constructor(props) {
        super(props);
        this.payableChange = false;
    }

    checkWhatChanged() {
        if (this.total) {
            this.payableChange = (this.total.get('TOTAL_PAYABLE') !== this.props.total.get('TOTAL_PAYABLE'))
        }
        this.total = this.props.total;
    }

    componentDidUpdate() {
        let self = this;
        if (this.payableChange) {
            this.payableContainer.classList.add('updated-count');
            this.payableChange = false;
            setTimeout(function () {
                self.payableContainer.classList.remove('updated-count');
            }, 400)
        }
    }

    componentDidMount() {

    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return true;
    }

    render() {
        let total = this.props.total;
        this.checkWhatChanged();

        return <div className="chunk sixteen wide column shadow-1 pad-right-10">
                <div className="left-box">
                    <div className="job-id">({this.props.jobInfo.jid}-{this.props.index})</div>
                    <div className="file-details" onClick={this.props.showFiles}>
                        <a href="#">
                            File <span className="details">details </span>
                        </a>
                        (<span className="f-details-number">{_.size(this.props.jobInfo.files)}</span>)
                    </div>
                </div>
                <div className="single-analysis">
                    <div className="single total">{this.props.jobInfo.total_raw_word_count_print}</div>
                    <div className="single payable-words"
                         ref={(container) => this.payableContainer = container}>
                        {total.get('TOTAL_PAYABLE').get(1)}
                        </div>
                    <div className="single new">{total.get('NEW').get(1)}</div>
                    <div className="single repetition">{total.get('REPETITIONS').get(1)}</div>
                    <div className="single internal-matches">{total.get('INTERNAL_MATCHES').get(1)}</div>
                    <div className="single p-50-74">{total.get('TM_50_74').get(1)}</div>
                    <div className="single p-75-84">{total.get('TM_75_84').get(1)}</div>
                    <div className="single p-85-94">{total.get('TM_85_94').get(1)}</div>
                    <div className="single p-95-99">{total.get('TM_95_99').get(1)}</div>
                    <div className="single tm-100">{total.get('TM_100').get(1)}</div>
                    <div className="single tm-public">{total.get('TM_100_PUBLIC').get(1)}</div>
                    <div className="single tm-context">{total.get('ICE').get(1)}</div>
                    <div className="single machine-translation">{total.get('MT').get(1)}</div>
                </div>
                <div className="right-box">
                    <div className="open-translate ui primary button open">Translate</div>
                </div>

            </div>;


    }
}

export default ChunkAnalyzeHeader;
