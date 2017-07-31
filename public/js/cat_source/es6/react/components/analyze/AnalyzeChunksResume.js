let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeActions = require('../../actions/AnalyzeActions');



class AnalyzeChunksResume extends React.Component {

    constructor(props) {
        super(props);
        this.payableValues = [];
        this.payableValuesChenged = [];
        this.containers = {};
        this.state = {
            openDetails: false
        }
    }

    showDetails(idJob) {
        AnalyzeActions.showDetails(idJob);
        this.setState({
            openDetails: true
        });

    }
    openSplitModal(id,e) {
        e.stopPropagation();
        e.preventDefault();
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == id;
        });
        ModalsActions.openSplitJobModal(job, this.props.project, UI.reloadAnalysis);
    }

    openMergeModal(id, e) {
        e.stopPropagation();
        e.preventDefault();
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

    openOutsourceModal(id, pass, index, e) {
        e.stopPropagation();
        e.preventDefault();
        let job = this.props.project.get('jobs').find(function (item) {
            return item.get('id') == id && item.get('password') == pass;
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
        if (!this.props.jobsAnalysis.isEmpty()) {
            return this.props.jobsAnalysis.map(function (jobAnalysis, indexJob) {
                if (self.props.jobsInfo[indexJob].splitted !== "" && _.size(self.props.jobsInfo[indexJob].chunks) > 1) {
                    let index = 0;
                    let chunksHtml = jobAnalysis.get('totals').reverse().map(function (chunkAnalysis, indexChunk) {
                        let chunk = self.props.jobsInfo[indexJob].chunks[indexChunk];
                        index++;

                        self.checkPayableChanged(self.props.jobsInfo[indexJob].jid + index, chunkAnalysis.get('TOTAL_PAYABLE').get(1));

                        return <div key={indexChunk} className="chunk ui grid shadow-1" onClick={self.showDetails.bind(self, self.props.jobsInfo[indexJob].jid)}>
                            <div className="title-job">
                                <div className="job-id" >Chunk {index}</div>
                            </div>
                            <div className="titles-compare">
                                <div className="title-total-words ttw">
                                    <div>{chunk.total_raw_word_count_print}</div>
                                </div>
                                <div className="title-standard-words tsw">
                                    <div>{chunkAnalysis.get('standard_word_count').get(1)}</div>
                                </div>
                                <div className="title-matecat-words tmw"
                                     ref={(container) => self.containers[self.props.jobsInfo[indexJob].jid] = container}>
                                    <div>
                                        {chunkAnalysis.get('TOTAL_PAYABLE').get(1)}</div>
                                </div>
                            </div>
                            <div className="activity-icons">
                                <div className="open-translate ui primary button open"
                                     onClick={self.openOutsourceModal.bind(self, chunk.jid, indexChunk, index)}>Translate</div>
                            </div>
                        </div>;
                    }).toList().toJS();
                    return <div key={indexJob} className="job ui grid">
                        <div className="chunks sixteen wide column">

                            <div className="chunk ui grid shadow-1" onClick={self.showDetails.bind(self, self.props.jobsInfo[indexJob].jid)}>
                                <div className="title-job">
                                    <div className="source-target" >
                                        <div className="source-box">{self.props.jobsInfo[indexJob].source}</div>
                                        <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                        <div className="target-box">{self.props.jobsInfo[indexJob].target}</div>
                                    </div>
                                </div>
                                <div className="titles-compare">
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
                    let obj = self.props.jobsInfo[indexJob].chunks;
                    let password = Object.keys(obj)[0];
                    let total_raw = obj[Object.keys(obj)[0]].total_raw_word_count_print;
                    let total_standard = jobAnalysis.get('totals').first().get('standard_word_count').get(1);

                    self.checkPayableChanged(self.props.jobsInfo[indexJob].jid,
                        jobAnalysis.get('totals').first().get('TOTAL_PAYABLE').get(1));

                    return <div key={indexJob} className="job ui grid">
                        <div className="chunks sixteen wide column">
                            <div className="chunk ui grid shadow-1" onClick={self.showDetails.bind(self, self.props.jobsInfo[indexJob].jid) }>
                                <div className="title-job">
                                    {/*<div className="job-id">({self.props.jobsInfo[indexJob].jid})</div>*/}
                                    <div className="source-target" >
                                        <div className="source-box no-split">{self.props.jobsInfo[indexJob].source}</div>
                                        <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                        <div className="target-box no-split">{self.props.jobsInfo[indexJob].target}</div>
                                    </div>
                                </div>
                                <div className="titles-compare">
                                    <div className="title-total-words ttw">
                                        {/*<div className="cell-label">Total words:</div>*/}
                                        <div>{total_raw}</div>
                                    </div>
                                    <div className="title-standard-words tsw">
                                        {/*<div className="cell-label">Other CAT tool</div>*/}
                                        <div>{total_standard}</div>
                                    </div>
                                    <div className="title-matecat-words tmw"
                                         ref={(container) => self.containers[self.props.jobsInfo[indexJob].jid] = container}>
                                        {/*<div className="cell-label" >Weighted words:</div>*/}
                                        <div>
                                            {/*<i className="icon-chart4 icon"/>*/}
                                            {jobAnalysis.get('totals').first().get('TOTAL_PAYABLE').get(1)}</div>
                                    </div>
                                </div>
                                <div className="activity-icons">
                                    <div className={"split ui blue basic button " + buttonsClass}
                                         onClick={self.openSplitModal.bind(self, self.props.jobsInfo[indexJob].jid)}><i className="icon-expand icon"/>Split</div>
                                    <div className="open-translate ui primary button open"
                                         onClick={self.openOutsourceModal.bind(self, self.props.jobsInfo[indexJob].jid, password, null)}>Translate</div>
                                </div>
                            </div>

                        </div>
                    </div>
                }
            }).toList().toJS();
        } else {
            return this.props.project.get('jobs').map(function (jobInfo, indexJob) {
                return <div key={jobInfo.get('id') + '-' + indexJob} className="job ui grid">
                    <div className="chunks sixteen wide column">
                        <div className="chunk ui grid shadow-1">
                            <div className="title-job">
                                <div className="source-target" >
                                    <div className="source-box no-split">{jobInfo.get('sourceTxt')}</div>
                                    <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                    <div className="target-box no-split">{jobInfo.get('targetTxt')}</div>
                                </div>
                            </div>
                            <div className="titles-compare">
                                <div className="title-total-words ttw">
                                    <div>0</div>
                                </div>
                                <div className="title-standard-words tsw">
                                    <div>0</div>
                                </div>
                                <div className="title-matecat-words tmw">
                                    <div>0</div>
                                </div>
                            </div>
                            <div className="activity-icons"/>
                        </div>

                    </div>
                </div>
            });
        }
    }

    openAnalysisReport() {
        this.props.openAnalysisReport();
        this.setState({
            openDetails: !this.state.openDetails
        });
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
            nextProps.status !== this.props.status ||
            nextState.openDetails !== this.state.openDetails)
    }

    render() {
        let showHideText = (this.state.openDetails) ? "Hide Details" : "Show Details";
        let iconClass = (this.state.openDetails) ? "open" : "";
        return <div className="project-top ui grid">
            <div className="compare-table sixteen wide column">
                <div className="header-compare-table ui grid shadow-1">
                    <div className="title-job">
                        <h5/>
                        <p/>
                    </div>
                    <div className="titles-compare">
                        <div className="title-total-words">
                            <h5>Total word count</h5>
                        </div>
                        <div className="title-standard-words">
                            <h5>Industry weighted
                                <span data-tooltip="As counted by other CAT tools">
                                    <span className="icon-info icon"/>
                                </span>
                            </h5>
                        </div>
                        <div className="title-matecat-words">
                            <h5>MateCat weighted</h5>
                        </div>
                    </div>
                </div>
            </div>
            <div className="compare-table jobs sixteen wide column">

                {this.getResumeJobs()}

            </div>
            { (!this.props.jobsAnalysis.isEmpty()) ? (
                <div className="analyze-report"
                     onClick={this.openAnalysisReport.bind(this)}>
                    <h3>{showHideText}</h3>
                    <div className="rounded">
                        <i className= {"icon-sort-down icon " + iconClass }/>
                    </div>
                </div>
            ):(null)}

        </div>;


    }
}

export default AnalyzeChunksResume;
