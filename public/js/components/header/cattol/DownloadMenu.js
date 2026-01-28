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
        mode: BUTTON_MODE.GHOST,
        type: BUTTON_TYPE.ICON,
        onClick: (e) => {
          console.log('clicked')
          e.preventDefault()
        },
      }}
      items={[
        {
          key: 'download',
          label: 'Download',
        },
        {key: 'download-translation', label: 'Pippo'},
      ]}
    />
    // <div
    //   className={`action-submenu ui simple pointing top center floating dropdown ${
    //     downloadTranslationAvailable ? 'job-completed' : ''
    //   } ${downloadDisabled ? 'disabled' : ''}`}
    //   id="action-download"
    //   title="Download"
    // >
    //   <div
    //     className="dropdown-menu-overlay"
    //     onClick={() => runDownload()}
    //   ></div>
    //   <ul className="menu" id="previewDropdown">
    //     {downloadTranslationAvailable ? (
    //       <li className="item downloadTranslation" data-value="translation">
    //         <a
    //           title="Translation"
    //           alt="Translation"
    //           onClick={() => runDownload()}
    //         >
    //           {isGDriveProject
    //             ? 'Open in Google Drive'
    //             : 'Download Translation'}
    //         </a>
    //       </li>
    //     ) : (
    //       <li className="item previewLink" data-value="draft">
    //         <a title="Draft" alt="Draft" href="#" onClick={() => runDownload()}>
    //           {isGDriveProject ? 'Preview in Google Drive' : 'Draft'}
    //         </a>
    //       </li>
    //     )}
    //     {!isGDriveProject && (
    //       <li className="item" data-value="original">
    //         <a
    //           className="originalDownload"
    //           title="Original"
    //           alt="Original"
    //           href={`/api/v2/original/${jid}/${password}`}
    //           target="_blank"
    //         >
    //           Original
    //         </a>
    //       </li>
    //     )}
    //     {isGDriveProject && (
    //       <li className="item">
    //         <a
    //           className="originalsGDrive"
    //           title="Original in Google Drive"
    //           alt="Original in Google Drive"
    //           href="javascript:void(0)"
    //           onClick={() =>
    //             continueDownloadWithGoogleDrive({originalFiles: 1})
    //           }
    //         >
    //           Original in Google Drive
    //         </a>
    //       </li>
    //     )}
    //
    //     <li className="item" data-value="xlif">
    //       <a
    //         className="sdlxliff"
    //         title="Export XLIFF"
    //         alt="Export XLIFF"
    //         href={`/api/v2/xliff/${jid}/${password}/${jid}.zip`}
    //         target="_blank"
    //       >
    //         Export XLIFF
    //       </a>
    //     </li>
    //
    //     <li className="item" data-value="tmx">
    //       <a
    //         rel="noreferrer"
    //         className="tmx"
    //         title="Export job TMX for QA"
    //         alt="Export job TMX for QA"
    //         href={`/api/v2/tmx/${jid}/${password}`}
    //         target="_blank"
    //       >
    //         Export Job TMX
    //       </a>
    //     </li>
    //   </ul>
    // </div>
  )
}
