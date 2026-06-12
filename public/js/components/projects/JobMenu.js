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

const JOB_MENU_ITEM_ID = {
  CHANGE_PASSWORD: 'change_password',
  SPLIT: 'split',
  MERGE: 'merge',
  REVISE: 'revise',
  REVISE2: 'change_password_revise_2',
  QA_REPORT: 'qa_report',
  DOWNLOAD: 'download',
  ORIGINAL: 'original',
  EXPORT_XLIFF: 'export_xliff',
  EXPORT_TMX: 'export_tmx',
  ARCHIVE: 'archive',
  CANCEL: 'cancel',
  UNARCHIVE: 'unarchive',
  RESUME: 'resume',
  DELETE: 'delete',
}

const JOB_CHUNKS_MENU_ITEM_ID = [
  JOB_MENU_ITEM_ID.MERGE,
  JOB_MENU_ITEM_ID.DOWNLOAD,
  JOB_MENU_ITEM_ID.ORIGINAL,
  JOB_MENU_ITEM_ID.EXPORT_XLIFF,
  JOB_MENU_ITEM_ID.EXPORT_TMX,
  JOB_MENU_ITEM_ID.ARCHIVE,
  JOB_MENU_ITEM_ID.CANCEL,
  JOB_MENU_ITEM_ID.UNARCHIVE,
  JOB_MENU_ITEM_ID.RESUME,
  JOB_MENU_ITEM_ID.DELETE,
]

const CHUNK_MENU_ITEM_ID = [
  JOB_MENU_ITEM_ID.CHANGE_PASSWORD,
  JOB_MENU_ITEM_ID.REVISE,
  JOB_MENU_ITEM_ID.REVISE2,
  JOB_MENU_ITEM_ID.QA_REPORT,
]

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
  isJobChunks,
  changePasswordFn,
  openSplitModalFn,
  openMergeModalFn,
  downloadLabel,
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
            id: JOB_MENU_ITEM_ID.REVISE2,
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
            id: JOB_MENU_ITEM_ID.REVISE2,
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
            id: JOB_MENU_ITEM_ID.CHANGE_PASSWORD,
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
            id: JOB_MENU_ITEM_ID.SPLIT,
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
              id: JOB_MENU_ITEM_ID.MERGE,
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
      id: JOB_MENU_ITEM_ID.REVISE,
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
      id: JOB_MENU_ITEM_ID.QA_REPORT,
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
    ...(downloadLabel
      ? [
          {
            id: JOB_MENU_ITEM_ID.DOWNLOAD,
            label: downloadLabel.label,
            onClick: () => {
              downloadLabel.action()
            },
            disabled: disableDownload,
          },
        ]
      : []),
    {
      id: JOB_MENU_ITEM_ID.ORIGINAL,
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
      id: JOB_MENU_ITEM_ID.EXPORT_XLIFF,
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
      id: JOB_MENU_ITEM_ID.EXPORT_TMX,
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
            id: JOB_MENU_ITEM_ID.ARCHIVE,
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
            id: JOB_MENU_ITEM_ID.CANCEL,
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
            id: JOB_MENU_ITEM_ID.UNARCHIVE,
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
            id: JOB_MENU_ITEM_ID.CANCEL,
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
            id: JOB_MENU_ITEM_ID.RESUME,
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
            id: JOB_MENU_ITEM_ID.DELETE,
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
    .filter(({id}) =>
      typeof id === 'string'
        ? !isJobChunks
          ? isChunk
            ? CHUNK_MENU_ITEM_ID.some((value) => value === id)
            : true
          : isJobChunks && isChunk
            ? JOB_CHUNKS_MENU_ITEM_ID.some((value) => value === id)
            : true
        : true,
    )
    .reduce((acc, item) => {
      if (item === 'separator' && acc[acc.length - 1] === 'separator') {
        return acc
      }
      return [...acc, item]
    }, [])
    .filter((item, index, arr) => {
      if (item !== 'separator') return true
      return arr.slice(index + 1).some((i) => i !== 'separator')
    })

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
  jobId: PropTypes.number.isRequired,
  status: PropTypes.string.isRequired,
  qAReportUrl: PropTypes.string,
  jobTMXUrl: PropTypes.string.isRequired,
  exportXliffUrl: PropTypes.string.isRequired,
  originalUrl: PropTypes.string.isRequired,
  reviseUrl: PropTypes.string,
  isChunkOutsourced: PropTypes.bool.isRequired,
  isChunk: PropTypes.bool.isRequired,
  isJobChunks: PropTypes.bool,
  changePasswordFn: PropTypes.func,
  openSplitModalFn: PropTypes.func,
  openMergeModalFn: PropTypes.func.isRequired,
  downloadLabel: PropTypes.object,
  disableDownload: PropTypes.bool.isRequired,
  archiveJobFn: PropTypes.func.isRequired,
  cancelJobFn: PropTypes.func.isRequired,
  activateJobFn: PropTypes.func.isRequired,
  deleteJobFn: PropTypes.func.isRequired,
}

export default JobMenu
