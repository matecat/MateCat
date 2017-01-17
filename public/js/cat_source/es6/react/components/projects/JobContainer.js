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
        return (nextProps.job !== this.props.job || nextProps.lastAction !== this.props.lastAction )
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
            this.props.job.get('review_password') :
            this.props.job.get('password')
        );

        return '/revise/'+this.props.project.get('name')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+  possibly_different_review_password ;
    }

    getEditingLogUrl() {
        return '/editlog/' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    getQAReport() {
        return '/revise-summary/' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    changePassword() {
        var self = this;
        this.oldPassword = this.props.job.get('password');
        this.props.changeJobPasswordFn(this.props.job.toJS())
            .done(function (data) {
                var notification = {
                    title: 'Change job password',
                    text: 'The password has been changed. <a class="undo-password">Undo</a>',
                    type: 'warning',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                var boxUndo = APP.addNotification(notification);
                ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo);
                $('.undo-password').off('click');
                $('.undo-password').on('click', function () {
                    APP.removeNotification(boxUndo);
                    self.props.changeJobPasswordFn(self.props.job.toJS(), 1, self.oldPassword)
                        .done(function (data) {
                            notification = {
                                title: 'Change job password',
                                text: 'The previous password has been restored.',
                                type: 'warning',
                                position: 'tc',
                                timer: 7000
                            };
                            APP.addNotification(notification);
                            ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo);
                        });

                })
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
        var jobTMXUrl = '/TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        var exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('name') + '.zip';

        var originalUrl = '/?action=downloadOriginal&id_job=' + this.props.job.get('id') +' &password=' + this.props.job.get('password') + '&download_type=all';

        var jobStatus = this.getTranslationStatus();
        var downloadButton = (jobStatus == 'translated' || jobStatus == 'approved') ?
            <div className="item" onClick={this.downloadTranslation}><a >Download</a></div> : <div className="item" onClick={this.downloadTranslation}><a ><i className="icon-eye"/>Preview</a></div>;

        var splitButton = (!this.props.isChunk) ? <div className="item"><a target="_blank" href={splitUrl}><i className="icon-expand"/> Split</a></div> : '';

        var menuHtml = <div className="menu">
            <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></div>
                {splitButton}
            <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></div>
            <div className="divider"/>
            <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></div>
            <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></div>
                {downloadButton}
            <div className="divider"/>
            <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></div>
            <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></div>
            <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></div>
            <div className="item" onClick={this.archiveJob.bind(this)}><a ><i className="icon-drawer"/> Archive job</a></div>
            <div className="item" onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o"/> Cancel job</a></div>
            </div>;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <div className="menu">

                <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></div>
                {splitButton}
                <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></div>
                <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></div>
                <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></div>

                    {downloadButton}
                <div className="divider"/>
                <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></div>
                <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></div>
                <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></div>
                <div className="item" onClick={this.activateJob.bind(this)}><a ><i className="icon-drawer unarchive-project"/> Unarchive job</a></div>
                <div className="item" onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o"/> Cancel job</a></div>
            </div>;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <div className="menu">
                    <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh"/> Change Password</a></div>
                    {splitButton}
                    <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit"/> Revise</a></div>
                    <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat"/> QA Report</a></div>
                    <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs"/> Editing Log</a></div>
                    {downloadButton}
                    <div className="divider"/>
                    <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download"/> Download Original</a></div>
                    <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download"/> Export XLIFF</a></div>
                    <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download"/> Export TMX</a></div>
                    <div className="item" onClick={this.activateJob.bind(this)}><a><i className="icon-drawer unarchive-project"/> Resume job</a></div>
                </div>;
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

    getModifyDate() {
        if ( this.props.lastAction ) {
            var date = new Date(this.props.lastAction.event_date);
            return <div><span>Modified: </span> <a target="_blank" href={this.props.activityLogUrl}> {date.toDateString()}</a></div>;
        } else {
            return '';
        }
    }

    render () {
        var translateUrl = this.getTranslateUrl();
        var outsourceUrl = this.getOutsourceUrl();

        var analysisUrl = this.getAnalysisUrl();
        var splitUrl = this.getSplitUrl();
        var mergeUrl = this.getMergeUrl();
        var splitMergeButton = this.getSplitOrMergeButton(splitUrl, mergeUrl);
        // var modifyDate = this.getModifyDate();

        var jobMenu = this.getJobMenu(splitUrl, mergeUrl);
        var tmIcon = this.getTMIcon();
        var idJobLabel = ( !this.props.isChunk ) ? this.props.job.get('id') : this.props.job.get('id') + '-' + this.props.index;

        return <div className="card job z-depth-1">
            <div className="body-job">
                <div className="row">
                    <div className="col">
                        <div className="job-id">
                            <div id={"id-job-" + this.props.job.get('id')}>{ idJobLabel }</div>
                        </div>
                    </div>
                    <div className="col">
                        <div className="progress-bar">
                            <div className="progr">
                                <div className="meter">
                                    <a className="warning-bar" title={'Rejected '+this.props.job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('REJECTED_PERC') + '%'}}/>
                                    <a className="approved-bar" title={'Approved '+this.props.job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('APPROVED_PERC')+ '%' }}/>
                                    <a className="translated-bar" title={'Translated '+this.props.job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('TRANSLATED_PERC') + '%' }}/>
                                    <a className="draft-bar" title={'Draft '+this.props.job.get('stats').get('DRAFT_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('DRAFT_PERC') + '%' }}/>
                                    
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
                        <div className="due-to">
                            <a href="#"><span id="due-date">12 Jan 2016</span><span id="due-hour">, 14.00</span></a>
                        </div>
                    </div>
                    <div className="col">
                        <div className="translator-mail">
                            <a href="#"><span id="translator-job">alessandro.cattelan@translated.net</span></a>
                        </div>
                    </div>
                    {/*<div className="col">*/}
                        {/*<div className="action-modified">*/}
                            {/*{modifyDate}*/}
                        {/*</div>*/}
                    {/*</div>*/}
                    <div className="col right">
                        
                        <ul className="job-activity-icon">
                            {/*tmIcon*/}
                            <li>
                                <div className="ui icon top left pointing dropdown button menu-job"
                                     ref={(dropdown) => this.dropdown = dropdown}>
                                    <i className="icon-more_vert"/>
                                {jobMenu}
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div className="col right">
                        <div className="button-list">
                            <a className="btn waves-effect waves-light translate move-left" target="_blank" href={translateUrl}>Open</a>
                        </div>
                    </div>
                    <div className="col right">
                        <ul className="job-activity-icon">
                            {tmIcon}
                            {/*<li>
                                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0"
                                    onClick={this.openSettings.bind(this)}>
                                    <i className="icon-settings"/>
                                </a>
                            </li>*/}
                        </ul>
                    </div>
                </div>
            </div>
        </div>;
    }
}
export default JobContainer ;
