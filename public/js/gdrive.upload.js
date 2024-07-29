import React from 'react'
import {getGoogleDriveUploadedFiles} from './cat_source/es6/api/getGoogleDriveUploadedFiles'
import {changeGDriveSourceLang} from './cat_source/es6/api/changeGDriveSourceLang'
import {deleteGDriveUploadedFile} from './cat_source/es6/api/deleteGdriveUploadedFile'
import {openGDriveFiles} from './cat_source/es6/api/openGDriveFiles'
import CreateProjectStore from './cat_source/es6/stores/CreateProjectStore'
import CreateProjectActions from './cat_source/es6/actions/CreateProjectActions'

APP.tryListGDriveFiles = function () {
  getGoogleDriveUploadedFiles()
    .then((listFiles) => {
      $('.files-gdrive').html('')

      if (listFiles && listFiles.hasOwnProperty('files')) {
        APP.displayGDriveFiles()

        $.each(listFiles.files, function (index, file) {
          var iconClass = ''

          if (file.fileExtension == 'docx') {
            iconClass = 'extgdoc'
          } else if (file.fileExtension == 'pptx') {
            iconClass = 'extgsli'
          } else if (file.fileExtension == 'xlsx') {
            iconClass = 'extgsheet'
          }

          $('<tr/>', {
            class: 'template-gdrive fade ready',
            style: 'display: table-row;',
          })
            .append(
              $('<td/>', {
                class: 'preview',
              }).append(
                $('<span/>', {
                  class: iconClass,
                }),
              ),
            )
            .append(
              $('<td/>', {
                class: 'name',
                text: file.fileName,
              }),
            )
            .append(
              $('<td/>', {
                class: 'size',
              }).append(
                $('<span/>', {
                  text: APP.formatBytes(file.fileSize),
                }),
              ),
            )
            .append(
              $('<td/>', {
                class: 'delete',
              }).append(
                $('<button/>', {
                  class:
                    'btn btn-dange ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary',
                  'data-fileid': file.fileId,
                  role: 'button',
                  'aria-disabled': 'false',
                  click: function () {
                    APP.deleteGDriveFile($(this).data('fileid'))
                  },
                })
                  .append(
                    $('<span/>', {
                      class: 'ui-button-icon-primary ui-icon ui-icon-trash',
                    }),
                  )
                  .append(
                    $('<span/>', {
                      class: 'ui-button-text',
                    })
                      .append(
                        $('<i/>', {
                          class: 'icon-ban-circle icon-white',
                        }),
                      )
                      .append(
                        $('<span/>', {
                          text: 'Delete',
                        }),
                      ),
                  ),
              ),
            )
            .appendTo('.files-gdrive')
        })
      } else {
        APP.hideGDriveFiles()
      }
    })
    .catch((error) => {
      if (error.code === 400) {
        const message = <span>{error.msg}</span>
        CreateProjectActions.showError(message)
      }
    })
}

APP.restartGDriveConversions = function () {
  var sourceLang = CreateProjectStore.getSourceLang()
  changeGDriveSourceLang(sourceLang).then((response) => {
    if (response.success) {
      console.log('Source language changed.')
    }
  })
}

APP.deleteGDriveFile = function (fileId) {
  deleteGDriveUploadedFile(fileId).then((response) => {
    if (response.success) {
      APP.tryListGDriveFiles()
    }
  })
}

APP.formatBytes = function (bytes, decimals) {
  if (bytes === 0) return '0 Byte'
  var k = 1024
  var dm = decimals + 1 || 2
  var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
  var i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

APP.addGDriveFile = function (exportIds) {
  var jsonDoc = {
    exportIds: exportIds,
    action: 'open',
  }

  var encodedJson = encodeURIComponent(JSON.stringify(jsonDoc))
  var html =
    '<div class="modal-gdrive">' +
    ' <div class="ui active inverted dimmer">' +
    '<div class="ui massive text loader">Uploading Files</div>' +
    '</div>' +
    '</div>'
  $(html).appendTo($('body'))
  openGDriveFiles(
    encodedJson,
    CreateProjectStore.getSourceLang(),
    CreateProjectStore.getTargetLangs(),
  ).then((response) => {
    $('.modal-gdrive').remove()
    CreateProjectActions.hideErrors()
    if (response.success) {
      APP.tryListGDriveFiles()
    } else {
      var message =
        'There was an error retrieving the file from Google Drive. Try again and if the error persists contact the Support.'
      if (response.error_class === 'Google\\Service\\Exception') {
        message =
          'There was an error retrieving the file from Google Drive: ' +
          response.error_msg
      }
      if (response.error_code === 404) {
        message = (
          <span>
            File retrieval error. To find out how to translate the desired file,
            please{' '}
            <a
              href="https://guides.matecat.com/google-drive-files-upload-issues"
              target="_blank"
              rel="noreferrer"
            >
              read this guide
            </a>
            .
          </span>
        )
      }

      CreateProjectActions.showError(message)

      console.error(
        'Error when processing request. Error class: ' +
          response.error_class +
          ', Error code: ' +
          response.error_code +
          ', Error message: ' +
          message,
      )
    }
  })
}

APP.displayGDriveFiles = function () {
  if (!$('#gdrive-files-list').is(':visible')) {
    $('#upload-files-list, .gdrive-addlink-container').hide()
    $('#gdrive-files-list').show()

    CreateProjectActions.enableAnalyzeButton(true)
  }
}

APP.hideGDriveFiles = function () {
  if ($('#gdrive-files-list').is(':visible')) {
    $('#gdrive-files-list').hide()
    $('#upload-files-list, .gdrive-addlink-container').show()
    CreateProjectActions.enableAnalyzeButton(false)
  }
}

APP.hideGDLink = function () {
  $('.gdrive-addlink-container').hide()
}

APP.showGDLink = function () {
  $('.gdrive-addlink-container').show()
}
