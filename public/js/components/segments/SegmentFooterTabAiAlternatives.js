import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {Button, BUTTON_MODE} from '../common/Button/Button'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import Copy from '../icons/Copy'
import {encodePlaceholdersToTags} from './utils/DraftMatecatUtils/tagUtils'
import {aiAlternartiveTranslations} from '../../api/aiAlternartiveTranslations/aiAlternartiveTranslations'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolStore from '../../stores/CatToolStore'

const restoreMissingWhiteSpace = (original, alternative) => {
  if (original.endsWith(' ') && !alternative.endsWith(' ')) {
    return `${alternative} `
  }
  return alternative
}

const splitWords = (languageCode, text) => {
  if (['th', 'zh-CN', 'zh-TW', 'ja'].includes(languageCode)) {
    const segmenter = new Intl.Segmenter(languageCode, {granularity: 'word'})
    return [...segmenter.segment(text)]
      .map((s) => s.segment)
      .filter((segment) => segment.trim() !== '')
  } else {
    return text.trim().split(/\s+/)
  }
}

const getModifiedWordsRange = ({
  originalSentenceWords,
  alternativeSentenceWords,
}) => {
  let startModified = 0
  while (
    startModified < originalSentenceWords.length &&
    startModified < alternativeSentenceWords.length &&
    originalSentenceWords[startModified] ===
      alternativeSentenceWords[startModified]
  ) {
    startModified++
  }

  let endOriginal = originalSentenceWords.length - 1
  let endModified = alternativeSentenceWords.length - 1

  while (
    endOriginal >= startModified &&
    endModified >= startModified &&
    originalSentenceWords[endOriginal] === alternativeSentenceWords[endModified]
  ) {
    endOriginal--
    endModified--
  }

  return {
    startModified,
    endModified,
    endOriginal,
  }
}

const enrichAlternatives = ({
  targetLanguage,
  originalSentence,
  alternatives,
  contextWindowSize = 3,
}) => {
  const originalWords = splitWords(targetLanguage, originalSentence)

  return alternatives.map(({alternative: _alternative, context}) => {
    const alternative = restoreMissingWhiteSpace(originalSentence, _alternative)
    const modifiedWords = splitWords(targetLanguage, alternative)

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

    return {
      alternative:
        originalSentence.endsWith(' ') && !alternative.endsWith(' ')
          ? `${alternative} `
          : originalSentence.endsWith('\n') && !alternative.endsWith('\n')
            ? `${alternative}\n`
            : alternative,
      highlighted: {
        before: hasStartEllipsis ? ` ...${before.join(' ')}` : before.join(' '),
        changed: replacementDiff,
        after: hasEndEllipsis ? `${after.join(' ')}... ` : after.join(' '),
      },
      context,
      original: originalDiff,
      replacement: replacementDiff,
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
    const cleanTags = (value) =>
      DraftMatecatUtils.excludeSomeTagsTransformToText(value, [
        'g',
        'bx',
        'ex',
        'x',
      ]).replace(/·/g, ' ')

    const requestAlternatives = ({text}) => {
      setAlternatives()

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
      if (!data.has_error && Array.isArray(data.message)) {
        console.log(data.message)
        const enrichedAlternatives = enrichAlternatives({
          targetLanguage: config.target_code,
          originalSentence: cleanTags(segment.translation),
          alternatives: data.message,
        })

        return

        setAlternatives(
          data.message.map(({alternative, context, highlighted}) => ({
            alternative,
            before:
              highlighted.before.length > 0
                ? `${highlighted.before} `
                : highlighted.before,
            after:
              highlighted.after.length > 0
                ? ` ${highlighted.after}`
                : highlighted.after,
            changed: highlighted.changed,
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
              ({before, after, changed, alternative, context}, index) => (
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
                    onClick={() => copyAlternative(alternative)}
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
