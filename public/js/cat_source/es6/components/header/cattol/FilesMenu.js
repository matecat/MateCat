import React, {useEffect, useState, useRef} from 'react'
import {getJobFileInfo} from '../../../api/getJobFileInfo'
import CatToolActions from '../../../actions/CatToolActions'
import Shortcuts from '../../../utils/shortcuts'
import CommonUtils from '../../../utils/commonUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'
import {FilenameLabel} from '../../common/FilenameLabel'
import {getFileSegments} from '../../../api/getFileSegments'

function useOutsideAlerter(ref, fun) {
  useEffect(() => {
    function handleClickOutside(event) {
      if (ref.current && !ref.current.contains(event.target)) {
        fun()
      }
    }
    // Bind the event listener
    document.addEventListener('mousedown', handleClickOutside)
    return () => {
      // Unbind the event listener on clean up
      document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [ref])
}

export const FilesMenu = ({projectName}) => {
  const [files, setFiles] = useState()
  const [currentFile, setCurrentFile] = useState()
  const [menuVisible, setMenuVisible] = useState(false)
  const [currentSegment, setCurrentSegment] = useState()
  const firstJobSegment = useRef()
  const containerRef = useRef()
  useOutsideAlerter(containerRef, () => setMenuVisible(false))

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

  const toggleMenu = () => {
    if (!menuVisible) {
      CatToolActions.closeSubHeader()
      const current = SegmentStore.getCurrentSegment()
      if (current) {
        setCurrentSegment(current.sid)
        // check if use id_file or id_file_part
        const idFileProp = files.find(
          ({id}) => id === parseInt(current.id_file),
        )
          ? 'id_file'
          : 'id_file_part'
        setCurrentFile(parseInt(current[idFileProp]))
      }
    }
    setMenuVisible(!menuVisible)
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

  return (
    <div
      className="breadcrumbs file-list"
      title="File list"
      onClick={toggleMenu}
      ref={containerRef}
    >
      {files && (
        <>
          <div className="icon-container">
            <span id="project-badge">
              <span>{files.length}</span>
            </span>
            <img src="/public/img/icons/icon-folder.svg" alt="" />
          </div>
          <div id="pname-container">
            <FilenameLabel cssClassName={'project-name'}>
              {projectName}
            </FilenameLabel>
          </div>
        </>
      )}
      {menuVisible && (
        <div className="job-menu-files">
          <div
            className="to-current"
            onClick={goToCurrentSegment}
            disabled={!currentSegment}
          >
            <div className="icon-iconmoon" />
            <span>Go to current segment</span>
            <span className={'current-shortcut'}>
              {Shortcuts.cattol.events.gotoCurrent.keystrokes[
                Shortcuts.shortCutsKeyType
              ].toUpperCase()}
            </span>
          </div>
          <div className="file-list-container">
            {/*<span className="file-list-label">*/}
            {/*  Go to first segment of the file:*/}
            {/*</span>*/}
            {files.map((file) => {
              return (
                <div
                  key={file.id}
                  onClick={() => goToFirstSegment(file)}
                  className={`file-list-item ${
                    currentFile === file.id ? 'current' : ''
                  }`}
                  title={'Click to go to the first segment'}
                >
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
                  {currentFile === file.id && (
                    <span className="current-icon">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 16 12"
                        width={16}
                        height={16}
                      >
                        <path
                          fill="#FFF"
                          fillRule="evenodd"
                          stroke="none"
                          strokeWidth="1"
                          d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z"
                          transform="translate(-266 -10) translate(266 8) translate(0 2)"
                        />
                      </svg>
                    </span>
                  )}
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
