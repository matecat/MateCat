import Cookies from 'js-cookie'
import CatToolActions from '../actions/CatToolActions'
import CommonUtils from './commonUtils'
import ModalsActions from '../actions/ModalsActions'
import ConfirmMessageModal from '../components/modals/ConfirmMessageModal'
import {downloadFileGDrive} from '../api/downloadFileGDrive'
import {downloadFile} from '../api/downloadFile'

const DownloadFileUtils = {
  downloadFile: function (idJob, pass, checkErrors, callback) {
    downloadFile({idJob, password: pass, checkErrors})
      .then(() => callback())
      .catch((e) => {
        const notification = {
          title: 'Error',
          text: 'Download failed. Please try again. If the error persists, contact support.',
          type: 'error',
        }
        CatToolActions.addNotification(notification)
        callback()
      })
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
