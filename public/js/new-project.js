import Cookies from 'js-cookie'
import {createRoot} from 'react-dom/client'
import React from 'react'

import ModalsActions from './cat_source/es6/actions/ModalsActions'
import {clearNotCompletedUploads as clearNotCompletedUploadsApi} from './cat_source/es6/api/clearNotCompletedUploads'
import {projectCreationStatus} from './cat_source/es6/api/projectCreationStatus'
import AlertModal from './cat_source/es6/components/modals/AlertModal'
import NotificationBox from './cat_source/es6/components/notificationsComponent/NotificationBox'
import NewProject from './cat_source/es6/pages/NewProject'
import CreateProjectActions from './cat_source/es6/actions/CreateProjectActions'
import CommonUtils from './cat_source/es6/utils/commonUtils'

/**
 * ajax call to clear the uploaded files when an user refresh the home page
 * called in main.js
 */
window.clearNotCompletedUploads = function () {
  clearNotCompletedUploadsApi()
}

APP.getFilenameFromUploadedFiles = function () {
  var files = ''
  $(
    '.upload-table tr:not(.failed) td.name, .gdrive-upload-table tr:not(.failed) td.name',
  ).each(function () {
    files += '@@SEP@@' + $(this).text()
  })
  return files.substr(7)
}

let UPLOAD_PAGE = {}

$.extend(UPLOAD_PAGE, {
  init: function () {
    this.addEvents()
  },

  restartConversions: function () {
    if ($('.template-download').length) {
      if (UI.conversionsAreToRestart()) {
        UI.restartConversions()
      }
    } else if ($('.template-gdrive').length) {
      APP.restartGDriveConversions()
    }
  },

  addEvents: function () {
    //Error upload (??)
    $('.upload-table').on('click', 'a.skip_link', function () {
      var fname = decodeURIComponent($(this).attr('id').replace('skip_', ''))

      UI.skipLangDetectArr[fname] = 'skip'

      var parentTd_label = $(this).parent('.label')

      $(parentTd_label).fadeOut(200, function () {
        $(this).remove()
      })
      $(parentTd_label).parent().removeClass('error')
    })
  },
})

APP.restartConversion = function () {
  UPLOAD_PAGE.restartConversions()
}

APP.checkGDriveEvents = function () {
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
APP.handleCreationStatus = function (id_project, password) {
  projectCreationStatus(id_project, password)
    .then(({data, status}) => {
      if (data.status == 202 || status == 202) {
        setTimeout(APP.handleCreationStatus, 1000, id_project, password)
      } else {
        APP.postProjectCreation(data)
      }
    })
    .catch(({errors}) => {
      APP.postProjectCreation({errors})
    })
}

APP.postProjectCreation = function (d) {
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
        case -17:
          $.each(d.lang_detect, function (fileName, status) {
            if (status == 'detect') {
              UI.addInlineMessage(
                fileName,
                'Different source language. <a class="skip_link" id="skip_' +
                  fileName +
                  '">Ignore</a>',
              )
            }
          })
          break
      }

      //normal error management
      CreateProjectActions.showError(this.message)
    })
  } else {
    //reset the clearNotCompletedUploads event that should be called in main.js onbeforeunload
    //--> we don't want to delete the files on the upload directory
    clearNotCompletedUploads = function () {}
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
      const data = {
        event: 'analyze_click',
        userStatus: APP.USER.isUserLogged() ? 'loggedUser' : 'notLoggedUser',
        userId:
          APP.USER.isUserLogged() && APP.USER.STORE.user
            ? APP.USER.STORE.user.uid
            : null,
        idProject: d.id_project,
      }
      CommonUtils.dispatchAnalyticsEvents(data)
      location.href = d.analyze_url
    }
  }
}

$(document).ready(function () {
  UPLOAD_PAGE.init()
  //TODO: REMOVE
  let currentTargetLangs = localStorage.getItem('currentTargetLang')
  let currentSourceLangs = localStorage.getItem('currentSourceLang')
  if (!currentSourceLangs) {
    currentSourceLangs = 'en-US'
  }
  if (!currentTargetLangs) {
    currentTargetLangs = 'fr-FR'
  }
  const domMountPoint = document.getElementsByClassName('new_project__page')[0]
  if (domMountPoint) {
    const newProjectPage =
      document.getElementsByClassName('new_project__page')[0]
    const rootNewProjectPage = createRoot(newProjectPage)
    rootNewProjectPage.render(
      <NewProject
        isLoggedIn={!!config.isLoggedIn}
        sourceLanguageSelected={currentSourceLangs}
        targetLanguagesSelected={currentTargetLangs}
        subjectsArray={config.subject_array.map((item) => {
          return {...item, id: item.key, name: item.display}
        })}
        conversionEnabled={!!config.conversionEnabled}
        formatsNumber={config.formats_number}
        googleDriveEnabled={!!config.googleDriveEnabled}
        restartConversions={UPLOAD_PAGE.restartConversions}
      />,
    )

    const mountPoint = document.getElementsByClassName(
      'notifications-wrapper',
    )[0]
    const root = createRoot(mountPoint)
    root.render(<NotificationBox />)
  }
})
