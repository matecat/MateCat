
class JobMenu extends React.Component {

    constructor(props) {
        super(props);
    }

    openSplitModal() {
        ModalsActions.openSplitJobModal(this.props.job, this.props.project, UI.reloadProjects);
    }

    openMergeModal() {
        ModalsActions.openMergeModal(this.props.project.toJS(), this.props.job.toJS(), UI.reloadProjects);
    }

    getMoreLinks() {

    }

    openSecondPassUrl() {
        if ( this.props.job.has('revise_passwords') && this.props.job.get('revise_passwords').size > 1) {
            let url = config.hostpath + '/revise2/' + this.props.project.get('name') + '/'+ this.props.job.get('source') +'-'+ this.props.job.get('target') +'/'+ this.props.jobId +'-'+ this.props.job.get('revise_passwords').get(1).get('password');
            window.open(url);
        }
    }

    getReviseMenuLink() {
        let reviseUrl = this.props.reviseUrl;
        return <a className="item" target="_blank" href={reviseUrl}><i className="icon-edit icon"/> Revise</a>
    }

    getSecondPassReviewMenuLink() {
        if (this.props.project.has('features') && this.props.project.get('features').indexOf('second_pass_review') > -1 ){
            if ( this.props.job.has('revise_passwords') && this.props.job.get('revise_passwords').size > 1 ) {
                let url = config.hostpath + '/revise2/' + this.props.project.get('name') + '/'+ this.props.job.get('source') +'-'+ this.props.job.get('target') +'/'+ this.props.jobId +'-'+ this.props.job.get('revise_passwords').get(1).get('password');
                return <a className="item" target="_blank" href={url}><i className="icon-edit icon"/>Revise 2</a>
            } else {
                return <a className="item" target="_blank" onClick={()=>this.retrieveSecondPassReviewLink()}><i className="icon-edit icon"/>Generate Revise 2</a>
            }
        }
        return '';
    }

    retrieveSecondPassReviewLink(event) {
        // event.preventDefault();
        ManageActions.getSecondPassReview(this.props.project.get('id'), this.props.project.get('password'), this.props.jobId, this.props.job.get('password')).then(()=>{
            this.openSecondPassUrl();
        });
    }

    componentDidMount() {
        $(this.dropdown).dropdown({
            belowOrigin: true,
        });
    }

    render() {

        let editLogUrl = this.props.editingLogUrl;
        let qaReportUrl = this.props.qAReportUrl;
        let jobTMXUrl = this.props.jobTMXUrl;
        let exportXliffUrl = this.props.exportXliffUrl;

        let originalUrl = this.props.originalUrl;


        let downloadButton = this.props.getDownloadLabel;
        let splitButton;
        if (!this.props.isChunkOutsourced && config.splitEnabled) {
            splitButton = (!this.props.isChunk) ?
                <a className="item" target="_blank" onClick={this.props.openSplitModalFn}><i className="icon-expand icon"/> Split</a> :
                <a className="item" target="_blank" onClick={this.props.openMergeModalFn}><i className="icon-compress icon"/> Merge</a>;
        }
        let menuHtml = <div className="menu">

            <div className="item submenu">
                <div
                className="ui dropdown"
                title="Job menu" ref={(dropdown) => (this.dropdown = dropdown)}>
                    <i className="icon-refresh icon"/> <a>Change Password</a>
                    <i className="dropdown icon"/>
                <div className="menu">
                    <a className={"item"} onClick={()=>this.props.changePasswordFn()}>Translate</a>
                    <a className={"item"} onClick={()=>this.props.changePasswordFn(1)}>Revise</a>
                    {/*If second pass enabled*/}
                    {this.props.job.has('revise_passwords') && this.props.job.get('revise_passwords').size > 1 ? (
                        <a className={"item"} onClick={()=>this.props.changePasswordFn(2)}>2nd Revise</a>
                    ) : null}

                </div>
            </div>
            </div>
            {splitButton}
            {this.getReviseMenuLink()}
            {this.getSecondPassReviewMenuLink()}
            {this.getMoreLinks()}
            <div className="divider"/>
            <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
            {/*<a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>*/}
            {downloadButton}
            <div className="divider"/>
            <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
            <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
            <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
            <div className="divider"/>
            <a className="item" onClick={this.props.archiveJobFn}><i className="icon-drawer icon"/> Archive job</a>
            <a className="item" onClick={this.props.cancelJobFn}><i className="icon-trash-o icon"/> Cancel job</a>
        </div>;
        if ( this.props.status === 'archived' ) {
            menuHtml = <div className="menu">
                {splitButton}
                {this.getReviseMenuLink()}
                {this.getMoreLinks()}
                <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                {/*<a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>*/}
                {downloadButton}
                <div className="divider"/>
                <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
                <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                <div className="divider"/>
                <a className="item" onClick={this.props.activateJobFn}><i className="icon-drawer unarchive-project icon"/> Unarchive job</a>
                <a className="item" onClick={this.props.cancelJobFn}><i className="icon-trash-o icon"/> Cancel job</a>
            </div>;
        } else if ( this.props.status === 'cancelled' ) {
            menuHtml = <div className="menu">
                {splitButton}
                {this.getReviseMenuLink()}
                {this.getMoreLinks()}
                <a className="item" target="_blank" href={qaReportUrl}><i className="icon-qr-matecat icon"/> QA Report</a>
                {/*<a className="item" target="_blank" href={editLogUrl}><i className="icon-download-logs icon"/> Editing Log</a>*/}
                {downloadButton}
                <div className="divider"/>
                <a className="item" target="_blank" href={originalUrl}><i className="icon-download icon"/> Download Original</a>
                <a className="item" target="_blank" href={exportXliffUrl}><i className="icon-download icon"/> Export XLIFF</a>
                <a className="item" target="_blank" href={jobTMXUrl}><i className="icon-download icon"/> Export TMX</a>
                <div className="divider"/>
                <a className="item" onClick={this.props.activateJobFn}><i className="icon-drawer unarchive-project icon"/> Resume job</a>
            </div>;
        }
        return menuHtml;
    }
}


export default JobMenu ;
