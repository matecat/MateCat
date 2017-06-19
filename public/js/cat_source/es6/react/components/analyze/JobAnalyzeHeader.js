
let AnalyzeConstants = require('../../constants/AnalyzeConstants');

class JobAnalyzeHeader extends React.Component {

    constructor(props) {
        super(props);
    }

    calculateWords() {
        this.total = 0;
        this.payable = 0;
        let self = this;
        this.props.totals.forEach(function (chunk, i) {
            self.payable = self.payable + chunk.get('TOTAL_PAYABLE').get(0);
        });

        _.each(this.props.jobInfo.chunks, function (chunk, i) {
            self.total = self.total + chunk.total_raw_word_count
        });
    }

    openSplitModal() {
        let self = this;
        let job = this.props.project.get('jobs').find(function (item) {
           return item.get('id') == self.props.jobInfo.jid;
        });
        ModalsActions.openSplitJobModal(job, this.props.project, UI.reloadAnalysis);
    }

    openMergeModal() {
        let self = this;
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == self.props.jobInfo.jid;
        });
        ModalsActions.openMergeModal(this.props.project.toJS(), job.toJS(), UI.reloadAnalysis);
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
        this.calculateWords();
        let buttonsClass = (this.props.status !== "DONE") ? 'disabled' : '';
        return <div className="head-chunk sixteen wide column shadow-1 pad-right-10">
                    <div className="source-target">
                        <div className="source-box">{this.props.jobInfo.source}</div>
                        <div className="in-to">
                            <i className="icon-chevron-right icon"></i>
                        </div>
                        <div className="target-box">{this.props.jobInfo.target}</div>
                    </div>
                    <div className="job-not-payable">
                        <span id="raw-words">{parseInt(this.total)}</span> Total words
                    </div>
                    <div className="job-payable">
                        <a href="#">
                            <span id="words">{parseInt(this.payable)}</span> Payable words
                        </a>
                    </div>
            {(this.props.jobInfo.splitted === "splitted") ? (
                <div className={"merge ui button "  + buttonsClass}
                    onClick={this.openMergeModal.bind(this)} >
                    <i className="icon-compress icon"/> Merge</div>
            ) : (
                <div className={"split ui button "  + buttonsClass}
                     onClick={this.openSplitModal.bind(this)}>
                    <i className="icon-expand icon"/> Split</div>
            )}


                </div>;

    }
}

export default JobAnalyzeHeader;
