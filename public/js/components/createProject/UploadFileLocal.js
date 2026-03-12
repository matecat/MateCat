import React, {useContext, useMemo} from 'react'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../common/Button/Button'
import {DeleteIcon} from '../segments/SegmentFooterTabGlossary'
import FileUploadIconBig from '../../../img/icons/FileUploadIconBig'
import CommonUtils from '../../utils/commonUtils'
import IconAdd from '../icons/IconAdd'
import IconClose from '../icons/IconClose'
import {PROGRESS_BAR_SIZE, ProgressBar} from '../common/ProgressBar'
import {getPrintableFileSize} from './UploadFile'
import {CreateProjectContext} from './CreateProjectContext'
import {useFileUploadManager} from './hooks/useFileUploadManager'
import {useDragAndDrop} from './hooks/useDragAndDrop'

function UploadFileLocal() {
  const {
    sourceLang,
    targetLangs,
    currentProjectTemplate,
    setUploadedFilesNames,
    tmKeys,
    setTmKeys,
    modifyingCurrentTemplate,
    fileImportFiltersParamsTemplates,
  } = useContext(CreateProjectContext)

  const segmentationRule = currentProjectTemplate?.segmentationRule.id
  const extractionParameterTemplateId =
    currentProjectTemplate?.filters_template_id
  const icuEnabled = currentProjectTemplate?.icuEnabled

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

  const {files, handleFiles, deleteFile, deleteAllFiles} = useFileUploadManager(
    {
      sourceLang,
      targetLangs,
      segmentationRule,
      extractionParameterTemplateId,
      currentFiltersExtractionParameters,
      icuEnabled,
      setUploadedFilesNames,
      tmKeys,
      setTmKeys,
      modifyingCurrentTemplate,
    },
  )

  const {isDragging, dragHandlers} = useDragAndDrop({handleFiles})

  const handleChange = (e) => {
    handleFiles(Array.from(e.target.files))
    e.target.value = ''
  }

  return (
    <div
      className={`upload-files-container ${isDragging ? 'dragging' : ''} ${files.length > 0 ? 'add-files' : ''}`}
      {...dragHandlers}
      onClick={
        files.length === 0
          ? () => document.getElementById('fileInput').click()
          : null
      }
    >
      <input
        type="file"
        multiple
        style={{display: 'none'}}
        id="fileInput"
        onChange={handleChange}
      />
      {files.length === 0 ? (
        <EmptyState isDragging={isDragging} />
      ) : (
        <>
          <FileList files={files} deleteFile={deleteFile} />
          <ActionButtons
            files={files}
            deleteFile={deleteFile}
            deleteAllFiles={deleteAllFiles}
          />
        </>
      )}
    </div>
  )
}

function EmptyState({isDragging}) {
  return (
    <div className="upload-files-start">
      <FileUploadIconBig />
      {!isDragging ? (
        <>
          <p>Drop your files to translate them with Matecat</p>
          <span>or click to browse</span>
        </>
      ) : (
        <p>Drop it here</p>
      )}
    </div>
  )
}

function FileList({files, deleteFile}) {
  return (
    <div className="upload-files-list">
      {files.map((f, idx) => (
        <FileItem key={idx} file={f} onDelete={() => deleteFile(f)} />
      ))}
    </div>
  )
}

function FileItem({file: f, onDelete}) {
  return (
    <div className={`file-item ${f.zipFolder ? 'zip-folder' : ''}`}>
      <div className="file-item-name">
        <span className={`file-icon ${CommonUtils.getIconClass(f.ext)}`} />
        {f.name}
      </div>
      {f.error && (
        <div className="file-item-error">
          <span dangerouslySetInnerHTML={{__html: f.error}} />
        </div>
      )}
      {f.warning && <div className="file-item-warning">{f.warning}</div>}
      {f.uploaded &&
        f.converted &&
        !f.error &&
        f.size &&
        getPrintableFileSize(f.size)}
      {!f.uploaded && !f.error && f.uploadProgress > 0 && (
        <div className="upload-progress">
          <ProgressBar
            total={100}
            progress={f.uploadProgress}
            size={PROGRESS_BAR_SIZE.BIG}
            showProgress={true}
            label="Uploading"
          />
        </div>
      )}
      {f.uploaded && !f.converted && !f.error && f.convertProgress > 0 && (
        <div className="upload-progress">
          <ProgressBar
            total={100}
            progress={f.convertProgress}
            size={PROGRESS_BAR_SIZE.BIG}
            label="Importing"
            className="importing-progress"
          />
        </div>
      )}
      <Button
        size={BUTTON_SIZE.ICON_SMALL}
        onClick={onDelete}
        style={{marginLeft: 'auto'}}
        tooltip="Remove file"
      >
        <DeleteIcon />
      </Button>
    </div>
  )
}

function ActionButtons({files, deleteFile, deleteAllFiles}) {
  return (
    <div className="upload-files-buttons">
      <span>
        <strong>Drag and drop</strong> your file here or
      </span>
      <Button
        type={BUTTON_TYPE.PRIMARY}
        onClick={() => document.getElementById('fileInput').click()}
        disabled={files.length >= config.maxNumberFiles}
      >
        <IconAdd />
        Add files...
      </Button>
      <Button type={BUTTON_TYPE.WARNING} onClick={deleteAllFiles}>
        <IconClose /> Clear all
      </Button>
      {files.filter((f) => f.error).length > 0 && (
        <Button
          type={BUTTON_TYPE.WARNING}
          onClick={() => files.forEach((f) => f.error && deleteFile(f))}
        >
          <IconClose /> Clear all failed
        </Button>
      )}
    </div>
  )
}

export default UploadFileLocal
