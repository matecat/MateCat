let CSSTransitionGroup = React.addons.CSSTransitionGroup;
let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeActions = require('../../actions/AnalyzeActions');



class AnalyzeChunksResume extends React.Component {

    constructor(props) {
        super(props);
        this.payableValues = [];
        this.payableValuesChenged = [];
        this.containers = {};
    }

    showDetails(idJob) {
        AnalyzeActions.showDetails(idJob)
    }

    openSplitModal(id) {
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == id;
        });
        ModalsActions.openSplitJobModal(job, this.props.project, UI.reloadAnalysis);
    }

    openMergeModal(id) {
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == id;
        });
        ModalsActions.openMergeModal(this.props.project.toJS(), job.toJS(), UI.reloadAnalysis);
    }

    thereIsChunkOutsourced() {
        let self = this;
        let outsourceChunk = this.props.project.get('jobs').find(function (item) {
            return !!(item.get('outsource')) && item.get('id') === self.props.idJob;
        });
        return !_.isUndefined(outsourceChunk)
    }

    getTranslateUrl(job, index) {
        let chunk_id = (index)? job.get('id') + '-' + index : job.get('id');
        return '/translate/'+ this.props.project.get('project_slug')+'/'+ job.get('source') +'-'+ job.get('target')+'/'+ chunk_id +'-'+ job.get('password')  ;
    }

    openOutsourceModal(id, index) {
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == id;
        });
        ModalsActions.openOutsourceModal(this.props.project.toJS(), job.toJS(), this.getTranslateUrl(job, index), false, false, false);
    }

    checkPayableChanged(idJob, payable) {
        if (this.payableValues[idJob] && payable !== this.payableValues[idJob]) {
            this.payableValuesChenged[idJob] = true;
        }
        this.payableValues[idJob] = payable;
    }

    getResumeJobs() {
        var self = this;

        let buttonsClass = (this.props.status !== "DONE" || this.thereIsChunkOutsourced()) ? 'disabled' : '';

        return this.props.jobsAnalysis.map(function (jobAnalysis, indexJob) {
            if (self.props.jobsInfo[indexJob].splitted !== "" && _.size(self.props.jobsInfo[indexJob].chunks) > 1) {
                let index = 0;
                let chunksHtml = jobAnalysis.get('totals').map(function (chunkAnalysis, indexChunk) {
                    let chunk = self.props.jobsInfo[indexJob].chunks[indexChunk];
                    index++;

                    self.checkPayableChanged(self.props.jobsInfo[indexJob].jid, chunkAnalysis.get('TOTAL_PAYABLE').get(1));

                    return <div key={indexChunk} className="chunk ui grid shadow-1">
                                <div className="title-job">
                                    <div className="job-id">{chunk.jid}-{index}</div>
                                </div>
                                <div className="titles-compare">
                                    <div className="title-total-words ttw">
                                        <div>{chunk.total_raw_word_count_print}</div>
                                    </div>
                                    {/*<div className="title-standard-words tsw">*/}
                                        {/*<div>xxx</div>*/}
                                    {/*</div>*/}
                                    <div className="title-matecat-words tmw"
                                         ref={(container) => self.containers[self.props.jobsInfo[indexJob].jid] = container}>
                                        <div>{chunkAnalysis.get('TOTAL_PAYABLE').get(1)}</div>
                                    </div>
                                </div>
                                <div className="activity-icons">
                                    <div className="open-translate ui primary button open"
                                         onClick={self.openOutsourceModal.bind(self, chunk.jid, index)}>Translate</div>
                                </div>
                            </div>;
                }).toList().toJS();
                return <div key={indexJob} className="job ui grid">
                    <div className="chunks sixteen wide column">

                        <div className="chunk ui grid shadow-1">
                            <div className="title-job">
                                <div className="source-target">
                                    <div className="source-box">{self.props.jobsInfo[indexJob].source}</div>
                                    <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                    <div className="target-box">{self.props.jobsInfo[indexJob].target}</div>
                                </div>
                                <div className="job-details"
                                    onClick={self.showDetails.bind(this, self.props.jobsInfo[indexJob].jid)}>
                                    <span className="details">Show details </span>
                                </div>
                            </div>
                            <div className="titles-compare">
                                <div className="title-total-words">

                                </div>
                                {/*<div className="title-standard-words">*/}

                                {/*</div>*/}
                                <div className="title-matecat-words">

                                </div>
                            </div>
                            <div className="activity-icons">
                                <div className={"merge ui blue basic button " + buttonsClass}
                                     onClick={self.openMergeModal.bind(self, self.props.jobsInfo[indexJob].jid)}><i className="icon-compress icon"/>Merge</div>
                            </div>
                        </div>
                        {chunksHtml}
                    </div>
                </div>;
            } else {
                let totals = jobAnalysis.get('totals').get(0);
                let obj = self.props.jobsInfo[indexJob].chunks;
                let total_standard = obj[Object.keys(obj)[0]].total_raw_word_count_print;

                self.checkPayableChanged(self.props.jobsInfo[indexJob].jid,
                    jobAnalysis.get('totals').first().get('TOTAL_PAYABLE').get(1));

                return <div key={indexJob} className="job ui grid">
                    <div className="chunks sixteen wide column">
                        <div className="chunk ui grid shadow-1">
                            <div className="title-job">
                                <div className="job-id">{self.props.jobsInfo[indexJob].jid}</div>
                                <div className="source-target">
                                    <div className="source-box">{self.props.jobsInfo[indexJob].source}</div>
                                    <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                    <div className="target-box">{self.props.jobsInfo[indexJob].target}</div>
                                </div>
                                <div className="job-details" onClick={self.showDetails.bind(this, self.props.jobsInfo[indexJob].jid)}>
                                    <div className="details">Details</div>
                                </div>
                            </div>
                            <div className="titles-compare">
                                <div className="title-total-words ttw">
                                    <div>{total_standard}</div>
                                </div>
                                <div className="title-standard-words tsw">
                                    <div>xxx</div>
                                </div>
                                <div className="title-matecat-words tmw"
                                     ref={(container) => self.containers[self.props.jobsInfo[indexJob].jid] = container}>
                                    <div>{jobAnalysis.get('totals').first().get('TOTAL_PAYABLE').get(1)}</div>
                                </div>
                            </div>
                            <div className="activity-icons">
                                <div className={"split ui blue basic button " + buttonsClass}
                                     onClick={self.openSplitModal.bind(self, self.props.jobsInfo[indexJob].jid)}><i className="icon-expand icon"/>Split</div>
                                <div className="open-translate ui primary button open"
                                     onClick={self.openOutsourceModal.bind(self, self.props.jobsInfo[indexJob].jid)}>Translate</div>
                            </div>
                        </div>

                    </div>
                </div>
            }
        }).toList().toJS();
    }

    openAnalysisReport() {
        this.props.openAnalysisReport();
    }

    componentDidUpdate() {
        let self = this;
        let changedData = _.pick(this.payableValuesChenged, function (item, i, array) {
            return item === true;
        });
        if (_.size(changedData) > 0) {
            _.each(changedData, function (item, i) {
                self.containers[i].classList.add('updated-count');
                setTimeout(function () {
                    self.containers[i].classList.remove('updated-count');
                }, 400)
            })
        }
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return ( !nextProps.jobsAnalysis.equals(this.props.jobsAnalysis) ||
        nextProps.status !== this.props.status)
    }

    render() {

        return <div className="project-top ui grid">
            <div className="compare-table sixteen wide column shadow-1">
                <div className="header-compare-table ui grid">
                    <div className="title-job">
                        <h5></h5>
                        <p></p>
                    </div>
                    <div className="titles-compare">
                        <div className="title-total-words">
                            <h5>Total Words</h5>
                            <p>(Actual words in the files)</p>
                        </div>
                        {/*<div className="title-standard-words">*/}
                            {/*<h5>Standard Weighted</h5>*/}
                            {/*<p>(Industry word count)</p>*/}
                        {/*</div>*/}
                        <div className="title-matecat-words">
                            <h5>MateCat Payable Words</h5>
                            <p>(Improved content reuse)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div className="compare-table jobs sixteen wide column">

                {this.getResumeJobs()}

            </div>
            <div className="analyze-report"
                 onClick={this.openAnalysisReport.bind(this)}>
                <h3>Analysis report</h3>
                <div className="rounded">
                    <i className="icon-sort-down icon"/>
                </div>
            </div>
        </div>;


    }
}

export default AnalyzeChunksResume;
