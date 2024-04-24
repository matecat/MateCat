import Cookies from 'js-cookie'
import CreateProjectActions from '../actions/CreateProjectActions'
import {clearNotCompletedUploads as clearNotCompletedUploadsApi} from '../api/clearNotCompletedUploads'
import {projectCreationStatus} from '../api/projectCreationStatus'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import CommonUtils from './commonUtils'
import UserStore from '../stores/UserStore'

// called inside upload_main.js file
window.clearNotCompletedUploads = function () {
  clearNotCompletedUploadsApi()
}

export const restartConversions = () => {
  if (document.getElementsByClassName('template-download').length) {
    if (UI.conversionsAreToRestart()) {
      UI.restartConversions()
    }
  } else if (document.getElementsByClassName('template-gdrive').length) {
    APP.restartGDriveConversions()
  }
}

export const checkGDriveEvents = () => {
  const cookieFilesGdrive = 'gdrive_files_to_be_listed'
  const cookieGdriveResponse = 'gdrive_files_outcome'
  const cookie = Cookies.get(cookieGdriveResponse)
  if (cookie) {
    const gdriveResponse = JSON.parse(Cookies.get(cookieGdriveResponse))
    if (gdriveResponse.success) {
      APP.tryListGDriveFiles()
    } else if (gdriveResponse.error_msg) {
      CreateProjectActions.showError(gdriveResponse.error_msg)
    }
    Cookies.remove(cookieFilesGdrive, {
      path: '',
      domain: '.' + location.hostname,
    })
    Cookies.remove(cookieGdriveResponse, {
      path: '',
      domain: '.' + location.hostname,
    })
  }
}

export const getFilenameFromUploadedFiles = () => {
  const excludeFailed = (element) =>
    !Array.from(element.classList).some((value) => value === 'failed')
  const getFilename = (element) =>
    element.getElementsByClassName('name')[0].innerText

  const tableElements = Array.from(
    document
      .getElementsByClassName('upload-table')[0]
      .getElementsByClassName('template-download'),
  )
  const tableElementsGDrive = Array.from(
    document
      .getElementsByClassName('gdrive-upload-table')[0]
      .getElementsByClassName('template-gdrive'),
  )

  const filesList = [
    ...tableElements.filter(excludeFailed).map(getFilename),
    ...tableElementsGDrive.filter(excludeFailed).map(getFilename),
  ]

  return filesList.length > 1
    ? filesList.reduce((acc, cur) => `${acc}@@SEP@@${cur}`, '')
    : filesList
}

export const handleCreationStatus = (id_project, password) => {
  projectCreationStatus(id_project, password)
    .then(({data, status}) => {
      if (data.status == 202 || status == 202) {
        setTimeout(handleCreationStatus, 1000, id_project, password)
      } else {
        postProjectCreation(data)
      }
    })
    .catch(({errors}) => {
      postProjectCreation({errors})
    })
}

const postProjectCreation = (d) => {
  if (typeof d.lang_detect !== 'undefined') {
    UI.skipLangDetectArr = d.lang_detect
  }

  if (UI.skipLangDetectArr != null) {
    $.each(UI.skipLangDetectArr, function (file, status) {
      if (status == 'ok') UI.skipLangDetectArr[file] = 'skip'
      else UI.skipLangDetectArr[file] = 'detect'
    })
  }

  if (typeof d.errors != 'undefined' && d.errors.length) {
    CreateProjectActions.hideErrors()

    $.each(d.errors, function () {
      switch (this.code) {
        //no useful memories found in TMX
        case -16:
          UI.addTMXLangFailure()
          break
        case -14:
          UI.addInlineMessage('.tmx', this.message)
          break
        //no text to translate found.
        case -1:
          var fileName = this.message
            .replace('No text to translate in the file ', '')
            .replace(/.$/g, '')

          console.log(fileName)
          UI.addInlineMessage(
            fileName,
            'Is this a scanned file or image?<br/>Try converting to DOCX using an OCR software ' +
              '(ABBYY FineReader or Nuance PDF Converter)',
          )
          break
      }

      //normal error management
      CreateProjectActions.showError(this.message)
    })
  } else {
    //reset the clearNotCompletedUploads event that should be called in main.js onbeforeunload
    //--> we don't want to delete the files on the upload directory
    window.clearNotCompletedUploads = function () {}
    //this should not be.
    //A project now are never EMPTY, it is not created anymore
    if (d.status == 'EMPTY') {
      console.log('EMPTY')
      $('body').removeClass('creating')
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'No text to translate in the file(s).<br />Perhaps it is a scanned file or an image?',
          buttonText: 'Continue',
        },
        'No text to translate',
      )
    } else {
      const userInfo = UserStore.getUser()

      const data = {
        event: 'analyze_click',
        userStatus: 'loggedUser',
        userId: userInfo.user.uid,
        idProject: d.id_project,
      }
      CommonUtils.dispatchAnalyticsEvents(data)
      location.href = d.analyze_url
    }
  }
}
