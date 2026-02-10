import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {
  decodePlaceholdersToPlainText,
  encodePlaceholdersToTags,
} from './utils/DraftMatecatUtils/tagUtils'
import CatToolStore from '../../stores/CatToolStore'
import {laraTranslate} from '../../api/laraTranslate'
import {laraAuth} from '../../api/laraAuth'
import SegmentUtils from '../../utils/segmentUtils'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentActions from '../../actions/SegmentActions'
import {Button, BUTTON_MODE} from '../common/Button/Button'
import SwitchHorizontal from '../../../img/icons/SwitchHorizontal'
import TranslationMatches from './utils/translationMatches'
import {getContributions} from '../../api/getContributions'
import {SegmentContext} from './SegmentContext'
import CatToolActions from '../../actions/CatToolActions'

export const SegmentFooterTabLaraStyles = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const {multiMatchLangs} = useContext(SegmentContext)

  const [translationStyles, setTranslationStyles] = useState()

  useEffect(() => {
    const requestStyle = ({sid, styles}) => {
      setTranslationStyles()

      const currentSegment = segment

      const {contextListBefore, contextListAfter} =
        SegmentUtils.getSegmentContext(sid)

      laraAuth({
        idJob: config.id_job,
        password: config.password,
        reasoning: false,
      }).then((response) => {
        const jobMetadata = CatToolStore.getJobMetadata()
        const glossaries = jobMetadata?.project?.mt_extra?.lara_glossaries || []
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
          isDefault && segment.translation
            ? Promise.resolve({
                translation: [{text: segment.translation, translatable: true}],
              })
            : laraTranslate({...requestLaraParams, style: id}),
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
            setTranslationStyles({
              error: (
                <>
                  Lara couldn't generate translations in different styles for
                  this segment. Please try again in a moment.
                </>
              ),
            })
          })
      })
    }

    SegmentStore.addListener(SegmentConstants.LARA_STYLES, requestStyle)

    return () =>
      SegmentStore.removeListener(SegmentConstants.LARA_STYLES, requestStyle)
  }, [segment])

  const switchStyle = ({style, translationOriginal}) => {
    SegmentActions.setFocusOnEditArea()
    SegmentActions.disableTPOnSegment(segment)

    setTimeout(() => {
      SegmentActions.replaceEditAreaTextContent(
        segment.sid,
        translationOriginal,
      )
    }, 200)

    const {contextListBefore, contextListAfter} =
      SegmentUtils.getSegmentContext(segment.sid)

    getContributions({
      idSegment: segment.sid,
      target: segment.segment,
      translation: encodePlaceholdersToTags(translationOriginal),
      crossLanguages: multiMatchLangs
        ? [multiMatchLangs.primary, multiMatchLangs.secondary]
        : [],
      contextListBefore,
      contextListAfter,
      laraStyle: style.id,
      reasoning: false,
    })
      .then(() => {
        // Remove from waiting list
        if (
          TranslationMatches.segmentsWaitingForContributions.indexOf(
            segment.sid,
          ) > -1
        ) {
          TranslationMatches.segmentsWaitingForContributions.splice(
            TranslationMatches.segmentsWaitingForContributions.indexOf(
              segment.sid,
            ),
            1,
          )
        }
      })
      .catch((errors) => {
        CatToolActions.processErrors(errors, 'getContribution')
        TranslationMatches.renderContributionErrors(errors, segment.sid)
      })
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
        <div className="ai-feature-content">
          <div className="ai-feature-options">
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
                    className="ai-feature-button"
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={() => switchStyle({style, translationOriginal})}
                  >
                    <SwitchHorizontal size={16} />
                  </Button>
                </div>
              ),
            )}
          </div>
        </div>
      ) : translationStyles?.error ? (
        <div className="ai-feature-content">
          <p>{translationStyles.error}</p>
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
