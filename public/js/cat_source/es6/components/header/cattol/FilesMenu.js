import React, {useEffect, useState, useRef} from 'react'
import {getJobFileInfo} from '../../../api/getJobFileInfo'
import CatToolActions from '../../../actions/CatToolActions'
import Shortcuts from '../../../utils/shortcuts'
import CommonUtils from '../../../utils/commonUtils'
import SegmentActions from '../../../actions/SegmentActions'
import SegmentStore from '../../../stores/SegmentStore'

export const FilesMenu = ({projectName}) => {
  const [files, setFiles] = useState()
  const [menuVisible, setMenuVisible] = useState(false)
  const [currentSegment, setCurrentSegment] = useState()
  const firstJobSegment = useRef()

  useEffect(() => {
    getJobFileInfo(config.id_job, config.password).then((response) => {
      CatToolActions.storeFilesInfo(response)
      setFiles(response.files)
      firstJobSegment.current = response.first_segment
    })
  })

  const toggleMenu = () => {
    if (!menuVisible) {
      CatToolActions.closeSubHeader()
      const current = SegmentStore.getCurrentSegmentId()
      setCurrentSegment(current)
    }
    setMenuVisible(!menuVisible)
  }

  const goToCurrentSegment = () => {
    SegmentActions.scrollToCurrentSegment()
  }
  const goToSegment = (sid) => {
    SegmentActions.scrollToSegment(sid)
  }

  return (
    <div
      className="breadcrumbs file-list"
      title="File list"
      onClick={toggleMenu}
    >
      {files && (
        <>
          <div className="icon-container">
            <span id="project-badge">
              <span>{files.length}</span>
            </span>
            <img src="/public/img/icons/icon-folder.svg" alt="" />
          </div>
          <p id="pname-container">
            <a href="#" id="pname">
              {projectName}
            </a>
          </p>
        </>
      )}
      {/*Menu File*/}
      {menuVisible && (
        <nav id="jobMenu" className="topMenu">
          <ul className="gotocurrentsegment">
            <li
              className="currSegment"
              onClick={goToCurrentSegment}
              disabled={!currentSegment}
            >
              <a>Go to current segment</a>
              <span>
                {Shortcuts.cattol.events.gotoCurrent.keystrokes[
                  Shortcuts.shortCutsKeyType
                ].toUpperCase()}
              </span>
            </li>
            <li className="firstSegment">
              <span className="label">Go to first segment of the file:</span>
            </li>
          </ul>
          <div className="separator" />
          <ul className="jobmenu-list">
            {files.map((file) => {
              return (
                <li
                  key={file.id}
                  onClick={() => goToSegment(file.first_segment)}
                >
                  <span
                    className={CommonUtils.getIconClass(
                      file.file_name.split('.')[
                        file.file_name.split('.').length - 1
                      ],
                    )}
                  />
                  <span title={file.file_name}>
                    {file.file_name.substring(0, 20)}
                  </span>
                </li>
              )
            })}
          </ul>
        </nav>
      )}
    </div>
  )
}
