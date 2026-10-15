import React, {useState} from 'react'
import PropTypes from 'prop-types'
import CommonUtils from '../../../utils/commonUtils'
import DownloadFileUtils from '../../../utils/downloadFileUtils'
import {
  DROPDOWN_MENU_TRIGGER_MODE,
  DropdownMenu,
} from '../DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../Button/Button'
import Download from '../../../../img/icons/Download'

/**
 * Download menu for one job or chunk: translation/draft, original, XLIFF and TMX.
 *
 * `triggerProps` overrides the trigger button's children, size, mode, type and
 * any other button prop. `onClick` is always the component's own, so a caller
 * cannot replace the download, and `disabled={true}` on the component always
 * wins over `triggerProps.disabled`, so a disabled menu cannot be re-enabled.
 */
export const JobDownloadMenu = ({
  jid,
  password,
  isGDriveProject,
  isJobCompleted,
  disabled = false,
  checkErrors = true,
  onBeforeDownload,
  triggerProps = {},
}) => {
  const [downloadDisabled, setDownloadDisabled] = useState(false)

  const continueDownload = ({checkErrors: check = checkErrors} = {}) => {
    if (disabled || downloadDisabled) {
      return
    }

    setDownloadDisabled(true)
    const callback = () => {
      setDownloadDisabled(false)
    }
    DownloadFileUtils.downloadFile(jid, password, check, callback)
  }

  const continueDownloadWithGoogleDrive = ({
    checkErrors: check = checkErrors,
    originalFiles,
  } = {}) => {
    if (disabled || downloadDisabled) {
      return
    }
    setDownloadDisabled(true)

    const callback = () => {
      setDownloadDisabled(false)
    }

    DownloadFileUtils.downloadGDriveFile(
      originalFiles,
      jid,
      password,
      check,
      callback,
    )
  }

  const runDownload = () => {
    if (disabled || downloadDisabled) return false

    if (!isJobCompleted) {
      //Send event
      const data = {
        event: 'download_draft',
      }
      CommonUtils.dispatchAnalyticsEvents(data)
    }

    const continueDownloadFunction = isGDriveProject
      ? continueDownloadWithGoogleDrive
      : continueDownload
    const proceed = () => continueDownloadFunction()
    const proceedWithoutErrors = () =>
      continueDownloadFunction({checkErrors: false})

    if (onBeforeDownload) {
      onBeforeDownload(proceed, proceedWithoutErrors)
    } else {
      proceed()
    }
  }

  const openInNewTab = (url) => {
    if (disabled) return
    window.open(url, '_blank')
  }

  const items = [
    ...(isJobCompleted
      ? [
          {
            label: (
              <>
                {' '}
                <Download />
                {isGDriveProject
                  ? 'Open in Google Drive'
                  : 'Download translation'}
              </>
            ),
            onClick: () => runDownload(),
          },
        ]
      : [
          {
            label: (
              <>
                <Download />
                {isGDriveProject
                  ? 'Open preview in Google Drive'
                  : 'Download draft'}
              </>
            ),
            onClick: () => runDownload(),
          },
        ]),
    ...(!isGDriveProject
      ? [
          {
            label: (
              <>
                <Download />
                Download original
              </>
            ),
            onClick: () => openInNewTab(`/api/v2/original/${jid}/${password}`),
          },
        ]
      : [
          {
            label: (
              <>
                <Download />
                Open original in Google Drive
              </>
            ),
            onClick: () => continueDownloadWithGoogleDrive({originalFiles: 1}),
          },
        ]),
    {
      label: (
        <>
          <Download />
          Export XLIFF
        </>
      ),
      onClick: () =>
        openInNewTab(`/api/v2/xliff/${jid}/${password}/${jid}.zip`),
    },
    {
      label: (
        <>
          <Download />
          Export job TMX
        </>
      ),
      onClick: () => openInNewTab(`/api/v2/tmx/${jid}/${password}`),
    },
  ]

  return (
    <DropdownMenu
      triggerMode={DROPDOWN_MENU_TRIGGER_MODE.HOVER}
      toggleButtonProps={{
        children: <Download size={20} />,
        size: BUTTON_SIZE.ICON_STANDARD,
        mode: isJobCompleted ? BUTTON_MODE.BASIC : BUTTON_MODE.GHOST,
        type: isJobCompleted ? BUTTON_TYPE.PRIMARY : BUTTON_TYPE.ICON,
        ...triggerProps,
        onClick: (e) => {
          runDownload()
          e.preventDefault()
        },
        ...(disabled && {disabled: true}),
      }}
      className={isJobCompleted ? 'job-completed' : ''}
      disabled={disabled || downloadDisabled}
      items={
        disabled ? items.map((item) => ({...item, disabled: true})) : items
      }
    />
  )
}

JobDownloadMenu.propTypes = {
  jid: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  password: PropTypes.string.isRequired,
  isGDriveProject: PropTypes.bool,
  isJobCompleted: PropTypes.bool,
  disabled: PropTypes.bool,
  checkErrors: PropTypes.bool,
  onBeforeDownload: PropTypes.func,
  // Overrides the trigger button props; never its onClick or a disabled={true}
  triggerProps: PropTypes.object,
}
