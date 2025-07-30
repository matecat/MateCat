import React, {useEffect, useState, useRef} from 'react'
import classnames from 'classnames'

import {IconQR} from '../icons/IconQR'
import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'
import CatToolActions from '../../actions/CatToolActions'
import {reloadQualityReport} from '../../api/reloadQualityReport'
import CommonUtils from '../../utils/commonUtils'
import {REVISE_STEP_NUMBER} from '../../constants/Constants'

/**
 * @NOTE because the state of this component is manipulated
 * outside of the React tree, we currently cannot convert it
 * into a function component...
 */
export const QualityReportButton = ({
  isReview,
  revisionNumber,
  secondRevisionsCount,
  qualityReportHref,
  overallQualityClass,
}) => {
  const [is_pass, setIsPass] = useState()
  // const [score, setScore] = useState()
  const [vote, setVote] = useState()
  const [progress, setProgress] = useState()
  const [feedback, setFeedback] = useState()
  const revision_number = revisionNumber ? revisionNumber : '1'
  const qrParam = secondRevisionsCount
    ? '?revision_type=' + revision_number
    : ''
  const quality_report_href = useRef(qualityReportHref + qrParam)

  const openFeedbackModal = (e) => {
    e.preventDefault()
    e.stopPropagation()
    CatToolActions.openFeedbackModal(feedback, revisionNumber)
  }

  const updateQr = (qr) => {
    if (qr) {
      const revNumber = revisionNumber ? revisionNumber : 1
      const review = qr.chunk.reviews.find(function (value) {
        return value.revision_number === revNumber
      })
      let newVote = ''
      if (review) {
        if (review.is_pass != null && isReview) {
          newVote = review.is_pass ? 'excellent' : 'fail'
        }
        setIsPass(review.is_pass)
        setVote(newVote)
        // setScore(review.score)
        setFeedback(review.feedback)
      }
      CatToolActions.updateQualityReport(qr)
    }
  }

  const reloadQualityReportFn = () => {
    reloadQualityReport().then((data) => {
      updateQr(data['quality-report'])
    })
  }

  const checkQualityReport = (stats) => {
    let reviseCount = config.isReview
      ? config.revisionNumber === REVISE_STEP_NUMBER.REVISE2
        ? stats.raw.approved2
        : stats.raw.approved
      : null
    if (config.isReview && reviseCount && reviseCount >= stats.raw.total) {
      let revise = CatToolStore.getQR(config.revisionNumber)
      if (revise && !revise[0].feedback) {
        const isModalClosed =
          CommonUtils.getFromSessionStorage('feedback-modal')
        if (!isModalClosed) {
          CatToolActions.openFeedbackModal('', config.revisionNumber)
        }
      }
    }
  }

  useEffect(() => {
    reloadQualityReportFn()
    const updateProgress = (stats) => {
      setProgress(stats)
      checkQualityReport(stats)
    }
    CatToolStore.addListener(CattoolConstants.SET_PROGRESS, updateProgress)
    CatToolStore.addListener(CattoolConstants.RELOAD_QR, reloadQualityReportFn)
    return () => {
      CatToolStore.removeListener(CattoolConstants.SET_PROGRESS, updateProgress)
      CatToolStore.removeListener(
        CattoolConstants.RELOAD_QR,
        reloadQualityReportFn,
      )
    }
  }, [])

  return (
    <div
      className={'action-submenu ui floating'}
      id="quality-report-button"
      title="Quality Report"
    >
      <div
        id="quality-report"
        className={
          progress && isReview && (revisionNumber === 1 || revisionNumber === 2)
            ? 'ui simple pointing top center floating dropdown'
            : ''
        }
        data-vote={vote}
        data-testid="report-button"
        onClick={() => {
          window.open(quality_report_href.current, '_blank')
        }}
      >
        <IconQR width={30} height={30} />

        {isReview && !feedback && progress && (
          <div className="feedback-alert" />
        )}

        <div className="dropdown-menu-overlay" />
        {progress &&
        isReview &&
        (revisionNumber === 1 || revisionNumber === 2) ? (
          <ul className="menu" id="qualityReportMenu">
            <li className="item">
              <a
                title="Open QR"
                onClick={(event) => {
                  event.stopPropagation()
                  window.open(quality_report_href.current, '_blank')
                }}
              >
                Open QR
              </a>
            </li>
            <li className="item">
              <a title="Revision Feedback" onClick={openFeedbackModal}>
                {!feedback
                  ? `Write feedback (R${revisionNumber})`
                  : `Edit feedback (R${revisionNumber})`}
              </a>
            </li>
          </ul>
        ) : null}
      </div>
    </div>
  )
}
