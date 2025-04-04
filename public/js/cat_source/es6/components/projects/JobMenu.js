import React from 'react'
import ManageActions from '../../actions/ManageActions'
import {JOB_STATUS} from '../../constants/Constants'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import DotsHorizontal from '../../../../../img/icons/DotsHorizontal'
class JobMenu extends React.Component {
  constructor(props) {
    super(props)
  }

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
        return [
          {
            label: (
              <>
                <i className="icon-edit icon" />
                Revise 2
              </>
            ),
            onClick: () => {
              window.open(url, '_blank')
            },
          },
        ]
      } else {
        return [
          {
            label: (
              <>
                <i className="icon-edit icon" />
                Generate Revise 2
              </>
            ),
            onClick: () => {
              this.retrieveSecondPassReviewLink()
            },
          },
        ]
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

    const items = [
      ...(this.props.status === JOB_STATUS.ACTIVE
        ? [
            {
              label: (
                <>
                  <i className="icon-refresh icon" />
                  Change Password
                </>
              ),
              items: [
                {
                  label: <>Translate</>,
                  onClick: () => {
                    this.props.changePasswordFn()
                  },
                },
                {
                  label: <>Revise</>,
                  onClick: () => {
                    this.props.changePasswordFn(1)
                  },
                },
                ...(this.props.job.has('revise_passwords') &&
                this.props.job.get('revise_passwords').size > 1
                  ? [
                      {
                        label: <>2nd Revise</>,
                        onClick: () => {
                          this.props.changePasswordFn(2)
                        },
                      },
                    ]
                  : []),
              ],
            },
          ]
        : []),
      ...(!this.props.isChunkOutsourced &&
      config.splitEnabled &&
      !this.props.isChunk
        ? [
            {
              label: (
                <>
                  <i className="icon-expand icon" />
                  Split
                </>
              ),
              onClick: () => {
                this.props.openSplitModalFn()
              },
            },
          ]
        : !this.props.isChunkOutsourced &&
            config.splitEnabled &&
            this.props.isChunk
          ? [
              {
                label: (
                  <>
                    <i className="icon-compress icon" />
                    Merge
                  </>
                ),
                onClick: () => {
                  this.props.openMergeModalFn()
                },
              },
            ]
          : []),
      'separator',
      {
        label: (
          <>
            <i className="icon-edit icon" />
            Revise
          </>
        ),
        onClick: () => {
          window.open(this.props.reviseUrl, '_blank')
        },
      },
      ...this.getSecondPassReviewMenuLink(),
      {
        label: (
          <>
            <i className="icon-qr-matecat icon" /> QA Report
          </>
        ),
        onClick: () => {
          window.open(qaReportUrl, '_blank')
        },
      },
      'separator',
      ...(this.props.getDownloadLabel
        ? [
            {
              label: this.props.getDownloadLabel.label,
              onClick: () => {
                this.props.getDownloadLabel.action()
              },
              disabled: this.props.disableDownload,
            },
          ]
        : []),
      {
        label: (
          <>
            <i className="icon-download icon" /> Download Original
          </>
        ),
        onClick: () => {
          window.open(originalUrl, '_blank')
        },
      },
      {
        label: (
          <>
            <i className="icon-download icon" /> Export XLIFF
          </>
        ),
        onClick: () => {
          window.open(exportXliffUrl, '_blank')
        },
      },
      {
        label: (
          <>
            <i className="icon-download icon" /> Export TMX
          </>
        ),
        onClick: () => {
          window.open(jobTMXUrl, '_blank')
        },
      },
      'separator',
      ...(this.props.status === JOB_STATUS.ACTIVE
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer icon" />
                  Archive job
                </>
              ),
              onClick: () => {
                this.props.archiveJobFn()
              },
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel job
                </>
              ),
              onClick: () => {
                this.props.cancelJobFn()
              },
            },
          ]
        : []),
      ...(this.props.status === JOB_STATUS.ARCHIVED
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer unarchive-project icon" />
                  Unarchive job
                </>
              ),
              onClick: () => {
                this.props.activateJobFn()
              },
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Cancel job
                </>
              ),
              onClick: () => {
                this.props.cancelJobFn()
              },
            },
          ]
        : []),
      ...(this.props.status === JOB_STATUS.CANCELLED
        ? [
            {
              label: (
                <>
                  <i className="icon-drawer unarchive-project icon" />
                  Resume job
                </>
              ),
              onClick: () => {
                this.props.activateJobFn()
              },
            },
            {
              label: (
                <>
                  <i className="icon-trash-o icon" />
                  Delete job permanently
                </>
              ),
              onClick: () => {
                this.props.deleteJobFn()
              },
            },
          ]
        : []),
    ]
    return (
      <DropdownMenu
        className="job-menu"
        items={items}
        toggleButtonProps={{
          children: <DotsHorizontal size={18} />,
          testId: 'job-menu-button',
        }}
        align={DROPDOWN_MENU_ALIGN.RIGHT}
      />
    )
  }
}

export default JobMenu
