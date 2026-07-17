import {useState, useRef, useCallback, useEffect} from 'react'
import React from 'react'
import {isEqual} from 'lodash'
import {openGDriveFiles} from '../../../api/openGDriveFiles'
import {getGoogleDriveUploadedFiles} from '../../../api/getGoogleDriveUploadedFiles'
import {deleteGDriveUploadedFile} from '../../../api/deleteGdriveUploadedFile'
import {changeGDriveSourceLang} from '../../../api/changeGDriveSourceLang'
import CreateProjectActions from '../../../actions/CreateProjectActions'
export function useGDriveFiles({
  sourceLang,
  targetLangs,
  segmentationRule,
  extractionParameterTemplateId,
  currentFiltersExtractionParameters,
  setUploadedFilesNames,
  setOpenGDrive,
}) {
  const [files, setFiles] = useState([])
  const [loading, setLoading] = useState(false)
  const previousFiltersExtractionParameters = useRef()
  const tryListGDriveFiles = useCallback(() => {
    getGoogleDriveUploadedFiles()
      .then((listFiles) => {
        const filesList = []
        if (listFiles?.files) {
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
          CreateProjectActions.showError(<span>{error.msg}</span>)
        }
      })
  }, [setUploadedFilesNames])
  const deleteFile = useCallback(
    (file) => {
      deleteGDriveUploadedFile({
        fileId: file.id,
        source: sourceLang.code,
        segmentationRule,
        filtersTemplateId: extractionParameterTemplateId,
      })
        .then((response) => {
          setUploadedFilesNames((prev) => prev.filter((f) => f !== file.name))
          if (response.success) {
            tryListGDriveFiles()
          }
        })
        .catch(() => {
          setFiles([])
        })
      CreateProjectActions.hideErrors()
    },
    [
      sourceLang,
      segmentationRule,
      extractionParameterTemplateId,
      setUploadedFilesNames,
      tryListGDriveFiles,
    ],
  )
  const buildFilterParams = useCallback(() => {
    return typeof currentFiltersExtractionParameters === 'object'
      ? {
          filters_extraction_parameters_template: JSON.stringify(
            currentFiltersExtractionParameters,
          ),
        }
      : {
          filters_extraction_parameters_template_id:
            extractionParameterTemplateId,
        }
  }, [currentFiltersExtractionParameters, extractionParameterTemplateId])
  const pickerCallback = useCallback(
    (data) => {
      if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
        if (files.length === 0) setOpenGDrive(false)
        return
      }
      if (data[google.picker.Response.ACTION] === google.picker.Action.PICKED) {
        const exportIds = data[google.picker.Response.DOCUMENTS].map(
          (doc) => doc.id,
        )
        const jsonDoc = {exportIds, action: 'open'}
        setLoading(true)
        openGDriveFiles({
          stateJson: JSON.stringify(jsonDoc),
          sourceLang: sourceLang.code,
          targetLang: targetLangs.map((lang) => lang.id).join(),
          segmentation_rule: segmentationRule,
          ...buildFilterParams(),
        })
          .then((response) => {
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
              if (response.error_class === 'InvalidArgumentException') {
                message = response.error_msg
              }
              if (response.error_code === 404) {
                message = (
                  <span>
                    File retrieval error. To find out how to translate the
                    desired file, please{' '}
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
              if (files.length === 0) {
                setOpenGDrive(false)
              }
            }
            setLoading(false)
          })
          .catch(() => {
            CreateProjectActions.showError(
              <span>
                There was a problem uploading the file, please try again or
                contact support.
              </span>,
            )
            setLoading(false)
            setOpenGDrive(false)
          })
      }
    },
    [
      files.length,
      sourceLang,
      targetLangs,
      segmentationRule,
      buildFilterParams,
      tryListGDriveFiles,
      setOpenGDrive,
    ],
  )
  const restartConversions = useCallback(() => {
    if (files.length > 0) {
      setLoading(true)
      CreateProjectActions.enableAnalyzeButton(false)
      changeGDriveSourceLang({
        sourceLang: sourceLang.code,
        segmentation_rule: segmentationRule,
        ...buildFilterParams(),
      })
        .then(() => {
          setLoading(false)
          CreateProjectActions.enableAnalyzeButton(true)
          console.log('Source language changed.')
        })
        .catch(() => {
          CreateProjectActions.showError(
            <span>
              There was a problem uploading the file, please try again or
              contact support.
            </span>,
          )
          setLoading(false)
        })
    }
  }, [files.length, sourceLang, segmentationRule, buildFilterParams])
  useEffect(() => {
    CreateProjectActions.enableAnalyzeButton(files.length > 0)
    if (files.length >= config.maxNumberFiles) {
      CreateProjectActions.showError(
        'No more files can be loaded (the limit of ' +
          config.maxNumberFiles +
          ' has been exceeded).',
      )
    } else if (files.length > config.maxNumberFiles) {
      CreateProjectActions.showError(
        `Maximum ${config.maxNumberFiles} files allowed. Please remove all files with errors from the list below.`,
      )
    }
    if (files.length === 0) {
      setOpenGDrive(false)
    }
  }, [files, setOpenGDrive])
  useEffect(() => {
    restartConversions()
  }, [sourceLang, extractionParameterTemplateId, segmentationRule]) // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (
      !isEqual(
        currentFiltersExtractionParameters,
        previousFiltersExtractionParameters.current,
      )
    )
      restartConversions()
    previousFiltersExtractionParameters.current =
      currentFiltersExtractionParameters
  }, [currentFiltersExtractionParameters]) // eslint-disable-line react-hooks/exhaustive-deps
  return {files, loading, deleteFile, pickerCallback}
}
