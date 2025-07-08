import React, {useEffect, useRef, useState} from 'react'
import $ from 'jquery'
import Icon3Dots from '../icons/Icon3Dots'
import {exportQualityReport} from '../../api/exportQualityReport'
import CatToolActions from '../../actions/CatToolActions'
import ShortCutsModal from '../modals/ShortCutsModal'
import ModalsActions from '../../actions/ModalsActions'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../utils/shortcuts'
import {
  DROPDOWN_MENU_ALIGN,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import {BUTTON_SIZE} from '../common/Button/Button'

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
  const [isExportXMLDisabled, setIsExportXMLDisabled] = useState(false)

  const openShortcutsModal = (event) => {
    event.preventDefault()
    event.stopPropagation()
    ModalsActions.showModalComponent(ShortCutsModal, {}, 'Shortcuts')
  }

  const getQualityReportMenu = () => {
    return [
      ...(jobUrls.revise_urls
        ? [
            {
              label: (
                <>
                  <span>Revise</span>
                </>
              ),
              onClick: () => {
                window.open(jobUrls.revise_urls[0].url)
              },
            },
          ]
        : null),
      {
        label: (
          <>
            <span>Translate</span>
          </>
        ),
        onClick: () => {
          window.open(jobUrls.translate_url)
        },
      },
      {
        label: (
          <>
            <span
              className={`${isExportCsvDisabled ? ' disabled' : ''}`}
              title="Export CSV"
            >
              Download QA Report CSV
            </span>
          </>
        ),
        onClick: () => {
          !isExportCsvDisabled ? handlerExportCsv() : null
        },
      },
      {
        label: (
          <>
            <span
              className={`${isExportJsonDisabled ? ' disabled' : ''}`}
              title="Export JSON"
            >
              Download QA Report JSON
            </span>
          </>
        ),
        onClick: () => {
          !isExportJsonDisabled ? handlerExportJson() : null
        },
      },
      {
        label: (
          <>
            <span
              className={`item${isExportXMLDisabled ? ' disabled' : ''}`}
              title="Export XML"
            >
              Download QA Report XML
            </span>
          </>
        ),
        onClick: () => {
          !isExportXMLDisabled ? handlerExportXML() : null
        },
      },
    ]
  }

  const getCattoolMenu = () => {
    return [
      ...(!isReview && showReviseLink
        ? [
            {
              label: (
                <>
                  <span title="Revise">Revise</span>
                </>
              ),
              onClick: () => {
                window.open(
                  `/revise/${projectName}/${source_code}-${target_code}/${jid}-${reviewPassword}`,
                )
              },
            },
          ]
        : []),
      ...(isReview
        ? [
            {
              label: (
                <>
                  <span title="Translate">Translate</span>
                </>
              ),
              onClick: () => {
                window.open(
                  `/translate/${projectName}/${source_code}-${target_code}/${jid}-${password}`,
                )
              },
            },
          ]
        : []),
      ...(allowLinkToAnalysis && analysisEnabled
        ? [
            {
              label: (
                <>
                  <span title="Analysis">Volume analysis</span>
                </>
              ),
              onClick: () => {
                window.open(`/jobanalysis/${pid}-${jid}-${password}`)
              },
            },
          ]
        : []),
      {
        label: (
          <>
            <span title="XLIFF-to-target converter">
              XLIFF-to-target converter
            </span>
          </>
        ),
        onClick: () => {
          window.open(`/utils/xliff-to-target`)
        },
      },
      {
        label: (
          <>
            <span title="Shortcuts">Shortcuts</span>
          </>
        ),
        onClick: openShortcutsModal,
      },
    ]
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
  const handlerExportXML = () => {
    setIsExportXMLDisabled(true)

    exportQualityReport({format: 'xml'})
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
          text: `Downloading XML error status code: ${errors.status}`,
          type: 'error',
        }
        CatToolActions.addNotification(notification)
      })
      .finally(() => setIsExportXMLDisabled(false))
  }

  return (
    <DropdownMenu
      dropdownClassName="action-menu"
      align={DROPDOWN_MENU_ALIGN.RIGHT}
      toggleButtonProps={{
        children: <Icon3Dots />,
        size: BUTTON_SIZE.ICON_STANDARD,
      }}
      items={
        cattoolMenu ? getCattoolMenu() : qrMenu ? getQualityReportMenu() : null
      }
    />
  )
}
