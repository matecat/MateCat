class JobContainer extends React.Component {

    constructor (props) {
        super(props);
        this.state = {
            showDownloadProgress: false
        };
        this.getTranslateUrl = this.getTranslateUrl.bind(this);
        this.getAnalysisUrl = this.getAnalysisUrl.bind(this);
        this.changePassword = this.changePassword.bind(this);
        this.downloadTranslation = this.downloadTranslation.bind(this);
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
        return (nextProps.job !== this.props.job ||
        nextProps.lastAction !== this.props.lastAction ||
        nextState.showDownloadProgress !== this.state.showDownloadProgress)
    }

    getTranslateUrl() {
        let use_prefix = ( this.props.jobsLenght > 1 );
        let chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        return '/translate/'+this.props.project.get('project_slug')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+ this.props.job.get('password')  ;
    }

    getReviseUrl() {
        let use_prefix = ( this.props.jobsLenght > 1 );
        let chunk_id = this.props.job.get('id') + ( ( use_prefix ) ? '-' + this.props.index : '' ) ;
        let possibly_different_review_password = ( this.props.job.has('review_password') ?
            this.props.job.get('review_password') :
            this.props.job.get('password')
        );

        return '/revise/'+this.props.project.get('project_slug')+'/'+ this.props.job.get('source') +'-'+this.props.job.get('target')+'/'+ chunk_id +'-'+  possibly_different_review_password ;
    }

    getEditingLogUrl() {
        return '/editlog/' + this.props.job.get('id') + '-' + this.props.job.get('password');

    }

    getQAReport() {
        if (this.props.project.get('features') && this.props.project.get('features').indexOf('review_improved')) {
            return '/plugins/review_improved/quality_report/' + this.props.job.get('id') + '/' + this.props.job.get('password');
        } else {
            return '/revise-summary/' + this.props.job.get('id') + '-' + this.props.job.get('password');
        }
    }

    changePassword() {
        let self = this;
        this.oldPassword = this.props.job.get('password');
        this.props.changeJobPasswordFn(this.props.job.toJS())
            .done(function (data) {
                let notification = {
                    title: 'Change job password',
                    text: 'The password has been changed. <a class="undo-password">Undo</a>',
                    type: 'warning',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                let boxUndo = APP.addNotification(notification);
                ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo);
                setTimeout(function () {
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
        let url = this.getTranslateUrl() + '?action=warnings';
        this.props.downloadTranslationFn(this.props.project.toJS(), this.props.job.toJS(), url);
    }

    disableDownloadMenu(idJob) {
        if (this.props.job.get('id') === idJob) {
            $(this.downloadMenu).addClass('disabled');
            this.setState({
                showDownloadProgress: true
            });
        }
    }

    enableDownloadMenu(idJob) {
        if (this.props.job.get('id') === idJob) {
            $(this.downloadMenu).removeClass('disabled');
            this.setState({
                showDownloadProgress: false
            });
        }
    }

    getDownloadLabel() {
        let jobStatus = this.getTranslationStatus();
        let remoteService = this.props.project.get('remote_file_service');
        let label = <a className="item" onClick={this.downloadTranslation}
                       ref={(downloadMenu) => this.downloadMenu = downloadMenu}><i className="icon-eye icon"></i> Preview</a>;
        if ((jobStatus == 'translated' || jobStatus == 'approved') && (!remoteService)) {
            label = <a className="item" onClick={this.downloadTranslation}
                       ref={(downloadMenu) => this.downloadMenu = downloadMenu}><i className="icon-download icon"/> Download Translation</a>;
        } else if ((jobStatus == 'translated' || jobStatus == 'approved') && (remoteService == 'gdrive')) {
            label = <a className="item" onClick={this.downloadTranslation}
                       ref={(downloadMenu) => this.downloadMenu = downloadMenu}><i className="icon-download icon"/> Open in Google Drive</a>;
        } else if (remoteService && (remoteService == 'gdrive')) {
            label = <a className="item" onClick={this.downloadTranslation}
                       ref={(downloadMenu) => this.downloadMenu = downloadMenu}><i className="icon-eye icon"/> Preview in Google Drive</a>;
        }
        return label
    }


    getJobMenu(splitUrl, mergeUrl) {
        let reviseUrl = this.getReviseUrl();
        let editLogUrl = this.getEditingLogUrl();
        let qaReportUrl = this.getQAReport();
        let jobTMXUrl = '/TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        let exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('project_slug') + '.zip';

        let originalUrl = '/?action=downloadOriginal&id_job=' + this.props.job.get('id') +' &password=' + this.props.job.get('password') + '&download_type=all';


        let downloadButton = this.getDownloadLabel();
        let splitButton = (!this.props.isChunk) ? <a className="item" target="_blank" href={splitUrl}><i className="icon-expand icon"/> Split</a> : <a className="item" target="_blank" href={mergeUrl}><i className="icon-compress icon"/> Merge</a>;

        let menuHtml = <div className="menu">

                {/*<div className="scrolling menu">*/}
                    <a className="item" onClick={this.changePassword.bind(this)}><i className="icon-refresh icon"/> Change Password</a>
                        {splitButton}
                    <a className="item" target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a>
                    <div className="divider"/>
                    <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                    <a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>
                        {downloadButton}
                    <div className="divider"/>
                    <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
                    <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                    <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                    <div className="divider"/>
                    <a className="item" onClick={this.archiveJob.bind(this)}><i className="icon-drawer icon"/> Archive job</a>
                    <a className="item" onClick={this.cancelJob.bind(this)}><i className="icon-trash-o icon"/> Cancel job</a>
                </div>
            /*</div>*/;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <div className="menu">

                {/*<div className="scrolling menu">*/}
                    <a className="item" onClick={this.changePassword.bind(this)}><i className="icon-refresh icon"/> Change Password</a>
                    {splitButton}
                    <a className="item" target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a>
                    <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                    <div className="item" target="_blank" href={editLogUrl}><a><i className="icon-download-logs icon"/> Editing Log</a></div>
                        {downloadButton}
                    <div className="divider"/>
                    <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
                    <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                    <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                    <div className="divider"/>
                    <a className="item" onClick={this.activateJob.bind(this)}><i className="icon-drawer unarchive-project icon"/> Unarchive job</a>
                    <a className="item" onClick={this.cancelJob.bind(this)}><i className="icon-trash-o icon"/> Cancel job</a>
                </div>
            /*</div>*/;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <div className="menu">

                    {/*<div className="scrolling menu">*/}
                        <a className="item" onClick={this.changePassword.bind(this)}>i className="icon-refresh icon"/> Change Password</a>
                        {splitButton}
                        <a className="item" target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a>
                        <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                        <a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>
                        {downloadButton}
                        <div className="divider"/>
                        <a className="item" target="_blank" href={originalUrl}>i className="icon-download icon"/> Download Original</a>
                        <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                        <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                        <div className="divider"/>
                        <a className="item" onClick={this.activateJob.bind(this)}><i className="icon-drawer unarchive-project icon"/> Resume job</a>
                    </div>
                /*</div>*/;
        }
        return menuHtml;
    }

    getAnalysisUrl() {
        return '/jobanalysis/'+this.props.project.get('id')+ '-' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    getProjectAnalyzeUrl() {
        return '/analyze/' + this.props.project.get('project_slug') + '/' +this.props.project.get('id')+ '-' + this.props.project.get('password');
    }

    getSplitUrl() {
        return '/analyze/'+ this.props.project.get('project_slug') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + this.props.job.get('id');
    }

    getMergeUrl() {
        return '/analyze/'+ this.props.project.get('project_slug') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=merge&jobid=' + this.props.job.get('id');
    }

    getActivityLogUrl() {
        return '/activityLog/'+ this.props.project.get('project_slug') +'/'+this.props.project.get('id')+'-' + this.props.project.get('password') + '?open=split&jobid=' + this.props.job.get('id');
    }

    openSettings() {
        ManageActions.openJobSettings(this.props.job.toJS(), this.props.project.get('project_slug'));
    }

    openTMPanel() {
        ManageActions.openJobTMPanel(this.props.job.toJS(), this.props.project.get('project_slug'));
    }

    getTMIcon() {
        if (JSON.parse(this.props.job.get('private_tm_key')).length) {
            let keys = JSON.parse(this.props.job.get('private_tm_key'));
            let tooltipText = '';
            keys.forEach(function (key, i) {
                let descript = (key.name) ? key.name : "Private TM and Glossary";
                let item = '<div style="text-align: left"><span style="font-weight: bold">' + descript + '</span> (' + key.key + ')</div>';
                tooltipText =  tooltipText + item;
            });
            return <a className=" ui icon basic button tm-keys" data-html={tooltipText}
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
            icon = <a className=" ui icon basic button comments-tooltip"
                      data-html={tooltipText} href={translatedUrl} target="_blank">
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
            let tooltipText = "Overall quality: " + quality.toUpperCase();
            var classQuality = (quality === "poor") ? 'yellow' : 'red';
            icon = <a className={"ui icon basic button qr-tooltip " + classQuality}
                      data-html={tooltipText} href={url} target="_blank" data-position="top center">
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
            let tooltipText = "Click to see issues";
            icon = <a className="ui icon basic button warning-tooltip"
                      data-html={tooltipText} href={url} target="_blank" data-position="top center">
                    <i className="icon-notice icon"/>
                </a>;
        }
        return icon;
    }

    getSplitOrMergeButton(splitUrl, mergeUrl) {

        if (this.props.isChunk) {
            return <a className="btn waves-effect merge waves-dark" target="_blank" href={mergeUrl}>
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

    openOutsourceModal() {
        if (this.props.job.get('outsource') && this.props.job.get('outsource').get('outsourced') == "1") {
            window.open(this.props.job.get('outsource').get('link_to_status'), "_blank");
        } else {
            ManageActions.openOutsourceModal(this.props.project, this.props.job, this.getTranslateUrl());
        }
    }

    getOutsourceButton() {
        let label = <a className="ui green button"
                       onClick={this.openOutsourceModal.bind(this)}>
           Outsource
        </a>;
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('outsourced') == "1") {
                label = <a className="ui grey button "
                           onClick={this.openOutsourceModal.bind(this)}>
                    Outsourced
                </a>;
            } else {
                label = <a className="ui green button"
                           onClick={this.openOutsourceModal.bind(this)}>
                    Outsource
                </a>;
            }
        }
        return label;
    }

    getOutsourceJobSent() {
        let outsourceJobLabel = '';
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('outsourced') == "1") {
                outsourceJobLabel = <div className="translated-outsuorced">
                    <a href="http://www.translated.net" target="_blank"><img className='outsource-logo' src="/public/img/logo_translated.png" title="visit our website"/></a>
                </div>
                ;
            }
        }
        return outsourceJobLabel;
    }

    getOutsourceDelivery() {
        let outsourceDelivery = '';
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('outsourced') == "1") {
                outsourceDelivery = <div className="job-delivery">{this.props.job.get('outsource').get('delivery')}</div>
                ;
            }
        }
        return outsourceDelivery;
    }

    componentDidMount () {
        $(this.dropdown).dropdown({
            belowOrigin: true
        });
        $('.button.tm-keys, .button.comments-tooltip, .warning-tooltip, .qr-tooltip, .translate-tooltip').popup();

        ProjectsStore.addListener(ManageConstants.ENABLE_DOWNLOAD_BUTTON, this.enableDownloadMenu.bind(this));
        ProjectsStore.addListener(ManageConstants.DISABLE_DOWNLOAD_BUTTON, this.disableDownloadMenu.bind(this));

        ManageActions.getOutsourceQuote(this.props.project, this.props.job);
    }

    componentWillUnmount() {
        ProjectsStore.removeListener(ManageConstants.ENABLE_DOWNLOAD_BUTTON, this.enableDownloadMenu);
        ProjectsStore.removeListener(ManageConstants.DISABLE_DOWNLOAD_BUTTON, this.disableDownloadMenu);
    }

    render () {
        let translateUrl = this.getTranslateUrl();
        let outsourceButton = this.getOutsourceButton();
        let outsourceJobLabel = this.getOutsourceJobSent();
        let outsourceDelivery = this.getOutsourceDelivery();
        let analysisUrl = this.getProjectAnalyzeUrl();
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
                        <div className="three wide computer two wide tablet three wide mobile column">
                            <div className="source-target">
                                <div className="source-box">
                                    {this.props.job.get('sourceTxt')}
                                </div>
                                <div className="in-to"><i className="icon-chevron-right icon"/></div>
                                <div className="target-box">
                                    {this.props.job.get('targetTxt')}
                                </div>
                            </div>
                        </div>


                        <div className="thirteen wide computer fourteen wide tablet thirteen wide mobile column pad-left-0">
                            <div className="ui mobile reversed stackable grid">
                                <div className="eight wide column">
                                    <div className="ui grid">
                                        <div className="twelve wide computer six wide tablet column">
                                            <div className="job-id">
                                                { idJobLabel }
                                            </div>
                                            <div className="progress-bar">
                                                <div className="progr">
                                                    <div className="meter">
                                                        <a className="warning-bar translate-tooltip" data-html={'Rejected '+this.props.job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('REJECTED_PERC') + '%'}}/>
                                                        <a className="approved-bar translate-tooltip" data-html={'Approved '+this.props.job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('APPROVED_PERC')+ '%' }}/>
                                                        <a className="translated-bar translate-tooltip" data-html={'Translated '+this.props.job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('TRANSLATED_PERC') + '%' }}/>
                                                        <a className="draft-bar translate-tooltip" data-html={'Draft '+this.props.job.get('stats').get('DRAFT_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('DRAFT_PERC') + '%' }}/>

                                                    </div>
                                                </div>
                                            </div>
                                            <div className="tm-job">
                                                {tmIcon}
                                            </div>
                                            <div className="job-activity-icons">
                                                <div className="comments">
                                                    {commentsIcon}
                                                </div>
                                            </div>
                                            <div className="job-payable">
                                                <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                                {/*<span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')} words</span>*/}
                                            </div>
                                            <div className="translated-outsourced">
                                                {outsourceJobLabel}
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div className="eight wide computer five wide tablet right floated right aligned column">
                                        {QRIcon}
                                        {warningsIcon}
                                        {outsourceDelivery}
                                        {outsourceButton}
                                        <a className="open-translate ui primary button open" target="_blank" href={translateUrl}>
                                            Open
                                        </a>
                                        <div className="job-menu circular ui icon top right pointing dropdown  basic button"
                                                ref={(dropdown) => this.dropdown = dropdown}>
                                            <i className="icon-more_vert icon"/>
                                            {jobMenu}
                                        </div>
                                </div>
                            </div>
                        </div>

                        {/*<div className="sixteen wide mobile only column pad-top-0">
                            <div className="ui stackable grid">

                                <div className="three wide column pad-top-0 pad-bottom-0">
                                    <div className="progress-bar">
                                        <div className="progr">
                                            <div className="meter">
                                                <a className="warning-bar translate-tooltip" data-html={'Rejected '+this.props.job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('REJECTED_PERC') + '%'}}/>
                                                <a className="approved-bar translate-tooltip" data-html={'Approved '+this.props.job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('APPROVED_PERC')+ '%' }}/>
                                                <a className="translated-bar translate-tooltip" data-html={'Translated '+this.props.job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('TRANSLATED_PERC') + '%' }}/>
                                                <a className="draft-bar translate-tooltip" data-html={'Draft '+this.props.job.get('stats').get('DRAFT_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('DRAFT_PERC') + '%' }}/>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="job-payable three wide right aligned column pad-top-0 pad-bottom-0">
                                    <div className="pad-bottom-15">
                                        <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                                    </div>
                                </div>

                            </div>
                        </div>*/}

                    </div>
                { this.state.showDownloadProgress ? (
                    <div className="chunk-download-progress"></div>
                ):('')}

        </div>
    }
}
export default JobContainer ;
