import React, {useEffect, useState} from 'react'

import JobMetadataModal from '../../modals/JobMetadataModal'
import SegmentStore from '../../../stores/SegmentStore'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import FileAttachment from '../../../../img/icons/FileAttachment'

export const JobMetadata = ({metadata}) => {
  const [files, setFiles] = useState()
  const [projectInfo, setProjectInfo] = useState()
  const [showButton, setShowButton] = useState(false)
  const closedPopupStorage = localStorage.getItem(
    'infoInstructions-' + config.userMail,
  )
  const openModal = () => {
    let currentSegment = SegmentStore.getCurrentSegment()
    let props = {
      currentFile: currentSegment ? parseInt(currentSegment.id_file) : null,
      currentFilePart: currentSegment
        ? parseInt(currentSegment.id_file_part)
        : null,
      files: files,
      projectInfo: projectInfo,
    }
    let styleContainer = {
      minWidth: 600,
      minHeight: 400,
      maxWidth: 900,
    }
    ModalsActions.showModalComponent(
      JobMetadataModal,
      props,
      'Job instructions and references',
      styleContainer,
    )
  }

  useEffect(() => {
    if (typeof metadata !== 'undefined') {
      const projectInfo =
        metadata.project && metadata.project.project_info
          ? metadata.project.project_info
          : undefined
      if (projectInfo) {
        setProjectInfo(projectInfo)
        setShowButton(true)
      }
    }
  }, [metadata])

  // useEffect(() => {
  //   // if (showButton && !closedPopupStorage) {
  //   //   showInfoTooltipFunction()
  //   // }
  // }, [showButton])

  useEffect(() => {
    const updateFiles = (files) => {
      const fileInstructions = files.find(
        (file) =>
          file &&
          ((file.metadata.instructions && file.metadata.instructions !== '') ||
            typeof file.metadata['mtc:references'] === 'string'),
      )
      if (fileInstructions) {
        setFiles(files)
        setShowButton(true)
      }
    }

    CatToolStore.addListener(CattolConstants.STORE_FILES_INFO, updateFiles)
    return () => {
      CatToolStore.removeListener(CattolConstants.STORE_FILES_INFO, updateFiles)
    }
  }, [])

  return (
    showButton && (
      <Button
        type={BUTTON_TYPE.ICON}
        mode={BUTTON_MODE.GHOST}
        onClick={openModal}
        size={BUTTON_SIZE.ICON_STANDARD}
      >
        <FileAttachment size={20} />
      </Button>
    )
  )
}

export default JobMetadata
