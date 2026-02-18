import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {Button, BUTTON_MODE} from '../common/Button/Button'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import Copy from '../icons/Copy'
import {
  decodePlaceholdersToPlainText,
  encodePlaceholdersToTags,
} from './utils/DraftMatecatUtils/tagUtils'
import {aiAlternartiveTranslations} from '../../api/aiAlternartiveTranslations/aiAlternartiveTranslations'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolStore from '../../stores/CatToolStore'

const getWordsBeforeAndAfter = (html, textPortion, count = 30) => {
  const tokenRegex = /(<[^>]+>)|([^<]+)/g
  let tokens = []
  let match
  while ((match = tokenRegex.exec(html)) !== null) {
    if (match[1]) tokens.push({type: 'tag', value: match[1]})
    else tokens.push({type: 'text', value: match[2]})
  }

  let plainText = ''
  let mapping = []
  tokens.forEach((t, i) => {
    if (t.type === 'text') {
      for (let j = 0; j < t.value.length; j++) {
        mapping.push({tokenIndex: i, charIndex: j})
      }
      plainText += t.value
    }
  })

  const idx = plainText.indexOf(textPortion.replace(/<[^>]+>/g, ''))
  if (idx === -1) return {begin: '', after: ''}

  const beginIdx = Math.max(0, idx - count)
  const afterIdx = Math.min(
    plainText.length,
    idx + textPortion.replace(/<[^>]+>/g, '').length + count,
  )

  function sliceTokens(start, end) {
    if (start >= end) return ''
    const tokenStart = mapping[start].tokenIndex
    const charStart = mapping[start].charIndex
    const tokenEnd = mapping[end - 1].tokenIndex
    const charEnd = mapping[end - 1].charIndex + 1

    let result = ''
    for (let i = tokenStart; i <= tokenEnd; i++) {
      const t = tokens[i]
      if (t.type === 'tag') result += t.value
      else if (i === tokenStart && i === tokenEnd)
        result += t.value.slice(charStart, charEnd)
      else if (i === tokenStart) result += t.value.slice(charStart)
      else if (i === tokenEnd) result += t.value.slice(0, charEnd)
      else result += t.value
    }
    return result
  }

  return {
    begin: (beginIdx > 0 ? '...' : '') + sliceTokens(beginIdx, idx),
    after:
      sliceTokens(idx + textPortion.replace(/<[^>]+>/g, '').length, afterIdx) +
      (afterIdx < plainText.length ? '...' : ''),
  }
}

export const SegmentFooterTabAiAlternatives = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [alternatives, setAlternatives] = useState()

  useEffect(() => {
    let selectedText = ''

    const cleanTags = (value) =>
      DraftMatecatUtils.excludeSomeTagsTransformToText(value, [
        'g',
        'bx',
        'ex',
        'x',
      ])

    const requestAlternatives = ({text}) => {
      setAlternatives()

      selectedText = text

      const decodedSource = cleanTags(segment.segment)
      const decodedTarget = cleanTags(segment.translation)

      const {contextListBefore, contextListAfter} =
        SegmentUtils.getSegmentContext(segment.sid)

      aiAlternartiveTranslations({
        idSegment: segment.sid,
        sourceSentence: decodedSource,
        sourceContextSentencesString: contextListBefore
          .map((t) => cleanTags(t))
          .join('\n'),
        targetSentence: decodedTarget,
        targetContextSentencesString: contextListAfter
          .map((t) => cleanTags(t))
          .join('\n'),
        excerpt: cleanTags(text),
        styleInstructions:
          CatToolStore.getJobMetadata().project.mt_extra.lara_style,
      })
    }

    const receiveAlternatives = ({data}) => {
      console.log(data)
      if (!data.has_error && Array.isArray(data.message)) {
        const wordsBeforeAndAfter = getWordsBeforeAndAfter(
          segment.translation,
          selectedText,
          15,
        )

        const begin = DraftMatecatUtils.transformTagsToHtml(
          wordsBeforeAndAfter.begin,
          config.isTargetRTL,
        )
        const after = DraftMatecatUtils.transformTagsToHtml(
          wordsBeforeAndAfter.after,
          config.isTargetRTL,
        )

        setAlternatives(
          data.message.map(({alternative, context}) => ({
            alternativeOriginal: alternative,
            alternative: DraftMatecatUtils.transformTagsToHtml(
              alternative,
              config.isTargetRTL,
            ),
            begin,
            after,
            context,
          })),
        )
      } else {
        setAlternatives({error: 'Error'})
      }
    }

    SegmentStore.addListener(
      SegmentConstants.AI_ALTERNATIVES,
      requestAlternatives,
    )
    SegmentStore.addListener(
      SegmentConstants.AI_ALTERNATIVES_SUGGESTION,
      receiveAlternatives,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.AI_ALTERNATIVES,
        requestAlternatives,
      )
      SegmentStore.addListener(
        SegmentConstants.AI_ALTERNATIVES_SUGGESTION,
        receiveAlternatives,
      )
    }
  }, [segment])

  const copyAlternative = (alternative) => {
    navigator.clipboard.writeText(encodePlaceholdersToTags(alternative))
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
      {alternatives?.length ? (
        <div className="ai-feature-content">
          <div className="ai-feature-alternatives-for">
            <h4>Alternatives for:</h4>
            <p dangerouslySetInnerHTML={allowHTML(alternatives[0].text)}></p>
          </div>
          <div className="ai-alternative-options">
            {alternatives.map(
              (
                {begin, after, alternative, context, alternativeOriginal},
                index,
              ) => (
                <div key={index}>
                  <div>
                    <p>
                      <span dangerouslySetInnerHTML={allowHTML(begin)}></span>
                      <span
                        className="ai-feature-option-alternative-highlight"
                        dangerouslySetInnerHTML={allowHTML(alternative)}
                      ></span>
                      <span dangerouslySetInnerHTML={allowHTML(after)}></span>
                    </p>
                    <p className="ai-feature-option-alternative-description">
                      {context}{' '}
                    </p>
                  </div>
                  <Button
                    className="ai-feature-button"
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={() => copyAlternative(alternativeOriginal)}
                  >
                    <Copy size={16} />
                  </Button>
                </div>
              ),
            )}
          </div>
        </div>
      ) : alternatives?.error ? (
        <div className="ai-feature-content">
          <p>{alternatives.error}</p>
        </div>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabAiAlternatives.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
