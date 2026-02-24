import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import Copy from '../icons/Copy'
import {aiAlternartiveTranslations} from '../../api/aiAlternartiveTranslations/aiAlternartiveTranslations'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolStore from '../../stores/CatToolStore'

const restoreMissingWhiteSpace = (original, alternative) => {
  if (original.endsWith(' ') && !alternative.endsWith(' ')) {
    return `${alternative} `
  }
  if (original.endsWith('\n') && !alternative.endsWith('\n')) {
    return `${alternative}\n`
  }
  return alternative
}

const maskTags = (text) => {
  const tagRegex = /<(?:[^"'<>]|"[^"]*"|'[^']*')+>/g

  const tagMap = {}
  let index = 0

  const maskedText = text.replace(tagRegex, (match) => {
    const key = `\uE000TAG_${index++}\uE001`
    tagMap[key] = match
    return key
  })

  return {maskedText, tagMap}
}

const unmaskTags = (text, tagMap) => {
  let result = text
  for (const key in tagMap) {
    result = result.split(key).join(tagMap[key])
  }
  return result
}

const splitWords = (languageCode, text) => {
  if (['th', 'zh-CN', 'zh-TW', 'ja'].includes(languageCode)) {
    const segmenter = new Intl.Segmenter(languageCode, {
      granularity: 'word',
    })

    return [...segmenter.segment(text)]
      .map((s) => s.segment)
      .filter((segment) => segment.trim() !== '')
  } else {
    return text.trim().split(/\s+/).filter(Boolean)
  }
}

const getModifiedWordsRange = ({
  originalSentenceWords,
  alternativeSentenceWords,
}) => {
  let startModified = 0
  const lenOriginal = originalSentenceWords.length
  const lenAlternative = alternativeSentenceWords.length

  // trova il primo cambiamento
  while (
    startModified < lenOriginal &&
    startModified < lenAlternative &&
    originalSentenceWords[startModified] ===
      alternativeSentenceWords[startModified]
  ) {
    startModified++
  }

  // trova l'ultimo cambiamento
  let endOriginal = lenOriginal - 1
  let endModified = lenAlternative - 1

  while (
    endOriginal >= startModified &&
    endModified >= startModified &&
    originalSentenceWords[endOriginal] === alternativeSentenceWords[endModified]
  ) {
    endOriginal--
    endModified--
  }

  // **tag dopo il cambiamento non devono allargare il changed**
  // se il tag compare subito dopo, escludilo
  while (
    endModified >= startModified &&
    alternativeSentenceWords[endModified].startsWith('\uE000TAG_') &&
    endModified > startModified
  ) {
    endModified--
  }

  return {startModified, endModified, endOriginal}
}

const enrichAlternatives = ({
  targetLanguage,
  originalSentence,
  alternatives,
  contextWindowSize = 3,
}) => {
  return alternatives.map(({alternative: rawAlternative, context}) => {
    const alternative = restoreMissingWhiteSpace(
      originalSentence,
      rawAlternative,
    )

    const {maskedText: maskedOriginal} = maskTags(originalSentence)
    const {maskedText: maskedAlternative, tagMap} = maskTags(alternative)

    const originalWords = splitWords(targetLanguage, maskedOriginal)
    const modifiedWords = splitWords(targetLanguage, maskedAlternative)

    const {startModified, endModified, endOriginal} = getModifiedWordsRange({
      originalSentenceWords: originalWords,
      alternativeSentenceWords: modifiedWords,
    })

    const changed = modifiedWords.slice(startModified, endModified + 1)

    const before = modifiedWords.slice(
      Math.max(0, startModified - contextWindowSize),
      startModified,
    )

    const after = modifiedWords.slice(
      endModified + 1,
      endModified + 1 + contextWindowSize,
    )

    const originalDiff = originalWords
      .slice(startModified, endOriginal + 1)
      .join(' ')

    const replacementDiff = changed.join(' ')

    const hasStartEllipsis = startModified - contextWindowSize > 0
    const hasEndEllipsis =
      endModified + 1 + contextWindowSize < modifiedWords.length

    const beforeText = unmaskTags(
      hasStartEllipsis ? ` ...${before.join(' ')}` : before.join(' '),
      tagMap,
    )

    const changedText = unmaskTags(replacementDiff, tagMap)

    const afterText = unmaskTags(
      hasEndEllipsis ? `${after.join(' ')}... ` : after.join(' '),
      tagMap,
    )

    return {
      alternative,
      highlighted: {
        before: beforeText,
        changed: changedText,
        after: afterText,
      },
      context,
      original: unmaskTags(originalDiff, tagMap),
      replacement: changedText,
    }
  })
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

    const requestAlternatives = ({text}) => {
      selectedText = DraftMatecatUtils.excludeSomeTagsFromText(text, [
        'g',
        'bx',
        'ex',
        'x',
      ])

      setAlternatives()

      const decodedSource = segment.segment
      const decodedTarget = segment.translation

      const {contextListBefore, contextListAfter} =
        SegmentUtils.getSegmentContext(segment.sid)

      aiAlternartiveTranslations({
        id_job: segment.id_job,
        password: segment.password,
        idSegment: segment.sid,
        sourceSentence: decodedSource,
        sourceContextSentencesString: contextListBefore
          .map((t) => t)
          .join('\n'),
        targetSentence: decodedTarget,
        targetContextSentencesString: contextListAfter.map((t) => t).join('\n'),
        excerpt: text,
        styleInstructions:
          CatToolStore.getJobMetadata().project.mt_extra.lara_style,
      })
    }

    const receiveAlternatives = ({data}) => {
      if (!data.has_error && Array.isArray(data.message)) {
        const enrichedAlternatives = enrichAlternatives({
          targetLanguage: config.target_code,
          originalSentence: DraftMatecatUtils.excludeSomeTagsFromText(
            segment.translation,
            ['g', 'bx', 'ex', 'x'],
          ),
          alternatives: data.message.map((alternative) => ({
            ...alternative,
            alternative: DraftMatecatUtils.excludeSomeTagsFromText(
              alternative.alternative,
              ['g', 'bx', 'ex', 'x'],
            ),
          })),
        })

        setAlternatives(
          enrichedAlternatives.map(({context, highlighted}, index) => ({
            ...(index === 0 && {
              selectedText: DraftMatecatUtils.transformTagsToHtml(
                `“${selectedText}”`,
                config.isTargetRTL,
              ),
            }),
            alternative: data.message[index].alternative,
            before: DraftMatecatUtils.transformTagsToHtml(
              highlighted.before.length > 0
                ? `${highlighted.before} `
                : highlighted.before,
              config.isTargetRTL,
            ),
            after: DraftMatecatUtils.transformTagsToHtml(
              highlighted.after.length > 0
                ? ` ${highlighted.after}`
                : highlighted.after,
              config.isTargetRTL,
            ),

            changed: DraftMatecatUtils.transformTagsToHtml(
              highlighted.changed,
              config.isTargetRTL,
            ),
            copyToClipboard: highlighted.changed,
            context,
          })),
        )
      } else {
        setAlternatives({
          error:
            typeof data.message === 'string' && data.message !== ''
              ? data.message
              : 'Service currently unavailable. Please try again in a moment.',
          retryCallback: () => requestAlternatives({text: selectedText}),
        })
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
    navigator.clipboard.writeText(alternative)
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
            <p
              dangerouslySetInnerHTML={allowHTML(alternatives[0].selectedText)}
            ></p>
          </div>
          <div className="ai-alternative-options">
            {alternatives.map(
              ({before, after, changed, copyToClipboard, context}, index) => (
                <div key={index}>
                  <div>
                    <p>
                      <span dangerouslySetInnerHTML={allowHTML(before)}></span>
                      <span
                        className="ai-feature-option-alternative-highlight"
                        dangerouslySetInnerHTML={allowHTML(changed)}
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
                    onClick={() => copyAlternative(copyToClipboard)}
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
          {alternatives.error !== 'No alternative translations found.' && (
            <Button
              className="ai-feature-button-retry"
              type={BUTTON_TYPE.DEFAULT}
              mode={BUTTON_MODE.OUTLINE}
              onClick={alternatives.retryCallback}
            >
              Retry
            </Button>
          )}
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
