import React, {useEffect, useState} from 'react'
import $ from 'jquery'
import CommonUtils from '../../../utils/commonUtils'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import SegmentStore from '../../../stores/SegmentStore'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolActions from '../../../actions/CatToolActions'
import {JobDownloadMenu} from '../../common/JobDownloadMenu'

export const DownloadMenu = ({password, jid, isGDriveProject}) => {
  const [downloadTranslationAvailable, setDownloadTranslationAvailable] =
    useState(false)
  const [stats, setStats] = useState()

  const goToFirstError = () => {
    CatToolActions.toggleQaIssues()
    setTimeout(function () {
      $('.button.qa-issue').first().click()
    }, 300)
  }

  const checkGlobalWarnings = (proceed, proceedWithoutErrors) => {
    const globalWarnings = SegmentStore.getGlobalWarnings()
    //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
    if (
      globalWarnings.matecat.ERROR &&
      globalWarnings.matecat.ERROR.total > 0
    ) {
      ModalsActions.showDownloadWarningsModal(
        proceed,
        proceedWithoutErrors,
        goToFirstError,
      )
    } else {
      proceed()
    }
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
    <JobDownloadMenu
      jid={jid}
      password={password}
      isGDriveProject={isGDriveProject}
      isJobCompleted={downloadTranslationAvailable}
      onBeforeDownload={checkGlobalWarnings}
    />
  )
}
