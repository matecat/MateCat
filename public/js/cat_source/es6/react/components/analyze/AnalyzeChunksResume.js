let AnalyzeConstants = require('../../constants/AnalyzeConstants');
let AnalyzeActions = require('../../actions/AnalyzeActions');
let OutsourceContainer = require('../outsource/OutsourceContainer').default;



class AnalyzeChunksResume extends React.Component {

    constructor(props) {
        super(props);
        this.payableValues = [];
        this.payableValuesChenged = [];
        this.containers = {};
        this.state = {
            openDetails: false,
            openOutsource: false,
            outsourceJobId : null
        }
    }

    showDetails(idJob, evt) {
        if ($(evt.target).parents('.outsource-container').length ===  0) {
            evt.preventDefault();
            evt.stopPropagation();
            AnalyzeActions.showDetails(idJob);
            this.setState({
                openDetails: true
            });
        }


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

    openOutsourceModal(idJob, e) {
        e.stopPropagation();
        e.preventDefault();
        this.setState({
            openOutsource: true,
            outsourceJobId : idJob
        });
    }

    closeOutsourceModal() {
        this.setState({
            openOutsource: false,
            outsourceJobId : null
        });
    }

    checkPayableChanged(idJob, payable) {
        if (this.payableValues[idJob] && payable !== this.payableValues[idJob]) {
            this.payableValuesChenged[idJob] = true;
        }
        this.payableValues[idJob] = payable;
    }

    getOpenButton(chunk, index) {
        return <div className="open-translate ui primary button open"
                    onClick={this.openOutsourceModal.bind(this, index)}>Translate</div>;
    }

    getResumeJobs() {
        var self = this;

        let buttonsClass = (this.props.status !== "DONE" || this.thereIsChunkOutsourced()) ? 'disabled' : '';
        if (!this.props.jobsAnalysis.isEmpty()) {
            return _.map(this.props.jobsInfo,function (item, indexJob) {
                let jobAnalysis = self.props.jobsAnalysis.get(indexJob);

                if (item.splitted !== "" && _.size(item.chunks) > 1) {
                    let chunksHtml = _.map(item.chunks, function (chunkConfig, index) {
                        let indexChunk = chunkConfig.jpassword;
                        let chunkAnalysis = jobAnalysis.get('totals').get(indexChunk);
                        let chunk = chunkConfig;
                        let chunkJob = self.props.project.get('jobs').find(function (job) {
                            return job.get('id') == chunk.jid && job.get('password') === chunk.jpassword;
                        });
                        index++;

                        let openOutsource = (self.state.openOutsource && self.state.outsourceJobId === (chunk.jid +'-'+ index));

                        self.checkPayableChanged(chunk.jid + index, chunkAnalysis.get('TOTAL_PAYABLE').get(1));

                        let openOutsourceClass = (openOutsource) ? 'openOutsource' : '';

                        return <div key={indexChunk} className={"chunk ui grid shadow-1 " + openOutsourceClass} onClick={self.showDetails.bind(self, chunk.jid)}>
                            <div className="title-job">
                                <div className="job-id" >{'Chunk ' + index}</div>
                            </div>
                            <div className="titles-compare">
                                <div className="title-total-words ttw">
                                    <div>{chunk.total_raw_word_count_print}</div>
                                </div>
                                <div className="title-standard-words tsw">
                                    <div>{chunkAnalysis.get('standard_word_count').get(1)}</div>
                                </div>
                                <div className="title-matecat-words tmw"
                                     ref={(container) => self.containers[chunk.jid + index] = container}>
                                    <div>
                                        {chunkAnalysis.get('TOTAL_PAYABLE').get(1)}</div>
                                </div>
                            </div>
                            <div className="activity-icons">
                                {self.getOpenButton(chunkJob.toJS(), chunk.jid + '-' + index)}
                            </div>
                            <OutsourceContainer project={self.props.project}
                                                job={chunkJob}
                                                standardWC={chunkAnalysis.get('standard_word_count').get(1)}
                                                url={self.getTranslateUrl(chunkJob, index)}
                                                showTranslatorBox={false}
                                                extendedView={true}
                                                showOpenBox={true}
                                                onClickOutside={self.closeOutsourceModal.bind(self)}
                                                openOutsource={openOutsource}
                                                idJobLabel={ chunk.jid +'-'+ index }
                                                outsourceJobId={self.state.outsourceJobId}
                            />
                        </div>;
                    });

                    return <div key={indexJob} className="job ui grid">
                        <div className="chunks sixteen wide column">

                            <div className="chunk ui grid shadow-1" onClick={self.showDetails.bind(self, self.props.jobsInfo[indexJob].jid)}>
                                <div className="title-job splitted">
                                    <div className="job-id" >ID: {self.props.jobsInfo[indexJob].jid}</div>
                                    <div className="source-target" >
                                        <div className="source-box">{self.props.jobsInfo[indexJob].source}</div>
                                        <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                        <div className="target-box">{self.props.jobsInfo[indexJob].target}</div>
                                    </div>
                                </div>

                                <div className="activity-icons">
                                    <div className="merge ui blue basic button"
                                         onClick={self.openMergeModal.bind(self, self.props.jobsInfo[indexJob].jid)}><i className="icon-compress icon"/>Merge</div>
                                </div>
                            </div>
                            {chunksHtml}
                        </div>
                    </div>;
                } else {
                    let obj = self.props.jobsInfo[indexJob].chunks[0];
                    let password = obj.jpassword;
                    let total_raw = obj.total_raw_word_count_print;
                    let total_standard = (jobAnalysis.get('totals').first().get('standard_word_count')) ?
                        jobAnalysis.get('totals').first().get('standard_word_count').get(1) : 0;

                    let chunkJob = self.props.project.get('jobs').find(function (job) {
                        return job.get('id') == self.props.jobsInfo[indexJob].jid ;
                    });

                    let openOutsource = (self.state.openOutsource && self.state.outsourceJobId === self.props.jobsInfo[indexJob].jid);
                    let openOutsourceClass = (openOutsource) ? 'openOutsource' : '';

                    self.checkPayableChanged(self.props.jobsInfo[indexJob].jid,
                        jobAnalysis.get('totals').first().get('TOTAL_PAYABLE').get(1));

                    return <div key={indexJob} className="job ui grid">
                        <div className="chunks sixteen wide column">
                            <div className={"chunk ui grid shadow-1 " + openOutsourceClass} onClick={self.showDetails.bind(self, self.props.jobsInfo[indexJob].jid) }>
                                <div className="title-job">
                                    <div className="job-id">ID: {self.props.jobsInfo[indexJob].jid}</div>
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
                                    {(!config.jobAnalysis && config.splitEnabled) ? (
                                        <div className={"split ui blue basic button " + buttonsClass + ' '}
                                             onClick={self.openSplitModal.bind(self, self.props.jobsInfo[indexJob].jid)}><i className="icon-expand icon"/>Split</div>
                                    ) : (null)}
                                    {self.getOpenButton(chunkJob.toJS(), self.props.jobsInfo[indexJob].jid)}
                                </div>
                            </div>
                            <OutsourceContainer project={self.props.project}
                                                job={chunkJob}
                                                url={self.getTranslateUrl(chunkJob)}
                                                standardWC={total_standard}
                                                showTranslatorBox={false}
                                                extendedView={true}
                                                showOpenBox={true}
                                                onClickOutside={self.closeOutsourceModal.bind(self)}
                                                openOutsource={openOutsource}
                                                idJobLabel={ self.props.jobsInfo[indexJob].jid }
                                                outsourceJobId={self.state.outsourceJobId}/>
                        </div>
                    </div>
                }
            });
        } else {
            return this.props.project.get('jobs').map(function (jobInfo, indexJob) {
                return <div key={jobInfo.get('id') + '-' + indexJob} className="job ui grid">
                    <div className="chunks sixteen wide column">
                        <div className="chunk ui grid shadow-1">
                            <div className="title-job no-split">
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

    openAnalysisReport(e) {
        e.preventDefault();
        e.stopPropagation();
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
        if (_.size(changedData) > 0 ) {
            _.each(changedData, function (item, i) {
                self.containers[i].classList.add('updated-count');
                setTimeout(function () {
                    self.containers[i].classList.remove('updated-count');
                }, 400)
            })
        }
    }

    componentDidMount() {
        let self = this;
        if (this.props.status === 'DONE') {
            _.each(self.containers, function (item, i) {
                item.classList.add('updated-count');
                setTimeout(function () {
                    self.containers[i].classList.remove('updated-count');
                }, 400)
            })
        }
    }

    componentWillUnmount() {
    }

    shouldComponentUpdate(nextProps, nextState){
        return ( !nextProps.jobsAnalysis.equals(this.props.jobsAnalysis) ||
            nextProps.status !== this.props.status ||
            nextState.openDetails !== this.state.openDetails ||
            nextState.outsourceJobId !== this.state.outsourceJobId ||
            !nextProps.project.equals(this.props.project)
        )

    }

    render() {
        let showHideText = (this.state.openDetails) ? "Hide Details" : "Show Details";
        let iconClass = (this.state.openDetails) ? "open" : "";
        let html = this.getResumeJobs()
        return <div className="project-top ui grid">
            <div className="compare-table sixteen wide column">
                <div className="header-compare-table ui grid shadow-1">
                    <div className="title-job">
                        <h5/>
                        <p/>
                    </div>
                    <div className="titles-compare">
                        { !config.isCJK ? (
                        <div className="title-total-words">
                            <h5>Total word count</h5>
                        </div>
                        ) : (
                        <div className="title-total-words">
                            <h5>Total character count</h5>
                        </div>
                            )}
                        <div className="title-standard-words">
                            <h5>Industry weighted
                                <span data-tooltip="As counted by other CAT tools">
                                    <i className="icon-info icon"/>
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

                {html}

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
