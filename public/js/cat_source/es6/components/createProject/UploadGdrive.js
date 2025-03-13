import React, {useCallback, useContext, useEffect, useState} from 'react'
import UserStore from '../../stores/UserStore'
import ModalsActions from '../../actions/ModalsActions'
import {getUserConnectedService} from '../../api/getUserConnectedService'
import {openGDriveFiles} from '../../api/openGDriveFiles'
import CreateProjectActions from '../../actions/CreateProjectActions'
import {getGoogleDriveUploadedFiles} from '../../api/getGoogleDriveUploadedFiles'
import CommonUtils from '../../utils/commonUtils'
import {FILES_TYPE, getPrintableFileSize} from './UploadFile'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import {deleteGDriveUploadedFile} from '../../api/deleteGdriveUploadedFile'
import IconClose from '../icons/IconClose'
import {usePrevious} from '../../hooks/usePrevious'
import {CreateProjectContext} from './CreateProjectContext'
import CreateProjectStore from '../../stores/CreateProjectStore'
import {changeGDriveSourceLang} from '../../api/changeGDriveSourceLang'

export const UploadGdrive = () => {
  const [authApiLoaded, setAuthApiLoaded] = useState(false)
  const [pickerApiLoaded, setPickerApiLoaded] = useState(false)
  const [loading, setLoading] = useState(false)
  const [files, setFiles] = useState([])
  const {
    openGDrive,
    sourceLang,
    targetLangs,
    currentProjectTemplate,
    setUploadedFilesNames,
    setOpenGDrive,
  } = useContext(CreateProjectContext)
  const segmentationRule = currentProjectTemplate?.segmentationRule.id
  const extractionParameterTemplateId =
    currentProjectTemplate?.filters_template_id
  const openGDrivePrev = usePrevious(openGDrive)

  useEffect(() => {
    gapi.load('auth', {callback: setAuthApiLoaded(true)})
    gapi.load('picker', {callback: setPickerApiLoaded(true)})
  }, [])

  useEffect(() => {
    openGDrive && !openGDrivePrev && openGDrivePicker()
  }, [openGDrive, openGDrivePrev])

  useEffect(() => {
    CreateProjectActions.enableAnalyzeButton(files.length > 0)
    if (files.length >= config.maxNumberFiles) {
      CreateProjectActions.showError(
        'No more files can be loaded (the limit of ' +
          config.maxNumberFiles +
          ' has been exceeded).',
      )
    }
    if (files.length === 0) {
      setOpenGDrive(false)
    }
  }, [files])

  useEffect(() => {
    restartGDriveConversions()
  }, [sourceLang, extractionParameterTemplateId, segmentationRule])

  const gdriveInitComplete = () => {
    return pickerApiLoaded && authApiLoaded
  }

  const tryToRefreshToken = (service) => {
    return getUserConnectedService(service.id)
  }

  const openGDrivePicker = () => {
    if (!gdriveInitComplete()) {
      console.log('gdriveInitComplete not complete')
      return
    }

    const defaultService = UserStore.getDefaultConnectedService()

    if (!defaultService) {
      showPreferencesWithMessage()
      return
    }

    tryToRefreshToken(defaultService)
      .then((data) => {
        UserStore.updateConnectedService(data.connected_service)
        createPicker(UserStore.getDefaultConnectedService())
      })
      .catch(() => {
        UserStore.updateConnectedService({defaultService, is_default: false})
        showPreferencesWithMessage()
      })
  }

  const createPicker = (service) => {
    const token = JSON.parse(service.oauth_access_token)

    const picker = new google.picker.PickerBuilder()
      .setAppId(window.clientId)
      .addView(google.picker.ViewId.DOCUMENTS)
      .addView(google.picker.ViewId.PRESENTATIONS)
      .addView(google.picker.ViewId.SPREADSHEETS)
      .setOAuthToken(token.access_token)
      .setDeveloperKey(window.developerKey)
      .setCallback(pickerCallback)
      .enableFeature(google.picker.Feature.MINE_ONLY)
      .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
      .build()
    try {
      picker.setVisible(true)
    } catch (e) {
      UserStore.updateConnectedService({service, is_default: false})
      throw new Error('Picker Error')
    }
  }

  const pickerCallback = (data) => {
    if (data[google.picker.Response.ACTION] == google.picker.Action.CANCEL) {
      files.length === 0 && setOpenGDrive(false)
      return
    }
    if (data[google.picker.Response.ACTION] == google.picker.Action.PICKED) {
      let exportIds = []
      data[google.picker.Response.DOCUMENTS].forEach((doc) => {
        exportIds.push(doc.id)
      })

      const jsonDoc = {
        exportIds: exportIds,
        action: 'open',
      }

      setLoading(true)
      openGDriveFiles({
        encodedJson: encodeURIComponent(JSON.stringify(jsonDoc)),
        sourceLang: sourceLang.code,
        targetLang: targetLangs.map((lang) => lang.id).join(),
        segmentation_rule: segmentationRule,
        filters_extraction_parameters_template_id:
          extractionParameterTemplateId,
      }).then((response) => {
        CreateProjectActions.hideErrors()
        if (response.success) {
          tryListGDriveFiles()
        } else {
          let message =
            'There was an error retrieving the file from Google Drive. Try again and if the error persists contact the Support.'
          if (response.error_class === 'Google\\Service\\Exception') {
            message =
              'There was an error retrieving the file from Google Drive: ' +
              response.error_msg
          }
          if (response.error_code === 404) {
            message = (
              <span>
                File retrieval error. To find out how to translate the desired
                file, please{' '}
                <a
                  href="https://guides.matecat.com/google-drive-files-upload-issues"
                  target="_blank"
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
        setLoading(false)
      })
    }
  }

  const deleteGDriveFile = (file) => {
    deleteGDriveUploadedFile(file.id).then((response) => {
      setUploadedFilesNames((prev) => prev.filter((f) => f !== file.name))
      if (response.success) {
        tryListGDriveFiles()
      }
    })
  }

  const tryListGDriveFiles = () => {
    getGoogleDriveUploadedFiles()
      .then((listFiles) => {
        let filesList = []
        if (listFiles && listFiles.files) {
          listFiles.files.forEach((file) => {
            setUploadedFilesNames((prev) => prev.concat([file.fileName]))
            filesList.push({
              name: file.fileName,
              ext: file.fileExtension,
              size: file.fileSize,
              id: file.fileId,
            })
          })
          CreateProjectActions.enableAnalyzeButton(true)
        }
        setFiles(filesList)
      })
      .catch((error) => {
        if (error.code === 400) {
          const message = <span>{error.msg}</span>
          CreateProjectActions.showError(message)
        }
      })
  }

  const restartGDriveConversions = () => {
    if (files.length > 0) {
      setLoading(true)
      CreateProjectActions.enableAnalyzeButton(false)
      changeGDriveSourceLang({
        sourceLang: sourceLang.code,
        segmentation_rule: segmentationRule,
        filters_extraction_parameters_template_id:
          extractionParameterTemplateId,
      }).then((response) => {
        setLoading(false)
        if (response.success) {
          CreateProjectActions.enableAnalyzeButton(true)
          console.log('Source language changed.')
        }
      })
    }
  }

  const showPreferencesWithMessage = () => {
    ModalsActions.openPreferencesModal({showGDriveMessage: true})
  }

  return (
    openGDrive && (
      <div
        className={`upload-files-container ${files.length > 0 ? 'add-files' : ''}`}
      >
        {loading && (
          <div className="modal-gdrive">
            <div className="ui active inverted dimmer">
              <div className="ui massive text loader">Uploading Files</div>
            </div>
          </div>
        )}
        {files.length > 0 && (
          <>
            <div className="upload-files-list">
              {files.map((f, idx) => (
                <div key={idx} className="file-item">
                  <div className="file-item-name">
                    <span
                      className={`file-icon ${CommonUtils.getIconClass(f.ext)}`}
                    />
                    {f.name}
                  </div>
                  <div>{getPrintableFileSize(f.size)}</div>
                  <Button
                    size={BUTTON_SIZE.ICON_SMALL}
                    style={{marginLeft: 'auto'}}
                    tooltip={'Remove file'}
                    onClick={() => deleteGDriveFile(f)}
                  >
                    <DeleteIcon />
                  </Button>
                </div>
              ))}
            </div>
            <div className="upload-files-buttons">
              <Button
                type={BUTTON_TYPE.PRIMARY}
                onClick={() => openGDrivePicker()}
                disabled={files.length >= config.maxNumberFiles}
              >
                <img
                  src="/public/img/logo-drive-16.png"
                  alt="Google drive logo"
                />
                Add from Google Drive
              </Button>
              <Button
                type={BUTTON_TYPE.WARNING}
                onClick={() => files.forEach((f) => deleteGDriveFile(f))}
              >
                <IconClose /> Clear all
              </Button>
            </div>
          </>
        )}
      </div>
    )
  )
}
