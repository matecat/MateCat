import React, {useContext, useEffect, useMemo} from 'react'
import CommonUtils from '../../utils/commonUtils'
import {getPrintableFileSize} from './UploadFile'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import IconClose from '../icons/IconClose'
import {usePrevious} from '../../hooks/usePrevious'
import {CreateProjectContext} from './CreateProjectContext'
import DriveIcon from '../../../img/icons/DriveIcon'
import {useGDrivePicker} from './hooks/useGDrivePicker'
import {useGDriveFiles} from './hooks/useGDriveFiles'
import {SpinnerLoader} from '../common/SpinnerLoader'

export const UploadGdrive = () => {
  const {
    openGDrive,
    sourceLang,
    targetLangs,
    currentProjectTemplate,
    setUploadedFilesNames,
    setOpenGDrive,
    setIsGDriveEnabled,
    fileImportFiltersParamsTemplates,
  } = useContext(CreateProjectContext)

  const segmentationRule = currentProjectTemplate?.segmentationRule.id
  const extractionParameterTemplateId =
    currentProjectTemplate?.filters_template_id

  const currentFiltersExtractionParameters = useMemo(() => {
    const unsavedTemplate = fileImportFiltersParamsTemplates.templates
      .filter(
        (template) =>
          template.id === extractionParameterTemplateId && template.isTemporary,
      )
      .map(
        ({
          /* eslint-disable */
          isSelected,
          isTemporary,
          id,
          created_at,
          modified_at,
          /* eslint-enable */
          ...result
        }) => result,
      )[0]

    return unsavedTemplate
  }, [
    extractionParameterTemplateId,
    fileImportFiltersParamsTemplates?.templates,
  ])

  const {files, loading, deleteFile, pickerCallback} = useGDriveFiles({
    sourceLang,
    targetLangs,
    segmentationRule,
    extractionParameterTemplateId,
    currentFiltersExtractionParameters,
    setUploadedFilesNames,
    setOpenGDrive,
  })

  const {openPicker} = useGDrivePicker({
    setIsGDriveEnabled,
    onFilesPicked: pickerCallback,
  })

  const openGDrivePrev = usePrevious(openGDrive)

  useEffect(() => {
    if (openGDrive && !openGDrivePrev) openPicker()
  }, [openGDrive, openGDrivePrev, openPicker])

  if (!openGDrive) return null

  return (
    <div
      className={`upload-files-container ${files.length > 0 ? 'add-files' : ''}`}
    >
      {loading && <LoadingOverlay />}
      {files.length > 0 && (
        <>
          <GDriveFileList files={files} onDelete={deleteFile} />
          <GDriveActionButtons
            files={files}
            onAddFiles={openPicker}
            onClearAll={() => files.forEach((f) => deleteFile(f))}
          />
        </>
      )}
    </div>
  )
}

function LoadingOverlay() {
  return (
    <div className="modal-gdrive">
      <SpinnerLoader label="Uploading Files" />
    </div>
  )
}

function GDriveFileList({files, onDelete}) {
  return (
    <div className="upload-files-list">
      {files.map((f, idx) => (
        <div key={idx} className="file-item">
          <div className="file-item-name">
            {CommonUtils.getFileIcon(f.ext)}
            {f.name}
          </div>
          <div>{getPrintableFileSize(f.size)}</div>
          <Button
            size={BUTTON_SIZE.ICON_SMALL}
            style={{marginLeft: 'auto'}}
            tooltip={'Remove file'}
            onClick={() => onDelete(f)}
          >
            <DeleteIcon />
          </Button>
        </div>
      ))}
    </div>
  )
}

function GDriveActionButtons({files, onAddFiles, onClearAll}) {
  return (
    <div className="upload-files-buttons">
      <Button
        type={BUTTON_TYPE.PRIMARY}
        onClick={onAddFiles}
        disabled={files.length >= config.maxNumberFiles}
      >
        <DriveIcon />
        Add from Google Drive
      </Button>
      <Button type={BUTTON_TYPE.WARNING} onClick={onClearAll}>
        <IconClose /> Clear all
      </Button>
    </div>
  )
}
