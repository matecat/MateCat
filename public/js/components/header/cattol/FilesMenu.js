import React, {useEffect, useState, useRef} from 'react'
import {getJobFileInfo} from '../../../api/getJobFileInfo'
import CatToolActions from '../../../actions/CatToolActions'
import {Shortcuts} from '../../../utils/shortcuts'
import CommonUtils from '../../../utils/commonUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import {FilenameLabel} from '../../common/FilenameLabel'
import {getFileSegments} from '../../../api/getFileSegments'
import {
  DROPDOWN_SEPARATOR,
  DropdownMenu,
} from '../../common/DropdownMenu/DropdownMenu'
import Files from '../../../../img/icons/Files'
import {BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import GoToIcon from '../../../../img/icons/GoToIcon'
import Check from '../../../../img/icons/Check'

export const FilesMenu = ({projectName}) => {
  const [files, setFiles] = useState()
  const [currentFile, setCurrentFile] = useState()
  const [currentSegment, setCurrentSegment] = useState()
  const firstJobSegment = useRef()

  useEffect(() => {
    getJobFileInfo(config.id_job, config.password).then((response) => {
      const files = CommonUtils.parseFiles(response.files)
      CatToolActions.storeFilesInfo(
        files,
        response.first_segment,
        response.last_segment,
      )
      setFiles(files)
      firstJobSegment.current = response.first_segment
    })
  }, [])

  const toggleMenu = (open) => {
    if (open) {
      CatToolActions.closeSubHeader()
      const current = SegmentStore.getCurrentSegment()
      if (current && current.opened) {
        setCurrentSegment(current.sid)
        // check if use id_file or id_file_part
        const idFileProp = files.find(
          ({id}) => id === parseInt(current.id_file),
        )
          ? 'id_file'
          : 'id_file_part'
        setCurrentFile(parseInt(current[idFileProp]))
      }
    } else {
      setCurrentSegment()
    }
  }

  const goToCurrentSegment = () => {
    SegmentActions.scrollToCurrentSegment()
  }
  const goToFirstSegment = (file) => {
    if (file.first_segment) {
      SegmentActions.openSegment(file.first_segment)
    } else {
      getFileSegments({
        idJob: config.id_job,
        password: config.password,
        file_id: file.id,
        file_type: file.type,
      }).then((data) => {
        SegmentActions.openSegment(data.first_segment)
      })
    }
  }

  const getFilesMenu = () => {
    return [
      {
        label: (
          <>
            <GoToIcon size={20} />
            <div>Go to current segment</div>
            <span className={'current-shortcut'}>
              {Shortcuts.cattol.events.gotoCurrent.keystrokes[
                Shortcuts.shortCutsKeyType
              ].toUpperCase()}
            </span>
          </>
        ),
        onClick: goToCurrentSegment,
        disabled: !currentSegment,
      },
      DROPDOWN_SEPARATOR,
      ...files.map((file) => ({
        label: (
          <>
            <div>
              <span
                className={
                  'file-icon ' +
                  CommonUtils.getIconClass(
                    file.file_name.split('.')[
                      file.file_name.split('.').length - 1
                    ],
                  )
                }
              />
              <span className="file-name">{file.file_name}</span>
            </div>
            {currentFile === file.id && <Check size={20} />}
          </>
        ),
        onClick: () => goToFirstSegment(file),
        selected: currentFile === file.id,
      })),
    ]
  }

  return (
    <>
      <DropdownMenu
        dropdownClassName={'files-menu-header'}
        toggleButtonProps={{
          children: (
            <>
              <Files size={20} />
              <FilenameLabel cssClassName={'project-name'}>
                {projectName}
              </FilenameLabel>
            </>
          ),
          size: BUTTON_SIZE.STANDARD,
          mode: BUTTON_MODE.LINK,
          className: 'files-menu-button',
        }}
        items={files && getFilesMenu()}
        onOpenChange={toggleMenu}
        className={'file-list-item'}
        disabled={!files}
      />
    </>
  )
}
