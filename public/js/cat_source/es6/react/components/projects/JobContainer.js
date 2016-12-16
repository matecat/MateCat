class JobContainer extends React.Component {

    constructor (props) {
        super(props);
        this.getTranslateUrl = this.getTranslateUrl.bind(this);
        this.getOutsourceUrl = this.getOutsourceUrl.bind(this);
        this.getAnalysisUrl = this.getAnalysisUrl.bind(this);
        this.changePassword = this.changePassword.bind(this);
    }

    componentDidMount () {
        $(this.dropdown).dropdown({
            belowOrigin: true
        });
    }

    shouldComponentUpdate(nextProps, nextState){
        return (nextProps.job !== this.props.job )
    }

    getTranslateUrl() {
        var use_prefix = ( this.props.jobsLenght > 1 );
        var chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        return '/translate/'+this.props.project.get('name')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    getReviseUrl() {
        var use_prefix = ( this.props.jobsLenght > 1 );
        var chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        return '/revise/'+this.props.project.get('name')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    getEditingLogUrl() {
        return 'editlog/' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    getQAReport() {
        return 'revise-summary/' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    changePassword() {
        var self = this;
        this.props.changeJobPasswordFn(this.props.job.toJS())
            .done(function (data) {
                var notification = {
                    title: 'Change job password',
                    text: "The password has been changed",
                    type: 'warning', position: 'tc'
                };
                APP.addNotification(notification);
                ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo);
            });
    }

    archiveJob() {
        this.props.changeStatusFn('job', this.props.job.toJS(), 'archived');
        ManageActions.removeJob(this.props.project, this.props.job);
    }

    cancelJob() {
        this.props.changeStatusFn('job', this.props.job.toJS(), 'cancelled');
        ManageActions.removeJob(this.props.project, this.props.job);
    }

    activateJob() {
        this.props.changeStatusFn('job', this.props.job.toJS(), 'active');
        ManageActions.removeJob(this.props.project, this.props.job);
    }

    getJobMenu(splitUrl) {
        var reviseUrl = this.getReviseUrl();
        var editLogUrl = this.getEditingLogUrl();
        var qaReportUrl = this.getQAReport();
        var jobTMXUrl = 'TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        var exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('name') + '.zip';


        var menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>
                <li onClick={this.archiveJob.bind(this)}><a >Archive job</a></li>
                <li onClick={this.cancelJob.bind(this)}><a >Cancel job</a></li>
                <li onClick={this.changePassword.bind(this)}><a >Change Password</a></li>
                <li><a target="_blank" href={splitUrl}>Split</a></li>
                <li><a target="_blank" href={reviseUrl}>Revise</a></li>
                <li><a target="_blank" href={qaReportUrl}>QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}>Editing Log</a></li>
                <li><a >Preview</a></li>
                <li><a >Download</a></li>
                <li><a >Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}>Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}>Export TMX</a></li>
            </ul>;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>
                <li onClick={this.activateJob.bind(this)}><a >Unarchive job</a></li>
                <li onClick={this.cancelJob.bind(this)}><a >Cancel job</a></li>
                <li onClick={this.changePassword.bind(this)}><a >Change Password</a></li>
                <li><a target="_blank" href={splitUrl}>Split</a></li>
                <li><a target="_blank" href={reviseUrl}>Revise</a></li>
                <li><a target="_blank" href={qaReportUrl}>QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}>Editing Log</a></li>
                <li><a >Preview</a></li>
                <li><a >Download</a></li>
                <li><a >Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}>Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}>Export TMX</a></li>
            </ul>;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>
                <li onClick={this.activateJob.bind(this)}><a >Resume job</a></li>
                <li onClick={this.changePassword.bind(this)}><a >Change Password</a></li>
                <li><a target="_blank" href={splitUrl} >Split</a></li>
                <li><a target="_blank" href={reviseUrl}>Revise</a></li>
                <li><a target="_blank" href={qaReportUrl}>QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}>Editing Log</a></li>
                <li><a >Preview</a></li>
                <li><a >Download</a></li>
                <li><a >Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}>Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}>Export TMX</a></li>
            </ul>;
        }
        return menuHtml;
    }

    getOutsourceUrl() {
        return '/analyze/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=analysis&jobid=' + this.props.job.get('id');
    }

    getAnalysisUrl() {
        return '/jobanalysis/'+this.props.project.get('id')+ '-' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    getSplitUrl() {
        return '/analyze/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password'); // + '?open=split&jobid=' + this.props.job.get('id');
    }

    getActivityLogUrl() {
        return '/activityLog/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + this.props.job.get('id');
    }

    openSettings() {
        ManageActions.openJobSettings(this.props.job.toJS(), this.props.project.get('name'));
    }

    render () {
        var translateUrl = this.getTranslateUrl();
        var outsourceUrl = this.getOutsourceUrl();
        var analysisUrl = this.getAnalysisUrl();
        var splitUrl = this.getSplitUrl();
        var jobMenu = this.getJobMenu(splitUrl);
        return <div className="card job z-depth-1">
            <div className="head-job open-head-job">
                <div className="row">
                    <div className="col s2">
                        <div className="job-id">
                            <div id="id-job"><span>ID:</span>{this.props.job.get('id') + '-' + this.props.index }</div>
                        </div>
                    </div>
                    <div className="col s10">
                        <ul className="job-activity-icon right">

                            <li>
                                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0"
                                    onClick={this.openSettings.bind(this)}>
                                    <i className="material-icons">settings</i>
                                </a>
                            </li>
                            <li>
                                <a className='dropdown-button btn-floating btn-flat waves-effect waves-dark z-depth-0'
                                   data-activates={'dropdownJob' + this.props.job.get('id')}
                                   ref={(dropdown) => this.dropdown = dropdown}>
                                    <i className="material-icons">more_vert</i>
                                </a>
                                {jobMenu}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div className="body-job">
                <div className="row">
                    <div className="col s7">
                        <div className="row">
                            <div className="col s3">
                                <div className="combo-language">
                                    <span id="source">{this.props.job.get('source')}</span> <i className="material-icons">play_arrow</i>
                                    <span id="target">{this.props.job.get('target')}</span>
                                </div>
                            </div>
                            <div className="col s7">
                                <div className="progress-bar">
                                    <div className="progress">
                                        <div className="determinate" style={{width: '70%'}}></div>
                                    </div>
                                </div>
                            </div>
                            <div className="col s2">
                                <div className="payable-words">
                                    <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col s5">
                        <div className="row">
                            <div className="col s10">
                                <div className="button-list split-outsource right">
                                    <a className="btn waves-effect white waves-dark" target="_blank" href={splitUrl}>split
                                        <i className="large material-icons rotate">swap_horiz</i>
                                    </a>
                                    <a className="btn waves-effect waves-light green" target="_blank" href={outsourceUrl}>outsource</a>
                                </div>
                            </div>
                            <div className="col s2 right">
                                <div className="button-list open right">
                                    <a className="btn waves-effect waves-light" target="_blank" href={translateUrl}>open</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        // <div className="row">
        //         <div className="col s4">
        //             <h6>{'JOB ID: ' + this.props.job.get('id') + '-' + this.props.index }</h6>
        //         <a className="collection-item">
        //         {this.props.job.get('source') + '->' + this.props.job.get('target') + ' ' + this.props.job.get('stats').get('TOTAL_FORMATTED') + ' Payable Words'}}</a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={analysisUrl}> Outsource </a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={translateUrl}> Open</a>
        //     </div>
        //     <div className="col s3">
        //         <a target="_blank" href={splitUrl}> split</a>
            {/*</div>*/}
        {/*</div>;*/}
    }
}
export default JobContainer ;
