import React, {useEffect, useState} from 'react'
import $ from 'jquery'
import CommonUtils from '../../../utils/commonUtils'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import SegmentStore from '../../../stores/SegmentStore'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolActions from '../../../actions/CatToolActions'
import DownloadFileUtils from '../../../utils/downloadFileUtils'
import {
  DROPDOWN_MENU_TRIGGER_MODE,
  DropdownMenu,
} from '../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'
import Download from '../../../../img/icons/Download'

export const DownloadMenu = ({password, jid, isGDriveProject}) => {
  const [downloadTranslationAvailable, setDownloadTranslationAvailable] =
    useState(false)
  const [stats, setStats] = useState()
  const [downloadDisabled, setDownloadDisabled] = useState(false)

  const runDownload = () => {
    const globalWarnings = SegmentStore.getGlobalWarnings()
    let continueDownloadFunction

    if (downloadDisabled) return false

    if (!downloadTranslationAvailable) {
      //Send event
      const data = {
        event: 'download_draft',
      }
      CommonUtils.dispatchAnalyticsEvents(data)
    }

    if (config.isGDriveProject) {
      continueDownloadFunction = continueDownloadWithGoogleDrive
    } else {
      continueDownloadFunction = continueDownload
    }
    const continueDownloadFunctionWithoutErrors = () =>
      continueDownloadFunction({checkErrors: false})

    //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
    if (
      globalWarnings.matecat.ERROR &&
      globalWarnings.matecat.ERROR.total > 0
    ) {
      ModalsActions.showDownloadWarningsModal(
        continueDownloadFunction,
        continueDownloadFunctionWithoutErrors,
        goToFirstError,
      )
    } else {
      continueDownloadFunction()
    }
  }

  const goToFirstError = () => {
    CatToolActions.toggleQaIssues()
    setTimeout(function () {
      $('.button.qa-issue').first().click()
    }, 300)
  }

  const continueDownload = ({checkErrors = true} = {}) => {
    if (downloadDisabled) {
      return
    }

    setDownloadDisabled(true)
    const callback = () => {
      setDownloadDisabled(false)
    }
    DownloadFileUtils.downloadFile(
      config.id_job,
      config.password,
      checkErrors,
      callback,
    )
  }

  const continueDownloadWithGoogleDrive = ({
    checkErrors = true,
    originalFiles,
  } = {}) => {
    if (downloadDisabled) {
      return
    }
    setDownloadDisabled(true)

    const callback = () => {
      setDownloadDisabled(false)
    }

    DownloadFileUtils.downloadGDriveFile(
      originalFiles,
      config.id_job,
      config.password,
      checkErrors,
      callback,
    )
  }

  useEffect(() => {
    if (stats) {
      setDownloadTranslationAvailable(CommonUtils.isJobCompleted(stats))
    }
  }, [stats])

  useEffect(() => {
    const updateStats = (stats) => setStats(stats)
    CatToolStore.addListener(CattolConstants.SET_PROGRESS, updateStats)
    return () => {
      CatToolStore.removeListener(CattolConstants.SET_PROGRESS, updateStats)
    }
  }, [])
  return (
    <DropdownMenu
      triggerMode={DROPDOWN_MENU_TRIGGER_MODE.HOVER}
      toggleButtonProps={{
        children: <Download size={20} />,
        size: BUTTON_SIZE.ICON_STANDARD,
        mode: downloadTranslationAvailable
          ? BUTTON_MODE.BASIC
          : BUTTON_MODE.GHOST,
        type: downloadTranslationAvailable
          ? BUTTON_TYPE.PRIMARY
          : BUTTON_TYPE.ICON,
        onClick: (e) => {
          runDownload()
          e.preventDefault()
        },
      }}
      className={downloadTranslationAvailable ? 'job-completed' : ''}
      disabled={downloadDisabled}
      items={[
        ...(downloadTranslationAvailable
          ? [
              {
                label: (
                  <>
                    {' '}
                    <Download />
                    {isGDriveProject
                      ? 'Open in Google Drive'
                      : 'Download Translation'}
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
                      : 'Download Draft'}
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
                    Download Original
                  </>
                ),
                onClick: () => {
                  window.open(`/api/v2/original/${jid}/${password}`, '_blank')
                },
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
                onClick: () =>
                  continueDownloadWithGoogleDrive({originalFiles: 1}),
              },
            ]),
        {
          label: (
            <>
              <Download />
              Export XLIFF
            </>
          ),
          onClick: () => {
            window.open(`/api/v2/xliff/${jid}/${password}/${jid}.zip`, '_blank')
          },
        },
        {
          label: (
            <>
              <Download />
              Export Job TMX
            </>
          ),
          onClick: () => {
            window.open(`/api/v2/tmx/${jid}/${password}`, '_blank')
          },
        },
      ]}
    />
  )
}
