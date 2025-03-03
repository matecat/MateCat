import React from 'react'
import ManageActions from '../../actions/ManageActions'
class JobMenu extends React.Component {
  constructor(props) {
    super(props)
  }

  getMoreLinks() {}

  openSecondPassUrl() {
    if (
      this.props.job.has('revise_passwords') &&
      this.props.job.get('revise_passwords').size > 1
    ) {
      let url =
        config.hostpath +
        '/revise2/' +
        this.props.project.get('name') +
        '/' +
        this.props.job.get('source') +
        '-' +
        this.props.job.get('target') +
        '/' +
        this.props.jobId +
        '-' +
        this.props.job.get('revise_passwords').get(1).get('password')
      window.open(url)
    }
  }

  getReviseMenuLink() {
    let reviseUrl = this.props.reviseUrl
    return (
      <div
        className={'item'}
        onClick={() => window.open(reviseUrl, '_blank')}
        data-testid="revise-item"
      >
        <i className="icon-edit icon" />
        Revise
      </div>
    )
  }

  getSecondPassReviewMenuLink() {
    if (
      this.props.project.has('features') &&
      this.props.project.get('features').indexOf('second_pass_review') > -1
    ) {
      if (
        this.props.job.has('revise_passwords') &&
        this.props.job.get('revise_passwords').size > 1
      ) {
        let url =
          '/revise2/' +
          this.props.project.get('name') +
          '/' +
          this.props.job.get('source') +
          '-' +
          this.props.job.get('target') +
          '/' +
          this.props.jobId +
          '-' +
          this.props.job.get('revise_passwords').get(1).get('password')
        return (
          <div className="item" onClick={() => window.open(url, '_blank')}>
            <i className="icon-edit icon" />
            Revise 2
          </div>
        )
      } else {
        return (
          <div
            className="item"
            onClick={() => this.retrieveSecondPassReviewLink()}
          >
            <i className="icon-edit icon" />
            Generate Revise 2
          </div>
        )
      }
    }
    return ''
  }

  retrieveSecondPassReviewLink() {
    // event.preventDefault();
    ManageActions.getSecondPassReview(
      this.props.project.get('id'),
      this.props.project.get('password'),
      this.props.jobId,
      this.props.job.get('password'),
    ).then(() => {
      this.openSecondPassUrl()
    })
  }

  componentDidMount() {
    $(this.dropdown).dropdown()
  }

  render() {
    let qaReportUrl = this.props.qAReportUrl
    let jobTMXUrl = this.props.jobTMXUrl
    let exportXliffUrl = this.props.exportXliffUrl

    let originalUrl = this.props.originalUrl

    let downloadButton = this.props.getDownloadLabel
    let splitButton
    if (!this.props.isChunkOutsourced && config.splitEnabled) {
      splitButton = !this.props.isChunk ? (
        <div className="item" onClick={this.props.openSplitModalFn}>
          <i className="icon-expand icon" />
          Split
        </div>
      ) : (
        <div className="item" onClick={this.props.openMergeModalFn}>
          <i className="icon-compress icon" />
          Merge
        </div>
      )
    }
    let menuHtml = (
      <div
        className="menu"
        ref={(dropdown) => (this.dropdown = dropdown)}
        title="Job menu"
      >
        <div className="item submenu">
          <i className="icon-refresh icon" /> <a>Change Password</a>
          <i className="dropdown icon" />
          <div className="menu" data-testid="change-password-submenu">
            <div
              className={'item'}
              onClick={() => this.props.changePasswordFn()}
            >
              Translate
            </div>
            <div
              className={'item'}
              onClick={() => this.props.changePasswordFn(1)}
            >
              Revise
            </div>
            {/*If second pass enabled*/}
            {this.props.job.has('revise_passwords') &&
            this.props.job.get('revise_passwords').size > 1 ? (
              <div
                className={'item'}
                onClick={() => this.props.changePasswordFn(2)}
              >
                2nd Revise
              </div>
            ) : null}
          </div>
        </div>
        {splitButton}
        {this.getReviseMenuLink()}
        {this.getSecondPassReviewMenuLink()}
        {this.getMoreLinks()}
        <div className="divider" />
        <div
          className="item"
          onClick={() => window.open(qaReportUrl, '_blank')}
        >
          <i className="icon-qr-matecat icon" /> QA Report
        </div>
        {downloadButton}
        <div className="divider" />
        <div
          className="item"
          onClick={() => window.open(originalUrl, '_blank')}
        >
          <i className="icon-download icon" /> Download Original
        </div>
        <div
          className="item"
          onClick={() => window.open(exportXliffUrl, '_blank')}
        >
          <i className="icon-download icon" /> Export XLIFF
        </div>
        <div className="item" onClick={() => window.open(jobTMXUrl, '_blank')}>
          <i className="icon-download icon" /> Export TMX
        </div>
        <div className="divider" />
        <div className="item" onClick={this.props.archiveJobFn}>
          <i className="icon-drawer icon" /> Archive job
        </div>
        <div className="item" onClick={this.props.cancelJobFn}>
          <i className="icon-trash-o icon" /> Cancel job
        </div>
      </div>
    )
    if (this.props.status === 'archived') {
      menuHtml = (
        <div className="menu">
          {splitButton}
          {this.getReviseMenuLink()}
          {this.getMoreLinks()}
          <div
            className="item"
            onClick={() => window.open(qaReportUrl, '_blank')}
          >
            <i className="icon-qr-matecat icon" /> QA Report
          </div>
          {downloadButton}
          <div className="divider" />
          <div
            className="item"
            onClick={() => window.open(originalUrl, '_blank')}
          >
            <i className="icon-download icon" /> Download Original
          </div>
          <div
            className="item"
            onClick={() => window.open(exportXliffUrl, '_blank')}
          >
            <i className="icon-download icon" /> Export XLIFF
          </div>
          <div
            className="item"
            onClick={() => window.open(jobTMXUrl, '_blank')}
          >
            <i className="icon-download icon" /> Export TMX
          </div>
          <div className="divider" />
          <div className="item" onClick={this.props.activateJobFn}>
            <i className="icon-drawer unarchive-project icon" /> Unarchive job
          </div>
          <div className="item" onClick={this.props.cancelJobFn}>
            <i className="icon-trash-o icon" /> Cancel job
          </div>
        </div>
      )
    } else if (this.props.status === 'cancelled') {
      menuHtml = (
        <div className="menu">
          {splitButton}
          {this.getReviseMenuLink()}
          {this.getMoreLinks()}
          <div
            className="item"
            onClick={() => window.open(qaReportUrl, '_blank')}
          >
            <i className="icon-qr-matecat icon" /> QA Report
          </div>
          {downloadButton}
          <div className="divider" />
          <div
            className="item"
            onClick={() => window.open(originalUrl, '_blank')}
          >
            <i className="icon-download icon" /> Download Original
          </div>
          <div
            className="item"
            onClick={() => window.open(exportXliffUrl, '_blank')}
          >
            <i className="icon-download icon" /> Export XLIFF
          </div>
          <div
            className="item"
            onClick={() => window.open(jobTMXUrl, '_blank')}
          >
            <i className="icon-download icon" /> Export TMX
          </div>
          <div className="divider" />
          <div className="item" onClick={this.props.activateJobFn}>
            <i className="icon-drawer unarchive-project icon" /> Resume job
          </div>
          <div className="item" onClick={this.props.deleteJobFn}>
            <i className="icon-drawer delete-project icon" /> Delete job
            permanently
          </div>
        </div>
      )
    }
    return menuHtml
  }
}

export default JobMenu
