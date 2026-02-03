import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {decodePlaceholdersToPlainText} from './utils/DraftMatecatUtils/tagUtils'
import CatToolStore from '../../stores/CatToolStore'
import {laraTranslate} from '../../api/laraTranslate'
import {laraAuth} from '../../api/laraAuth'
import SegmentUtils from '../../utils/segmentUtils'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentActions from '../../actions/SegmentActions'

export const SegmentFooterTabLaraStyle = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [result, setResult] = useState()

  useEffect(() => {
    const requestStyle = ({sid, value}) => {
      setResult()

      const currentSegment = segment

      const {contextListBefore, contextListAfter} =
        SegmentUtils.getSegmentContext(sid)

      laraAuth({idJob: config.id_job, password: config.password}).then(
        (response) => {
          const jobMetadata = CatToolStore.getJobMetadata()
          const glossaries =
            jobMetadata?.project?.mt_extra?.lara_glossaries || []
          const decodedSource = decodePlaceholdersToPlainText(
            currentSegment.segment,
          )
          laraTranslate({
            token: response.token,
            source: decodedSource,
            contextListBefore: contextListBefore.map((t) =>
              decodePlaceholdersToPlainText(t),
            ),
            contextListAfter: contextListAfter.map((t) =>
              decodePlaceholdersToPlainText(t),
            ),
            sid,
            jobId: config.id_job,
            glossaries,
            style: value,
            reasoning: false,
          })
            .then((response) => {
              console.log(response)
              const {text} = response.translation.find(
                ({translatable}) => translatable,
              )

              setResult({
                source: DraftMatecatUtils.transformTagsToHtml(
                  segment.segment,
                  config.isSourceRTL,
                ),
                target: DraftMatecatUtils.transformTagsToHtml(
                  text,
                  config.isTargetRTL,
                ),
                targetOriginal: text,
              })
            })
            .catch((e) => {
              console.error('Lara Translate error:', e)
            })
        },
      )
    }

    SegmentStore.addListener(SegmentConstants.LARA_STYLE, requestStyle)

    return () =>
      SegmentStore.removeListener(SegmentConstants.LARA_STYLE, requestStyle)
  }, [segment])

  const suggestionDblClick = () => {
    SegmentActions.setFocusOnEditArea()
    SegmentActions.disableTPOnSegment(segment)
    setTimeout(() => {
      SegmentActions.replaceEditAreaTextContent(
        segment.sid,
        result.targetOriginal,
      )
    }, 200)
  }

  const allowHTML = (string) => {
    return {__html: string}
  }

  return (
    <div
      key={`container_ + ${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {result ? (
        <ul
          className="suggestion-item crosslang-item graysmall"
          onDoubleClick={suggestionDblClick}
        >
          <li className="sugg-source">
            <span
              className="suggestion_source"
              dangerouslySetInnerHTML={allowHTML(result.source)}
            ></span>
          </li>
          <li className="b sugg-target">
            <span
              className="translation"
              dangerouslySetInnerHTML={allowHTML(result.target)}
            ></span>
          </li>
        </ul>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabLaraStyle.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
