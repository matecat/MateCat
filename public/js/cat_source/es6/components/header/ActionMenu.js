import React, {useEffect, useRef, useState} from 'react'

import Icon3Dots from '../icons/Icon3Dots'
import {exportQualityReport} from '../../api/exportQualityReport'
import CatToolActions from '../../actions/CatToolActions'
import ShortCutsModal from '../modals/ShortCutsModal'
import ModalsActions from '../../actions/ModalsActions'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../utils/shortcuts'

export const ActionMenu = ({
  jobUrls,
  qrMenu = true,
  cattoolMenu = false,
  isReview,
  projectName,
  source_code,
  target_code,
  jid,
  password,
  reviewPassword,
  allowLinkToAnalysis,
  analysisEnabled,
  pid,
  showReviseLink = true,
}) => {
  useHotkeys(
    Shortcuts.cattol.events.openShortcutsModal.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => openShortcutsModal(e),
    {enableOnContentEditable: true},
  )
  const [isExportCsvDisabled, setIsExportCsvDisabled] = useState(false)
  const [isExportJsonDisabled, setIsExportJsonDisabled] = useState(false)
  const dropdownThreeDots = useRef()

  const initDropdowns = () => {
    // 3Dots
    if (dropdownThreeDots.current) {
      $(dropdownThreeDots.current).dropdown()
    }
  }

  const openShortcutsModal = (event) => {
    event.preventDefault()
    event.stopPropagation()
    ModalsActions.showModalComponent(ShortCutsModal, {}, 'Shortcuts')
  }

  const getQualityReportMenu = () => {
    return (
      <ul className="menu">
        <li className="item" title="Revise" data-value="revise">
          <a href={jobUrls.revise_urls[0].url}>Revise</a>
        </li>
        <li className="item" title="Translate" data-value="translate">
          <a href={jobUrls.translate_url}>Translate</a>
        </li>
        <li
          className={`item${isExportCsvDisabled ? ' disabled' : ''}`}
          title="Export CSV"
          data-value="export-csv"
        >
          <span onClick={!isExportCsvDisabled ? handlerExportCsv : () => {}}>
            Download QA Report CSV
          </span>
        </li>
        <li
          className={`item${isExportJsonDisabled ? ' disabled' : ''}`}
          title="Export JSON"
          data-value="export-json"
        >
          <span onClick={!isExportJsonDisabled ? handlerExportJson : () => {}}>
            Download QA Report JSON
          </span>
        </li>
      </ul>
    )
  }

  const getCattoolMenu = () => {
    return (
      <ul className="menu">
        {!isReview && showReviseLink && (
          <li className="item" title="Revise" data-value="revise">
            <a
              href={`/revise/${projectName}/${source_code}-${target_code}/${jid}-${reviewPassword}`}
            >
              Revise
            </a>
          </li>
        )}
        {isReview && (
          <li className="item" title="Translate" data-value="translate">
            <a
              href={`/translate/${projectName}/${source_code}-${target_code}/${jid}-${password}`}
            >
              Translate
            </a>
          </li>
        )}
        {allowLinkToAnalysis && analysisEnabled && (
          <li className="item" title="Analysis" data-value="analisys">
            <a
              rel="noreferrer"
              target="_blank"
              href={`/jobanalysis/${pid}-${jid}-${password}`}
            >
              Volume analysis
            </a>
          </li>
        )}

        <li
          className="item"
          title="XLIFF-to-target converter"
          data-value="target"
        >
          <a rel="noreferrer" target="_blank" href={`/utils/xliff-to-target`}>
            XLIFF-to-target converter
          </a>
        </li>
        <li
          className="item shortcuts"
          title="Shortcuts"
          data-value="shortcuts"
          onClick={openShortcutsModal}
        >
          <a>Shortcuts</a>
        </li>
        {/*<li class="item" title="Edit log" data-value="editlog" >*/}
        {/*    <a id="edit_log_link" target="_blank" href={`editlog/${jid}-${password}`}>Editing Log</a>*/}
        {/*</li>*/}
      </ul>
    )
  }

  const handlerExportCsv = () => {
    setIsExportCsvDisabled(true)

    exportQualityReport()
      .then(({blob, filename}) => {
        const aTag = document.createElement('a')
        const blobURL = URL.createObjectURL(blob)
        aTag.download = filename
        aTag.href = blobURL
        document.body.appendChild(aTag)
        aTag.click()
        document.body.removeChild(aTag)
      })
      .catch((errors) => {
        const notification = {
          title: 'Error',
          text: `Downloading CSV error status code: ${errors.status}`,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
      .finally(() => setIsExportCsvDisabled(false))
  }

  const handlerExportJson = () => {
    setIsExportJsonDisabled(true)

    exportQualityReport({format: 'json'})
      .then(({blob, filename}) => {
        const aTag = document.createElement('a')
        const blobURL = URL.createObjectURL(blob)
        aTag.download = filename
        aTag.href = blobURL
        document.body.appendChild(aTag)
        aTag.click()
        document.body.removeChild(aTag)
      })
      .catch((errors) => {
        const notification = {
          title: 'Error',
          text: `Downloading JSON error status code: ${errors.status}`,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
      .finally(() => setIsExportJsonDisabled(false))
  }

  useEffect(() => {
    initDropdowns()
  }, [dropdownThreeDots.current])

  return (
    <div className={'action-menu qr-element'}>
      <div
        className={'action-submenu ui pointing top center floating dropdown'}
        id={'action-three-dots'}
        ref={dropdownThreeDots}
      >
        <Icon3Dots />
        {qrMenu && !cattoolMenu && getQualityReportMenu()}
        {cattoolMenu && getCattoolMenu()}
      </div>
    </div>
  )
}
