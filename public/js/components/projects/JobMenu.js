import React from 'react'
import ManageActions from '../../actions/ManageActions'
import {JOB_STATUS} from '../../constants/Constants'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import DotsHorizontal from '../../../img/icons/DotsHorizontal'
import ChangePassword from '../../../img/icons/ChangePassword'
import Archive from '../../../img/icons/Archive'
import Refresh from '../../../img/icons/Refresh'
import Trash from '../../../img/icons/Trash'
import Split from '../../../img/icons/Split'
import Merge from '../../../img/icons/Merge'
import Download from '../../../img/icons/Download'
import QR from '../../../img/icons/QR'
import Revise from '../../../img/icons/Revise'
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
                <Revise size={18} />
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
                <Revise size={18} />
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
                  <ChangePassword size={18} />
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
                  <Split size={18} />
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
                    <Merge size={18} />
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
            <Revise size={18} />
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
            <QR /> QA Report
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
            <Download size={18} /> Original
          </>
        ),
        onClick: () => {
          window.open(originalUrl, '_blank')
        },
      },
      {
        label: (
          <>
            <Download size={18} /> Export XLIFF
          </>
        ),
        onClick: () => {
          window.open(exportXliffUrl, '_blank')
        },
      },
      {
        label: (
          <>
            <Download size={18} /> Export TMX
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
                  <Archive size={18} />
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
                  <Trash size={18} />
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
                  <Refresh size={18} />
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
                  <Trash size={18} />
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
                  <Refresh size={18} />
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
                  <Trash size={18} />
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
