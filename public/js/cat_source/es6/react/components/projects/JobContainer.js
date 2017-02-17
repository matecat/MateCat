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
        $('.button.tm-keys, .button.comments-tooltip').popup();
    }

    /**
     * Returns the translation status evaluating the job stats
     */

    getTranslationStatus() {
        let stats = this.props.job.get('stats').toJS();
        let t = 'approved';
        let app = parseFloat(stats.APPROVED);
        let tra = parseFloat(stats.TRANSLATED);
        let dra = parseFloat(stats.DRAFT);
        let rej = parseFloat(stats.REJECTED);

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
        let use_prefix = ( this.props.jobsLenght > 1 );
        let chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        return '/translate/'+this.props.project.get('name')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    getReviseUrl() {
        let use_prefix = ( this.props.jobsLenght > 1 );
        let chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        let possibly_different_review_password = ( this.props.job.has('review_password') ?
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
        let self = this;
        this.oldPassword = this.props.job.get('password');
        this.props.changeJobPasswordFn(this.props.job.toJS())
            .done(function (data) {
                let notification = {
                    title: 'Change job password',
                    text: 'The password has been changed. <a className="undo-password">Undo</a>',
                    type: 'warning',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                let boxUndo = APP.addNotification(notification);
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
        ManageActions.changeJobStatus(this.props.project, this.props.job, 'archived');
    }

    cancelJob() {
        ManageActions.changeJobStatus(this.props.project, this.props.job, 'cancelled');
    }

    activateJob() {
        ManageActions.changeJobStatus(this.props.project, this.props.job, 'active');
    }

    downloadTranslation() {
        this.props.downloadTranslationFn(this.props.project.toJS(), this.props.job.toJS());
    }

    openAssignToTranslatorModal() {
        ManageActions.openAssignToTranslator(this.props.project, this.props.job);
    }


    getJobMenu(splitUrl) {
        let reviseUrl = this.getReviseUrl();
        let editLogUrl = this.getEditingLogUrl();
        let qaReportUrl = this.getQAReport();
        let jobTMXUrl = '/TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        let exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('name') + '.zip';

        let originalUrl = '/?action=downloadOriginal&id_job=' + this.props.job.get('id') +' &password=' + this.props.job.get('password') + '&download_type=all';

        let jobStatus = this.getTranslationStatus();
        let downloadButton = (jobStatus == 'translated' || jobStatus == 'approved') ?
            <div className="item" onClick={this.downloadTranslation}><a >Download</a></div> : <div className="item" onClick={this.downloadTranslation}><a ><i className="icon-eye icon"/>Preview</a></div>;

        let splitButton = (!this.props.isChunk) ? <div className="item"><a target="_blank" href={splitUrl}><i className="icon-expand icon"/> Split</a></div> : '';

        let menuHtml = <div className="menu">
                <div className="header">Project Menu</div>
                <div className="ui divider"></div>
                <div className="scrolling menu">
                    <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh icon"/> Change Password</a></div>
                        {splitButton}
                    <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a></div>
                    <div className="divider"/>
                    <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a></div>
                    <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a></div>
                        {downloadButton}
                    <div className="divider"/>
                    <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a></div>
                    <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a></div>
                    <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a></div>
                    <div className="item" onClick={this.archiveJob.bind(this)}><a ><i className="icon-drawer icon"/> Archive job</a></div>
                    <div className="item" onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o icon"/> Cancel job</a></div>
                </div>
            </div>;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <div className="menu">
                <div className="header">Project Menu</div>
                <div className="ui divider"></div>
                <div className="scrolling menu">
                    <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh icon"/> Change Password</a></div>
                    {splitButton}
                    <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a></div>
                    <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a></div>
                    <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a></div>

                        {downloadButton}
                    <div className="divider"/>
                    <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a></div>
                    <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a></div>
                    <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a></div>
                    <div className="item" onClick={this.activateJob.bind(this)}><a ><i className="icon-drawer unarchive-project icon"/> Unarchive job</a></div>
                    <div className="item" onClick={this.cancelJob.bind(this)}><a ><i className="icon-trash-o icon"/> Cancel job</a></div>
                </div>
            </div>;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <div className="menu">
                    <div className="header">Project Menu</div>
                    <div className="ui divider"></div>
                    <div className="scrolling menu">
                        <div className="item" onClick={this.changePassword.bind(this)}><a ><i className="icon-refresh icon"/> Change Password</a></div>
                        {splitButton}
                        <div className="item"><a target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a></div>
                        <div className="item"><a target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a></div>
                        <div className="item"><a target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a></div>
                        {downloadButton}
                        <div className="divider"/>
                        <div className="item"><a target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a></div>
                        <div className="item"><a target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a></div>
                        <div className="item"><a target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a></div>
                        <div className="item" onClick={this.activateJob.bind(this)}><a><i className="icon-drawer unarchive-project icon"/> Resume job</a></div>
                    </div>
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
            let keys = JSON.parse(this.props.job.get('private_tm_key'));
            let tooltipText = '';
            keys.forEach(function (key, i) {
                let descript = (key.name) ? key.name : "Private TM and Glossary";
                let item = '<div style="text-align: left"><span style="font-weight: bold">' + descript + '</span> ( ' + key.key + ' )</div>';
                tooltipText =  tooltipText + item;
            });
            return <a className="circular ui icon basic button tm-keys" data-html={tooltipText}
                   onClick={this.openTMPanel.bind(this)}>
                    <i className="icon-tm-matecat icon"/>
                </a>;
        } else {
            return '';
        }
    }

    getCommentsIcon() {
        let icon = '';
        let openThreads = this.props.job.get("open_threads_count");
        if (openThreads > 0) {
            let tooltipText = "";
            if (this.props.job.get("open_threads_count") === 1) {
                tooltipText = 'There is an open thread';
            } else {
                tooltipText = 'There are <span style="font-weight: bold">' + openThreads + '</span> open threads';
            }
            var translatedUrl = this.getTranslateUrl() + '?action=openComments';
            icon = <a className="circular ui icon button comments-tooltip"
                   data-position="top" data-tooltip={tooltipText} href={translatedUrl} target="_blank">
                    <i className="icon-uniE96B icon"/>
                </a>;
        }
        return icon;

    }

    getQRIcon() {
        var icon = '';
        var quality = this.props.job.get('quality_overall');
        if ( quality === "poor" || quality === "fail" ) {
            var url = this.getQAReport();
            var classQuality = (quality === "poor") ? 'orange' : 'red';
            icon = <a className={"circular ui icon basic button " + classQuality}
                   href={url} target="_blank">
                    <i className="icon-qr-matecat icon"/>
                </a>
            ;
        }
        return icon;
    }

    getWarningsIcon() {
        var icon = '';
        var warnings = this.props.job.get('warnings_count');
        if ( warnings > 0 ) {
            var url = this.getTranslateUrl() + '?action=warnings';
            icon = <a className="circular ui icon basic button"
                   href={url} target="_blank">
                    <i className="icon-notice icon"/>
                </a>;
        }
        return icon;
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
            let date = new Date(this.props.lastAction.event_date);
            return <div><span>Modified: </span> <a target="_blank" href={this.props.activityLogUrl}> {date.toDateString()}</a></div>;
        } else {
            return '';
        }
    }

    render () {
        let translateUrl = this.getTranslateUrl();
        let outsourceUrl = this.getOutsourceUrl();

        let analysisUrl = this.getAnalysisUrl();
        let splitUrl = this.getSplitUrl();
        let mergeUrl = this.getMergeUrl();
        let splitMergeButton = this.getSplitOrMergeButton(splitUrl, mergeUrl);
        // let modifyDate = this.getModifyDate();

        let jobMenu = this.getJobMenu(splitUrl, mergeUrl);
        let tmIcon = this.getTMIcon();
        let QRIcon = this.getQRIcon();
        let commentsIcon = this.getCommentsIcon();
        let warningsIcon = this.getWarningsIcon();
        let idJobLabel = ( !this.props.isChunk ) ? this.props.job.get('id') : this.props.job.get('id') + '-' + this.props.index;

        return <div className="chunk sixteen wide column shadow-1">
                    <div className="ui grid">
                        <div className="two wide computer two wide tablet three wide mobile column">
                            <div className="job-id">
                                { idJobLabel }
                            </div>
                        </div>
                        <div className="fourteen wide computer fourteen wide tablet thirteen wide mobile column pad-left-0">
                            <div className="ui mobile reversed stackable grid">
                                <div className="thirteen wide computer only tablet only column">
                                    <div className="ui grid">
                                        <div className="three wide column">
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

                                        <div className="three wide column">
                                            <div className="job-payable">
                                                <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                            </div>
                                        </div>

                                        {/*<div className="ten wide column">
                                            <div className="ui grid one column">
                                                <div className="eight wide column">
                                                    <div className="send-translator"
                                                         onClick={this.openAssignToTranslatorModal.bind(this)}>
                                                        <i className="icon-forward icon"></i>
                                                        <a href="#"><span id="translator-job">Send to translator</span></a>
                                                    </div>
                                                </div>
                                                <div className="eight wide column ">
                                                    <div className="due-to"
                                                         onClick={this.openAssignToTranslatorModal.bind(this)}>
                                                        <i className="icon-calendar icon"></i>
                                                        <a href="#"><span id="due-date">Choose delivery date</span></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>*/}
                                    </div>
                                </div>
                                <div className="three wide right floated right aligned column">
                                    <div className="job-activity-icon">
                                        {QRIcon}
                                        {warningsIcon}
                                        {commentsIcon}
                                        {tmIcon}
                                        <a className="open-translate circular ui icon primary basic button" target="_blank" href={translateUrl}>
                                            <i className="icon-arrow-right2 icon"/>
                                        </a>
                                        <button className="job-menu circular ui icon top right pointing dropdown  basic button"
                                                ref={(dropdown) => this.dropdown = dropdown}>
                                            <i className="icon-more_vert icon"/>
                                            {jobMenu}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="sixteen wide mobile only column pad-top-0">
                            <div className="ui stackable grid">

                                <div className="three wide column pad-top-0 pad-bottom-0">
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

                                <div className="job-payable three wide right aligned column pad-top-0 pad-bottom-0">
                                    <div className="">
                                        <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                    </div>
                                </div>

                                {/*<div className="ten wide column">*/}
                                    {/*<div className="ui grid one column">*/}
                                        {/*<div className="nine wide column">*/}
                                            {/*<div className="send-translator"*/}
                                                 {/*onClick={this.openAssignToTranslatorModal.bind(this)}>*/}
                                                {/*<i className="icon-forward icon"/>*/}
                                                {/*<a href="#"><span id="translator-job">Send to translator</span></a>*/}
                                            {/*</div>*/}
                                        {/*</div>*/}
                                        {/*<div className="seven wide column right aligned">*/}
                                            {/*<div className="due-to"*/}
                                                 {/*onClick={this.openAssignToTranslatorModal.bind(this)}>*/}
                                                {/*<i className="icon-calendar icon"/>*/}
                                                {/*<a href="#"><span id="due-date">Delivery date</span></a>*/}
                                            {/*</div>*/}
                                        {/*</div>*/}
                                    {/*</div>*/}
                                {/*</div>*/}
                            </div>
                        </div>

                    </div>
                </div>;
    }
}
export default JobContainer ;
