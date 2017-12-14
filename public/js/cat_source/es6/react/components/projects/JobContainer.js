
let OutsourceContainer = require('../outsource/OutsourceContainer').default;

class JobContainer extends React.Component {

    constructor (props) {
        super(props);
        this.state = {
            showDownloadProgress: false,
            openOutsource: false,
            showTranslatorBox: false,
            extendedView: true
        };
        this.getTranslateUrl = this.getTranslateUrl.bind(this);
        this.getAnalysisUrl = this.getAnalysisUrl.bind(this);
        this.changePassword = this.changePassword.bind(this);
        this.removeTranslator = this.removeTranslator.bind(this);
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
        if (this.props.project.get('features') && this.props.project.get('features').indexOf('review_improved') > -1) {
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
                let translator = self.props.job.get('translator');
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
                                ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo, translator);
                            });

                    })
                }, 500);

            });
    }

    removeTranslator() {
        let self = this;
        this.oldPassword = this.props.job.get('password');
        this.props.changeJobPasswordFn(this.props.job.toJS())
            .done(function (data) {
                let notification = {
                    title: 'Job unassigned',
                    text: 'The translator has been removed and the password changed. <a class="undo-password">Undo</a>',
                    type: 'warning',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                let boxUndo = APP.addNotification(notification);
                let translator = self.props.job.get('translator');
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
                                ManageActions.changeJobPassword(self.props.project, self.props.job, data.password, data.undo, translator);
                            });

                    })
                }, 500);

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

    openSplitModal() {
        ModalsActions.openSplitJobModal(this.props.job, this.props.project, UI.reloadProjects);
    }

    openMergeModal() {
        ModalsActions.openMergeModal(this.props.project.toJS(), this.props.job.toJS(), UI.reloadProjects);
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


    getJobMenu() {
        let reviseUrl = this.getReviseUrl();
        let editLogUrl = this.getEditingLogUrl();
        let qaReportUrl = this.getQAReport();
        let jobTMXUrl = '/TMX/'+ this.props.job.get('id') + '/' + this.props.job.get('password');
        let exportXliffUrl = '/SDLXLIFF/'+ this.props.job.get('id') + '/' + this.props.job.get('password') +
            '/' + this.props.project.get('project_slug') + '.zip';

        let originalUrl = '/?action=downloadOriginal&id_job=' + this.props.job.get('id') +' &password=' + this.props.job.get('password') + '&download_type=all';


        let downloadButton = this.getDownloadLabel();
        let splitButton;
        if (!this.props.isChunkOutsourced) {
             splitButton = (!this.props.isChunk) ?
                <a className="item" target="_blank" onClick={this.openSplitModal.bind(this)}><i className="icon-expand icon"/> Split</a> :
                <a className="item" target="_blank" onClick={this.openMergeModal.bind(this)}><i className="icon-compress icon"/> Merge</a>;
        }
        let menuHtml = <div className="menu">

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
                </div>;
        if ( this.props.job.get('status') === 'archived' ) {
            menuHtml = <div className="menu">
                        {splitButton}
                    <a className="item" target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a>
                    <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                    <a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>
                        {downloadButton}
                    <div className="divider"/>
                    <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
                    <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                    <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                    <div className="divider"/>
                    <a className="item" onClick={this.activateJob.bind(this)}><i className="icon-drawer unarchive-project icon"/> Unarchive job</a>
                    <a className="item" onClick={this.cancelJob.bind(this)}><i className="icon-trash-o icon"/> Cancel job</a>
                </div>;
        } else if ( this.props.job.get('status') === 'cancelled' ) {
            menuHtml = <div className="menu">
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
                    </div>;
        }
        return menuHtml;
    }

    getAnalysisUrl() {
        return '/jobanalysis/'+this.props.project.get('id')+ '-' + this.props.job.get('id') + '-' + this.props.job.get('password');
    }

    getProjectAnalyzeUrl() {
        return '/analyze/' + this.props.project.get('project_slug') + '/' +this.props.project.get('id')+ '-' + this.props.project.get('password');
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
        if (this.props.job.get('private_tm_key').size) {
            let keys = this.props.job.get('private_tm_key');
            let tooltipText = '';
            keys.forEach(function (key, i) {
                let descript = (key.get('name')) ? key.get('name') : "Private TM and Glossary";
                let item = '<div style="text-align: left"><span style="font-weight: bold">' + descript + '</span> (' + key.get('key') + ')</div>';
                tooltipText =  tooltipText + item;
            });
            return  <a className=" ui icon basic button tm-keys" data-html={tooltipText} data-variation="tiny"
                       ref={(tooltip) => this.tmTooltip = tooltip}
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
            icon = <div className="comments-icon-container activity-icon-single"><a className=" ui icon basic button comments-tooltip"
                      data-html={tooltipText} href={translatedUrl} data-variation="tiny" target="_blank"
                        ref={(tooltip) => this.commentsTooltip = tooltip}>
                    <i className="icon-uniE96B icon"/>
            </a></div>;
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
            icon = <div className="qreport-icon-container activity-icon-single"><a className="ui icon basic button qr-tooltip "
                      data-html={tooltipText} href={url} target="_blank" data-position="top center" data-variation="tiny"
                        ref={(tooltip) => this.activityTooltip = tooltip}>
                    <i className={"icon-qr-matecat icon " + classQuality }/>
            </a></div>
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
            icon = <div className="warnings-icon-container activity-icon-single">
                <a className="ui icon basic button warning-tooltip"
                   data-html={tooltipText} href={url} target="_blank" data-position="top center" data-variation="tiny"
                   ref={(tooltip) => this.warningTooltip = tooltip}>
                <i className="icon-notice icon red"/>
            </a></div>;
        }
        return icon;
    }


    getWarningsMenuItem() {
        var icon = '';
        var warnings = this.props.job.get('warnings_count');
        if ( warnings > 0 ) {
            var url = this.getTranslateUrl() + '?action=warnings';
            let tooltipText = "Click to see issues";
            icon =<a className="ui icon basic button "
                      href={url} target="_blank" data-position="top center">
                    <i className="icon-notice icon red"/>
                    {tooltipText}
            </a>;
        }
        return icon;
    }

    getCommentsMenuItem() {
        let icon = '';
        let openThreads = this.props.job.get("open_threads_count");
        if (openThreads > 0) {
            var translatedUrl = this.getTranslateUrl() + '?action=openComments';
            if (this.props.job.get("open_threads_count") === 1) {
                icon = <a className=" ui icon basic button "
                          href={translatedUrl} target="_blank" >
                    <i className="icon-uniE96B icon" />
                    There is an open thread
                </a>;
            } else {
                icon = <a className=" ui icon basic button "
                          href={translatedUrl} target="_blank" >
                    <i className="icon-uniE96B icon" />
                    There are <span style={{fontWeight: 'bold'}}>{openThreads}</span> open threads
                </a>;
            }
        }
        return icon;

    }

    getQRMenuItem() {
        var icon = '';
        var quality = this.props.job.get('quality_overall');
        if ( quality === "poor" || quality === "fail" ) {
            var url = this.getQAReport();
            let tooltipText = "Overall quality: " + quality.toUpperCase();
            var classQuality = (quality === "poor") ? 'yellow' : 'red';
            icon = <a className="ui icon basic button"
                      href={url} target="_blank" data-position="top center">
                <i className={"icon-qr-matecat icon " + classQuality}/>
                {tooltipText}
            </a>
            ;
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

    openOutsourceModal(showTranslatorBox, extendedView) {
        this.setState({
            openOutsource: !this.state.openOutsource,
            showTranslatorBox: showTranslatorBox,
            extendedView: extendedView
        });
    }

    getOutsourceButton() {
        if (!config.enable_outsource) {
            return null;
        }
        let label = <a className="open-outsource ui green button"
                       onClick={this.openOutsourceModal.bind(this, false, true)}>
           Outsource
        </a>;
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('id_vendor') == "1") {
                label = <a className="open-outsourced ui button "
                           onClick={this.openOutsourceModal.bind(this, false, true)}>
                    View status
                </a>;
            } else {
                label = <a className="open-outsource ui green button"
                           onClick={this.openOutsourceModal.bind(this, false, true)}>
                    Outsource
                </a>;
            }
        }
        return label;
    }

    getOutsourceJobSent() {
        let outsourceJobLabel = '';
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('id_vendor') == "1") {
                outsourceJobLabel =
                    <a className="outsource-logo-box" href={this.props.job.get('outsource').get('quote_review_link')} target="_blank"><img className='outsource-logo' src="/public/img/logo_translated.png" title="Outsourced to translated.net" alt="Translated logo"/></a>;
            }
        } else if (this.props.job.get('translator')) {
            let email = this.props.job.get('translator').get('email');

            outsourceJobLabel = <div className="job-to-translator" data-variation="tiny"
                                     ref={(tooltip) => this.emailTooltip = tooltip}
                                     onClick={this.openOutsourceModal.bind(this, true, false)}>
                                    {email}
                                </div>;
        } else {
            outsourceJobLabel = <div className="job-to-translator not-assigned" data-variation="tiny">
                <a href="javascript:void(0)" onClick={this.openOutsourceModal.bind(this, true, false)}>Assign job to translator</a>
            </div>;
        }
        return outsourceJobLabel;
    }

    getOutsourceDelivery() {
        let outsourceDelivery = '';

        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('id_vendor') == "1") {
                let gmtDate = APP.getGMTDate(this.props.job.get('outsource').get('delivery_timestamp') * 1000);
                outsourceDelivery = <div className="job-delivery" title="Delivery date">
                    <div className="outsource-day-text">{gmtDate.day}</div>
                    <div className="outsource-month-text">{gmtDate.month}</div>
                    <div className="outsource-time-text">{gmtDate.time}</div>
                    <div className="outsource-gmt-text"> ({gmtDate.gmt})</div>
                </div>;
            }
        } else if (this.props.job.get('translator')) {
            let gmtDate = APP.getGMTDate(this.props.job.get('translator').get('delivery_timestamp') * 1000);
            outsourceDelivery = <div className="job-delivery" title="Delivery date">
                <div className="outsource-day-text">{gmtDate.day}</div>
                <div className="outsource-month-text">{gmtDate.month}</div>
                <div className="outsource-time-text">{gmtDate.time}</div>
                <div className="outsource-gmt-text"> ({gmtDate.gmt})</div>
            </div>;
        }

        return outsourceDelivery;
    }

    getOutsourceDeliveryPrice() {
        let outsourceDeliveryPrice = '';
        if (this.props.job.get('outsource')) {
            if (this.props.job.get('outsource').get('id_vendor') == "1") {
                let price  = this.props.job.get('outsource').get('price');
                outsourceDeliveryPrice = <div className="job-price"><span className="valuation">{this.props.job.get('outsource').get('currency')} </span><span className="price">{price}</span></div>;
            }
        }
        return outsourceDeliveryPrice;
    }

    getWarningsInfo() {
        let n = {
            number: 0,
            icon: ''
        };
        let quality = this.props.job.get('quality_overall');
        if (quality && quality === "poor" || quality === "fail") {
            n.number++;
            n.icon = this.getQRIcon();
        }
        if (this.props.job.get("open_threads_count") && this.props.job.get("open_threads_count") > 0) {
            n.number++;
            n.icon =  this.getCommentsIcon();
        }
        if (this.props.job.get('warnings_count') && this.props.job.get('warnings_count') > 0) {
            n.number++;
            n.icon = this.getWarningsIcon();
        }
        return n;
    }

    getWarningsGroup() {
        let icons = this.getWarningsInfo();
        if (icons.number > 1 ) {
            let QRIcon = this.getQRMenuItem();
            let commentsIcon = this.getCommentsMenuItem();
            let warningsIcon = this.getWarningsMenuItem();

            return <div className="job-activity-icons">
                <div className="ui icon top right pointing dropdown group-activity-icon basic button"
                     ref={(button) => this.iconsButton = button}>
                    <i className="icon-alarm icon"/>
                    <div className="menu group-activity-icons transition hidden">
                        <div className="item">
                            {QRIcon}
                        </div>
                        <div className="item">
                            {warningsIcon}
                        </div>
                        <div className="item">
                            {commentsIcon}
                        </div>
                    </div>
                </div>
            </div>;
        } else {
            return <div className="job-activity-icons">{icons.icon}</div>;
        }
    }

    shouldComponentUpdate(nextProps, nextState){
        if (!nextProps.job.equals(this.props.job) || nextState.showDownloadProgress !== this.state.showDownloadProgress
            || nextState.openOutsource !== this.state.openOutsource) {
            this.updated = true;
        }
        return (!nextProps.job.equals(this.props.job) ||
        nextProps.lastAction !== this.props.lastAction ||
        nextState.showDownloadProgress !== this.state.showDownloadProgress ||
        nextState.openOutsource !== this.state.openOutsource)
    }

    componentDidUpdate(prevProps, prevState) {
        var self = this;
        $(this.iconsButton).dropdown();
        this.initTooltips();
        console.log("Updated Job : " + this.props.job.get('id'));
        if (this.updated) {
            this.container.classList.add('updated-job');
            setTimeout(function () {
                self.container.classList.remove('updated-job');
                $(self.dropdown).dropdown({
                    belowOrigin: true
                });
            }, 500);
            self.updated = false;
        }
        if (prevState.openOutsource && this.chunkRow) {
            setTimeout(function () {
                $('.after-open-outsource').removeClass('after-open-outsource');
                self.chunkRow.classList.add('after-open-outsource');
            }, 400);
        }
    }

    componentDidMount () {
        $(this.dropdown).dropdown({
            belowOrigin: true
        });
        this.initTooltips();
        $(this.iconsButton).dropdown();
        ProjectsStore.addListener(ManageConstants.ENABLE_DOWNLOAD_BUTTON, this.enableDownloadMenu.bind(this));
        ProjectsStore.addListener(ManageConstants.DISABLE_DOWNLOAD_BUTTON, this.disableDownloadMenu.bind(this));
    }

    initTooltips() {
        $(this.rejectedTooltip).popup();
        $(this.approvedTooltip).popup();
        $(this.translatedTooltip).popup();
        $(this.draftTooltip).popup();
        $(this.activityTooltip).popup();
        $(this.commentsTooltip).popup();
        $(this.tmTooltip).popup({hoverable: true});
        $(this.warningTooltip).popup();
        $(this.languageTooltip).popup();
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
        // let outsourceDeliveryPrice = this.getOutsourceDeliveryPrice();
        let analysisUrl = this.getProjectAnalyzeUrl();
        let warningIcons = this.getWarningsGroup();
        let jobMenu = this.getJobMenu();
        let tmIcon = this.getTMIcon();
        let outsourceClass = this.props.job.get('outsource') ? ('outsource') : ('translator');

        let outsourceContainerClass = (!config.enable_outsource) ? ('no-outsource') : ((this.state.showTranslatorBox) ? 'showTranslator' : 'showOutsource');


        let idJobLabel = ( !this.props.isChunk ) ? this.props.job.get('id') : this.props.job.get('id') + '-' + this.props.index;

        return <div className="sixteen wide column chunk-container">
                <div className="ui grid" ref={(container) => this.container = container}>
                    {!this.state.openOutsource ? (
                    <div className="chunk wide column shadow-1 pad-right-10" ref={(chunkRow)=> this.chunkRow = chunkRow}>
                        <div className="job-id" title="Job Id">
                            ID: {idJobLabel}
                        </div>
                        <div className="source-target languages-tooltip"
                             ref={(tooltip) => this.languageTooltip = tooltip}
                             data-html={this.props.job.get('sourceTxt') + ' > ' + this.props.job.get('targetTxt')} data-variation="tiny">
                            <div className="source-box">
                                {this.props.job.get('sourceTxt')}
                            </div>
                            <div className="in-to"><i className="icon-chevron-right icon"/></div>
                            <div className="target-box">
                                {this.props.job.get('targetTxt')}
                            </div>
                        </div>
                        <div className="progress-bar">
                            <div className="progr">
                                <div className="meter">
                                    <a className="warning-bar translate-tooltip" data-variation="tiny" data-html={'Rejected '+this.props.job.get('stats').get('REJECTED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('REJECTED_PERC') + '%'}}
                                       ref={(tooltip) => this.rejectedTooltip = tooltip}/>
                                    <a className="approved-bar translate-tooltip" data-variation="tiny" data-html={'Approved '+this.props.job.get('stats').get('APPROVED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('APPROVED_PERC')+ '%' }}
                                       ref={(tooltip) => this.approvedTooltip = tooltip}/>
                                    <a className="translated-bar translate-tooltip" data-variation="tiny" data-html={'Translated '+this.props.job.get('stats').get('TRANSLATED_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('TRANSLATED_PERC') + '%' }}
                                       ref={(tooltip) => this.translatedTooltip = tooltip}/>
                                    <a className="draft-bar translate-tooltip" data-variation="tiny" data-html={'Draft '+this.props.job.get('stats').get('DRAFT_PERC_FORMATTED') +'%'} style={{width: this.props.job.get('stats').get('DRAFT_PERC') + '%' }}
                                       ref={(tooltip) => this.draftTooltip = tooltip}/>
                                </div>
                            </div>
                        </div>
                        <div className="job-payable">
                            <a href={analysisUrl} target="_blank"><span id="words">{this.props.job.get('stats').get('TOTAL_FORMATTED')}</span> words</a>
                        </div>
                        <div className="tm-job">
                            {tmIcon}
                        </div>
                        {warningIcons}
                        <div className="ui icon top right pointing dropdown job-menu  button" title="Job menu"
                                ref={(dropdown) => this.dropdown = dropdown}>
                            <i className="icon-more_vert icon"/>
                            {jobMenu}
                        </div>
                        <a className="open-translate ui primary button open" target="_blank" href={translateUrl}>
                            Open

                        </a>
                            {outsourceButton}
                        <div className="outsource-job">
                            <div className={"translated-outsourced " + outsourceClass}>
                                {outsourceJobLabel}
                                {outsourceDelivery}
                                {/*{outsourceDeliveryPrice}*/}
                                {this.props.job.get('translator') ? (
                                    <div className="item" onClick={this.removeTranslator}>
                                        <div className="ui cancel label"><i className="icon-cancel3"/></div>
                                    </div>
                                ) :('') }


                            </div>
                        </div>


                    { this.state.showDownloadProgress ? (
                        <div className="chunk-download-progress"/>
                    ):('')}
                    </div> ) :(null)}
            </div>
            <OutsourceContainer project={this.props.project}
                                job={this.props.job}
                                url={this.getTranslateUrl()}
                                showTranslatorBox={this.state.showTranslatorBox}
                                extendedView={this.state.extendedView}
                                onClickOutside={this.openOutsourceModal.bind(this)}
                                openOutsource={this.state.openOutsource}
                                idJobLabel={ idJobLabel }/>
        </div>
    }
}
export default JobContainer ;
