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
import {Button, BUTTON_MODE} from '../common/Button/Button'
import SwitchHorizontal from '../../../img/icons/SwitchHorizontal'

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

          const promises = styles.map(({id, isDefault}) =>
            !isDefault
              ? laraTranslate({...requestLaraParams, style: id})
              : Promise.resolve({
                  translation: [
                    {text: segment.original_translation, translatable: true},
                  ],
                }),
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

  const switchStyle = (translation) => {
    SegmentActions.setFocusOnEditArea()
    SegmentActions.disableTPOnSegment(segment)

    setTimeout(() => {
      SegmentActions.replaceEditAreaTextContent(segment.sid, translation)
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
          <div className="lara-styles-options">
            {translationStyles.map(
              ({style, translation, translationOriginal}) => (
                <div key={style.id}>
                  <div>
                    <h4>
                      {style.name}{' '}
                      {style.isDefault ? <span>(Original)</span> : ''}
                    </h4>
                    <p dangerouslySetInnerHTML={allowHTML(translation)}></p>
                  </div>
                  <Button
                    className="lara-style-switch-button"
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={() => switchStyle(translationOriginal)}
                  >
                    <SwitchHorizontal size={16} />
                  </Button>
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
