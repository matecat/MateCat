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
import {Badge, BADGE_TYPE} from '../common/Badge/Badge'
import {LARA_STYLES_OPTIONS} from '../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'

export const SegmentFooterTabLaraStyles = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [translationStyles, setTranslationStyles] = useState()

  useEffect(() => {
    const requestStyle = ({sid, styles}) => {
      setTranslationStyles()

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

          const requestLaraParams = {
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
            reasoning: false,
          }

          const promises = styles.map(({id}) =>
            laraTranslate({...requestLaraParams, style: id}),
          )

          Promise.all(promises)
            .then((values) => {
              const translations = values.map(
                ({translation}) =>
                  translation.find(({translatable}) => translatable).text,
              )

              setTranslationStyles(
                translations.map((value, index) => {
                  return {
                    translation: DraftMatecatUtils.transformTagsToHtml(
                      value,
                      config.isTargetRTL,
                    ),
                    translationOriginal: value,
                    style: styles[index],
                  }
                }),
              )
            })
            .catch((e) => {
              console.error('Lara Translate error:', e)
            })
        },
      )
    }

    SegmentStore.addListener(SegmentConstants.LARA_STYLES, requestStyle)

    return () =>
      SegmentStore.removeListener(SegmentConstants.LARA_STYLES, requestStyle)
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
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {translationStyles?.length ? (
        <div className="lara-styles-content">
          <div>
            <h4>
              Original style:{' '}
              {
                LARA_STYLES_OPTIONS.find(
                  ({id}) =>
                    id === CatToolStore.getJobMetadata().project.lara_style,
                ).name
              }
            </h4>
            <p
              dangerouslySetInnerHTML={allowHTML(
                DraftMatecatUtils.transformTagsToHtml(
                  segment.original_translation,
                  config.isTargetRTL,
                ),
              )}
            ></p>
          </div>
          <div className="lara-styles-options">
            {translationStyles.map(
              ({style, translation, translationOriginal}) => (
                <div key={style.id}>
                  <h4>Style: {style.name}</h4>
                  <p dangerouslySetInnerHTML={allowHTML(translation)}></p>
                </div>
              ),
            )}
          </div>
        </div>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabLaraStyles.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
