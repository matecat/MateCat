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
import Trash from '../../../img/icons/Trash'
import Split from '../../../img/icons/Split'
import Merge from '../../../img/icons/Merge'
import Download from '../../../img/icons/Download'
import QR from '../../../img/icons/QR'
import Revise from '../../../img/icons/Revise'
import {BUTTON_SIZE} from '../common/Button/Button'
import FlipBackward from '../icons/FlipBackward'
import PropTypes from 'prop-types'

const JobMenu = ({
  job,
  project,
  jobId,
  status,
  qAReportUrl,
  jobTMXUrl,
  exportXliffUrl,
  originalUrl,
  reviseUrl,
  isChunkOutsourced,
  isChunk,
  changePasswordFn,
  openSplitModalFn,
  openMergeModalFn,
  getDownloadLabel,
  disableDownload,
  archiveJobFn,
  cancelJobFn,
  activateJobFn,
  deleteJobFn,
}) => {
  const openSecondPassUrl = () => {
    if (job.has('revise_passwords') && job.get('revise_passwords').size > 1) {
      const url =
        config.hostpath +
        '/revise2/' +
        project.get('name') +
        '/' +
        job.get('source') +
        '-' +
        job.get('target') +
        '/' +
        jobId +
        '-' +
        job.get('revise_passwords').get(1).get('password')
      window.open(url)
    }
  }

  const retrieveSecondPassReviewLink = () => {
    ManageActions.getSecondPassReview(
      project.get('id'),
      project.get('password'),
      jobId,
      job.get('password'),
    ).then(() => {
      openSecondPassUrl()
    })
  }

  const getSecondPassReviewMenuLink = () => {
    if (
      project.has('features') &&
      project.get('features').indexOf('second_pass_review') > -1
    ) {
      if (job.has('revise_passwords') && job.get('revise_passwords').size > 1) {
        const url =
          '/revise2/' +
          project.get('name') +
          '/' +
          job.get('source') +
          '-' +
          job.get('target') +
          '/' +
          jobId +
          '-' +
          job.get('revise_passwords').get(1).get('password')
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
              retrieveSecondPassReviewLink()
            },
          },
        ]
      }
    }
    return ''
  }

  const items = [
    ...(status === JOB_STATUS.ACTIVE
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
                  changePasswordFn()
                },
              },
              {
                label: <>Revise</>,
                onClick: () => {
                  changePasswordFn(1)
                },
              },
              ...(job.has('revise_passwords') &&
              job.get('revise_passwords').size > 1
                ? [
                    {
                      label: <>Revise 2</>,
                      onClick: () => {
                        changePasswordFn(2)
                      },
                    },
                  ]
                : []),
            ],
          },
        ]
      : []),
    ...(!isChunkOutsourced && config.splitEnabled && !isChunk
      ? [
          {
            label: (
              <>
                <Split size={18} />
                Split
              </>
            ),
            onClick: () => {
              openSplitModalFn()
            },
          },
        ]
      : !isChunkOutsourced && config.splitEnabled && isChunk
        ? [
            {
              label: (
                <>
                  <Merge size={18} />
                  Merge
                </>
              ),
              onClick: () => {
                openMergeModalFn()
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
        window.open(reviseUrl, '_blank')
      },
    },
    ...getSecondPassReviewMenuLink(),
    {
      label: (
        <>
          <QR /> Quality report
        </>
      ),
      onClick: () => {
        window.open(qAReportUrl, '_blank')
      },
    },
    'separator',
    ...(getDownloadLabel
      ? [
          {
            label: getDownloadLabel.label,
            onClick: () => {
              getDownloadLabel.action()
            },
            disabled: disableDownload,
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
          <Download size={18} /> Export job TMX
        </>
      ),
      onClick: () => {
        window.open(jobTMXUrl, '_blank')
      },
    },
    'separator',
    ...(status === JOB_STATUS.ACTIVE
      ? [
          {
            label: (
              <>
                <Archive size={18} />
                Archive job
              </>
            ),
            onClick: () => {
              archiveJobFn()
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
              cancelJobFn()
            },
          },
        ]
      : []),
    ...(status === JOB_STATUS.ARCHIVED
      ? [
          {
            label: (
              <>
                <FlipBackward size={18} />
                Unarchive job
              </>
            ),
            onClick: () => {
              activateJobFn()
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
              cancelJobFn()
            },
          },
        ]
      : []),
    ...(status === JOB_STATUS.CANCELLED
      ? [
          {
            label: (
              <>
                <FlipBackward size={18} />
                Resume job
              </>
            ),
            onClick: () => {
              activateJobFn()
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
              deleteJobFn()
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
        children: <DotsHorizontal size={16} />,
        testId: 'job-menu-button',
        size: BUTTON_SIZE.ICON_SMALL,
      }}
      align={DROPDOWN_MENU_ALIGN.RIGHT}
    />
  )
}

JobMenu.propTypes = {
  job: PropTypes.object.isRequired,
  project: PropTypes.object.isRequired,
  jobId: PropTypes.string.isRequired,
  status: PropTypes.string.isRequired,
  qAReportUrl: PropTypes.string.isRequired,
  jobTMXUrl: PropTypes.string.isRequired,
  exportXliffUrl: PropTypes.string.isRequired,
  originalUrl: PropTypes.string.isRequired,
  reviseUrl: PropTypes.string.isRequired,
  isChunkOutsourced: PropTypes.bool.isRequired,
  isChunk: PropTypes.bool.isRequired,
  changePasswordFn: PropTypes.func.isRequired,
  openSplitModalFn: PropTypes.func.isRequired,
  openMergeModalFn: PropTypes.func.isRequired,
  getDownloadLabel: PropTypes.func,
  disableDownload: PropTypes.bool.isRequired,
  archiveJobFn: PropTypes.func.isRequired,
  cancelJobFn: PropTypes.func.isRequired,
  activateJobFn: PropTypes.func.isRequired,
  deleteJobFn: PropTypes.func.isRequired,
}

export default JobMenu
