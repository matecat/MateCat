import React from 'react'
import Cookies from 'js-cookie'
import CatToolActions from '../actions/CatToolActions'
import CommonUtils from './commonUtils'
import ModalsActions from '../actions/ModalsActions'
import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import {downloadFileGDrive} from '../api/downloadFileGDrive'
import {downloadFile} from '../api/downloadFile'
import {downloadFromUrl} from '../api/downloadFromUrl'
import {
  createDownloadProgressStore,
  DownloadProgress,
} from '../components/common/DownloadProgress/DownloadProgress'

// Next to the download menus, which sit on the right of the header and of each job row
const NOTIFICATION_POSITION = 'tr'

// Progress events arrive for every chunk read: repaint at most this often
const PROGRESS_REPAINT_MS = 200

let pendingDownloads = 0

// Closing the page aborts the request and the file is lost
const warnBeforeLeaving = (event) => {
  event.preventDefault()
  event.returnValue = ''
}

const trackPendingDownload = () => {
  if (pendingDownloads++ === 0) {
    window.addEventListener('beforeunload', warnBeforeLeaving)
  }
  return () => {
    if (--pendingDownloads === 0) {
      window.removeEventListener('beforeunload', warnBeforeLeaving)
    }
  }
}

// Notification uids of the downloads still running, so a second click on the
// same item does not start the same download twice
const activeDownloads = new Set()

/**
 * Runs a download behind a notification that stays open until the file is
 * saved, shows how much has arrived and lets the user cancel it.
 *
 * @param {string} uid identifies the download: a second one with the same uid is ignored
 * @param {function({onProgress: function, signal: AbortSignal}): Promise} start
 * @param {function} [callback] called once the download has settled
 */
const runDownload = (uid, start, callback) => {
  if (activeDownloads.has(uid)) return
  activeDownloads.add(uid)

  const controller = new AbortController()
  const progress = createDownloadProgressStore()
  const untrack = trackPendingDownload()
  let lastRepaint = 0

  CatToolActions.addNotification({
    uid,
    title: 'Download in progress',
    text: (
      <DownloadProgress store={progress} onCancel={() => controller.abort()} />
    ),
    type: 'info',
    position: NOTIFICATION_POSITION,
    autoDismiss: false,
    dismissable: false,
  })

  const onProgress = ({loaded, total}) => {
    const now = Date.now()
    if (loaded > 0 && now - lastRepaint < PROGRESS_REPAINT_MS) return
    lastRepaint = now
    progress.update({receiving: true, loaded, total})
  }

  start({onProgress, signal: controller.signal})
    .then(() => {
      CatToolActions.addNotification({
        title: 'Download complete',
        text: 'Your files have been saved.',
        type: 'success',
        position: NOTIFICATION_POSITION,
        timer: 4000,
      })
    })
    .catch(() => {
      if (controller.signal.aborted) return
      CatToolActions.addNotification({
        title: 'Error',
        text: 'Download failed. Please try again. If the error persists, contact support.',
        type: 'error',
        position: NOTIFICATION_POSITION,
      })
    })
    .finally(() => {
      activeDownloads.delete(uid)
      untrack()
      CatToolActions.removeNotification({uid})
      callback?.()
    })
}

const DownloadFileUtils = {
  /**
   * Downloads the translated files of a job
   */
  downloadFile: function (idJob, pass, checkErrors, callback) {
    runDownload(
      `download-job-${idJob}`,
      ({onProgress, signal}) =>
        downloadFile({idJob, password: pass, checkErrors, onProgress, signal}),
      callback,
    )
  },

  /**
   * Downloads a file served as an attachment, such as the original files or the
   * XLIFF and TMX exports, without opening a tab
   */
  downloadUrl: function (url) {
    runDownload(`download-${url}`, ({onProgress, signal}) =>
      downloadFromUrl({url, onProgress, signal}),
    )
  },

  showDownloadErrorMessage: function () {
    const notification = {
      title: 'Error',
      text:
        'Download failed. Please, fix any tag issues and try again in 5 minutes. If it still fails, please, contact ' +
        config.support_mail,
      type: 'error',
    }
    CatToolActions.addNotification(notification)
  },

  downloadGDriveFile: function (
    openOriginalFiles,
    jobId,
    pass,
    checkErrors,
    callback,
  ) {
    if (typeof openOriginalFiles === 'undefined') {
      openOriginalFiles = 0
    }

    if (typeof window.googleDriveWindows == 'undefined') {
      window.googleDriveWindows = {}
    }
    let windowReference
    if (CommonUtils.isSafari) {
      windowReference = window.open()
    }
    const driveUpdateDone = function (data) {
      if (!data.urls || data.urls.length === 0) {
        const props = {
          text:
            'Matecat was not able to update project files on Google Drive. Maybe the project owner revoked privileges to access those files. Ask the project owner to login again and' +
            ' grant Google Drive privileges to Matecat.',
          successText: 'Ok',
          successCallback: function () {
            ModalsActions.onCloseModal()
          },
        }
        ModalsActions.showModalComponent(
          ConfirmMessageModal,
          props,
          'Download fail',
        )
        return
      }

      let winName

      data.urls.forEach((item, index) => {
        winName = 'window' + item.localId
        if (CommonUtils.isSafari && windowReference) {
          windowReference.location = item.alternateLink
        } else if (
          window.googleDriveWindows[winName] &&
          !window.googleDriveWindows[winName].closed &&
          window.googleDriveWindows[winName].location != null
        ) {
          window.googleDriveWindows[winName].location.href = item.alternateLink
          window.googleDriveWindows[winName].focus()
        } else {
          window.googleDriveWindows[winName] = window.open(item.alternateLink)
        }
      })
    }
    const downloadToken =
      new Date().getTime() + '_' + parseInt(Math.random(0, 1) * 10000000)

    downloadFileGDrive(
      openOriginalFiles,
      jobId,
      pass,
      checkErrors,
      downloadToken,
    )
      .then((data) => {
        driveUpdateDone(data)
        if (callback) {
          callback()
        }
      })
      .catch(() => {
        if (callback) {
          callback()
        }
        const cookie = Cookies.get(downloadToken)
        if (cookie) {
          this.showDownloadErrorMessage()
          const props = {
            text: cookie.message,
            successText: 'Ok',
            successCallback: function () {
              ModalsActions.onCloseModal()
            },
          }
          ModalsActions.showModalComponent(
            ConfirmMessageModal,
            props,
            'Download fail',
          )
          Cookies.remove(downloadToken)
        }
      })
  },
}

export default DownloadFileUtils
