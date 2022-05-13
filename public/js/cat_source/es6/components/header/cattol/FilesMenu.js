import React, {useEffect, useState, useRef} from 'react'
import {getJobFileInfo} from '../../../api/getJobFileInfo'
import CatToolActions from '../../../actions/CatToolActions'
import Shortcuts from '../../../utils/shortcuts'
import CommonUtils from '../../../utils/commonUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'

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
      CatToolActions.storeFilesInfo(response)
      setFiles(response.files)
      firstJobSegment.current = response.first_segment
      UI.detectStartSegment() //TODO: remove
    })
  }, [])

  const toggleMenu = () => {
    if (!menuVisible) {
      CatToolActions.closeSubHeader()
      const current = SegmentStore.getCurrentSegment()
      setCurrentSegment(current.sid)
      setCurrentFile(current.id_file)
    }
    setMenuVisible(!menuVisible)
  }

  const goToCurrentSegment = () => {
    SegmentActions.scrollToCurrentSegment()
  }
  const goToSegment = (sid) => {
    SegmentActions.openSegment(sid)
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
            <span id="pname">{projectName}</span>
          </div>
        </>
      )}
      {/*Menu File*/}
      {/*{menuVisible && (*/}
      {/*  <nav id="jobMenu" className="topMenu open">*/}
      {/*    <ul className="gotocurrentsegment">*/}
      {/*      <li*/}
      {/*        className="currSegment"*/}
      {/*        onClick={goToCurrentSegment}*/}
      {/*        disabled={!currentSegment}*/}
      {/*      >*/}
      {/*        <a>Go to current segment</a>*/}
      {/*        <span>*/}
      {/*          {Shortcuts.cattol.events.gotoCurrent.keystrokes[*/}
      {/*            Shortcuts.shortCutsKeyType*/}
      {/*          ].toUpperCase()}*/}
      {/*        </span>*/}
      {/*      </li>*/}
      {/*      <li className="firstSegment">*/}
      {/*        <span className="label">Go to first segment of the file:</span>*/}
      {/*      </li>*/}
      {/*    </ul>*/}
      {/*    <div className="separator" />*/}
      {/*    <ul className="jobmenu-list">*/}
      {/*      {files.map((file) => {*/}
      {/*        return (*/}
      {/*          <li*/}
      {/*            key={file.id}*/}
      {/*            onClick={() => goToSegment(file.first_segment)}*/}
      {/*            className={currentFile === file.id ? 'current' : ''}*/}
      {/*          >*/}
      {/*            <span*/}
      {/*              className={CommonUtils.getIconClass(*/}
      {/*                file.file_name.split('.')[*/}
      {/*                  file.file_name.split('.').length - 1*/}
      {/*                ],*/}
      {/*              )}*/}
      {/*            />*/}
      {/*            <span title={file.file_name}>*/}
      {/*              {file.file_name.substring(0, 20)}*/}
      {/*              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 12">*/}
      {/*                <path*/}
      {/*                  fill="#FFF"*/}
      {/*                  fillRule="evenodd"*/}
      {/*                  stroke="none"*/}
      {/*                  strokeWidth="1"*/}
      {/*                  d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z"*/}
      {/*                  transform="translate(-266 -10) translate(266 8) translate(0 2)"*/}
      {/*                />*/}
      {/*              </svg>*/}
      {/*            </span>*/}
      {/*          </li>*/}
      {/*        )*/}
      {/*      })}*/}
      {/*    </ul>*/}
      {/*  </nav>*/}
      {/*)}*/}
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
                  onClick={() => goToSegment(file.first_segment)}
                  className={`file-list-item ${
                    currentFile === file.id ? 'current' : ''
                  }`}
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
