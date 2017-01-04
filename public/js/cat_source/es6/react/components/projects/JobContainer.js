class JobContainer extends React.Component {

    constructor (props) {
        super(props);
        this.getTranslateUrl = this.getTranslateUrl.bind(this);
        this.getOutsourceUrl = this.getOutsourceUrl.bind(this);
        this.getAnalysisUrl = this.getAnalysisUrl.bind(this);
        this.changePassword = this.changePassword.bind(this);
        this.downloadTranslation = this.downloadTranslation.bind(this);
    }

    componentDidMount () {
        $(this.dropdown).dropdown({
            belowOrigin: true
        });
        $('.tooltipped').tooltip({delay: 50});
    }

    /**
     * Returns the translation status evaluating the job stats
     */

    getTranslationStatus() {
        var stats = this.props.job.get('stats').toJS();
        var t = 'approved';
        var app = parseFloat(stats.APPROVED);
        var tra = parseFloat(stats.TRANSLATED);
        var dra = parseFloat(stats.DRAFT);
        var rej = parseFloat(stats.REJECTED);

        if (tra) t = 'translated';
        if (dra) t = 'draft';
        if (rej) t = 'draft';

        if( !tra && !dra && !rej && !app ){
            t = 'draft';
        }
        return t ;
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
        var possibly_different_review_password = ( this.props.job.has('review_password') ?
            this.props.job.get('password') :
            this.props.job.get('review_password')
        );

        return '/revise/'+this.props.project.get('name')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+  possibly_different_review_password ;
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

    downloadTranslation() {
        this.props.downloadTranslationFn(this.props.project.toJS(), this.props.job.toJS());
    }


    getJobMenu(splitUrl) {
        var reviseUrl = this.getReviseUrl();
        var editLogUrl = this.getEditingLogUrl();
        var qaReportUrl = this.getQAReport();
        var jobTMXUrl = 'TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        var exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('name') + '.zip';

        var originalUrl = '/?action=downloadOriginal&id_job=' + this.props.job.get('id') +' &password=' + this.props.job.get('password') + '&download_type=all';

        var jobStatus = this.getTranslationStatus();
        var downloadButton = (jobStatus == 'translated' || jobStatus == 'approved') ?
            <li onClick={this.downloadTranslation}><a >Download</a></li> : <li onClick={this.downloadTranslation}><a ><i className="icon-eye"/>Preview</a></li>;


        var menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>
                <li onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></li>
                <li><a target="_blank" href={splitUrl}><i className="icon-expand"/> Split</a></li>
                <li><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></li>
                <li className="divider"/>
                <li><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></li>
                <li className="divider"/>
                {downloadButton}
                <li><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></li>
                <li className="divider"/>
                <li onClick={this.archiveJob.bind(this)}><a ><i className="icon-drawer"/> Archive job</a></li>
                <li onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o"/> Cancel job</a></li>
            </ul>;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>

                <li onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></li>
                <li><a target="_blank" href={splitUrl}><i className="icon-expand"/>  Split</a></li>
                <li><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></li>
                <li className="divider"/>
                <li><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></li>
                <li className="divider"/>
                {downloadButton}
                <li><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></li>
                <li className="divider"/>
                <li onClick={this.activateJob.bind(this)}><a ><i className="icon-drawer unarchive-project"/> Unarchive job</a></li>
                <li onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o"/> Cancel job</a></li>
            </ul>;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <ul id={'dropdownJob' + this.props.job.get('id')} className='dropdown-content'>
                <li onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></li>
                <li><a target="_blank" href={splitUrl} ><i className="icon-expand"/>  Split</a></li>
                <li><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></li>
                <li className="divider"/>
                <li><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></li>
                <li><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></li>
                <li className="divider"/>
                {downloadButton}
                <li><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></li>
                <li><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></li>
                <li><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></li>
                <li className="divider"/>
                <li onClick={this.activateJob.bind(this)}><a ><i className="icon-drawer unarchive-project"/> Resume job</a></li>
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
        return '/analyze/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + this.props.job.get('id');
    }

    getMergeUrl() {
        return '/analyze/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=merge&jobid=' + this.props.job.get('id');
    }

    getActivityLogUrl() {
        return '/activityLog/'+ this.props.project.get('name') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + this.props.job.get('id');
    }

    openSettings() {
        ManageActions.openJobSettings(this.props.job.toJS(), this.props.project.get('name'));
    }

    openTMPanel() {
        ManageActions.openJobTMPanel(this.props.job.toJS(), this.props.project.get('name'));
    }

    getTMIcon() {
        if (JSON.parse(this.props.job.get('private_tm_key')).length) {
            return <li>
                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0"
                   onClick={this.openTMPanel.bind(this)}>
                    <i className="icon-tm-matecat"/>
                </a>
            </li>;
        } else {
            return '';
        }
    }

    getSplitOrMergeButton(splitUrl, mergeUrl) {

        if (this.props.isChunk) {
            return <a className="btn waves-effect split waves-dark" target="_blank" href={mergeUrl}>
                <i className="large icon-compress right"/>Merge
            </a>
        } else {
            return <a className="btn waves-effect split waves-dark" target="_blank" href={splitUrl}>
                <i className="large icon-expand right"/>Split
            </a>
        }
    }

    render () {
        var translateUrl = this.getTranslateUrl();
        var outsourceUrl = this.getOutsourceUrl();

        var analysisUrl = this.getAnalysisUrl();
        var splitUrl = this.getSplitUrl();
        var mergeUrl = this.getMergeUrl();
        var splitMergeButton = this.getSplitOrMergeButton(splitUrl, mergeUrl);

        var jobMenu = this.getJobMenu(splitUrl, mergeUrl);
        var tmIcon = this.getTMIcon();
        var idJobLabel = ( !this.props.isChunk ) ? this.props.job.get('id') : this.props.job.get('id') + '-' + this.props.index;

        return <div className="card job z-depth-1">
            <div className="head-job open-head-job">
                <div className="row">
                    <div className="col s2">
                        <div className="job-id">
                            <div id={"id-job-" + this.props.job.get('id')}><span>ID:</span>{ idJobLabel }</div>
                        </div>
                    </div>
                    <div className="col s10">
                        <ul className="job-activity-icon right">
                            {tmIcon}
                            <li>
                                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0"
                                    onClick={this.openSettings.bind(this)}>
                                    <i className="icon-settings"/>
                                </a>
                            </li>
                            <li>
                                <a className='dropdown-button btn-floating btn-flat waves-effect waves-dark z-depth-0 class-prova'
                                   data-activates={'dropdownJob' + this.props.job.get('id')}
                                   ref={(dropdown) => this.dropdown = dropdown}>
                                    <i className="icon-more_vert"/>
                                </a>
                                {jobMenu}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div className="body-job">
                <div className="row">
                    <div className="col">
                        <div className="combo-language single">
                            <ul>
                                <li>
                                    <span className="tooltipped" data-tooltip={this.props.job.get('sourceTxt')}>{this.props.job.get('source')}</span> <i className="icon-play"/>
                                </li>
                                <li>
                                    <span className="tooltipped" data-tooltip={this.props.job.get('targetTxt')}>{this.props.job.get('target')}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div className="col">
                        <div className="progress-bar">
                            <div className="progr">
                                <div className="meter">
                                    <a className="warning-bar" title={'Approved '+this.props.job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('REJECTED_PERC') + '%'}}/>
                                    <a className="translated-bar" title={'Translated '+this.props.job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('TRANSLATED_PERC') + '%' }}/>
                                    <a className="approved-bar" title={'Approved '+this.props.job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('APPROVED_PERC')+ '%' }}/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col">
                        <div className="payable-words">
                            <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                        </div>
                    </div>
                    <div className="col">
                        <div className="action-modified">
                            <div><span>Modified: </span> <a href="#"> yesterday</a></div>
                        </div>
                    </div>
                    <div className="col m4 right">
                        <div className="button-list split-outsource right">
                            {splitMergeButton}
                            <a className="btn waves-effect waves-light outsource" target="_blank" href={outsourceUrl}>outsource</a>
                            {/*<a className="btn waves-effect waves-light outsourced" target="_blank" href={outsourcedUrl}>outsource</a> */}
                            <a className="btn waves-effect waves-light translate move-left" target="_blank" href={translateUrl}>Open</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>;
    }
}
export default JobContainer ;
