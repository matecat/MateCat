import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {Button, BUTTON_MODE} from '../common/Button/Button'
import SwitchHorizontal from '../../../img/icons/SwitchHorizontal'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import Copy from '../icons/Copy'
import {encodePlaceholdersToTags} from './utils/DraftMatecatUtils/tagUtils'

export const SegmentFooterTabAiAlternatives = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [alternatives, setAlternatives] = useState()

  useEffect(() => {
    const requestAlternatives = ({sid, text}) => {
      setAlternatives()
      console.log(text)
      const currentSegment = segment

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
            sliceTokens(
              idx + textPortion.replace(/<[^>]+>/g, '').length,
              afterIdx,
            ) + (afterIdx < plainText.length ? '...' : ''),
        }
      }

      const wordsBeforeAndAfter = getWordsBeforeAndAfter(
        currentSegment.translation,
        text,
        15,
      )
      console.log('wordsBeforeAndAfter', wordsBeforeAndAfter)
      const begin = DraftMatecatUtils.transformTagsToHtml(
        wordsBeforeAndAfter.begin,
        config.isTargetRTL,
      )
      const after = DraftMatecatUtils.transformTagsToHtml(
        wordsBeforeAndAfter.after,
        config.isTargetRTL,
      )

      setAlternatives([
        {
          text: DraftMatecatUtils.transformTagsToHtml(text, config.isTargetRTL),
          alternativeOriginal: DraftMatecatUtils.transformTagsToHtml(
            text,
            config.isTargetRTL,
          ),
          alternative: DraftMatecatUtils.transformTagsToHtml(
            text,
            config.isTargetRTL,
          ),
          begin,
          after,
          description: 'Lorem ipsum bla bla',
        },
        {
          alternativeOriginal: text,
          alternative: DraftMatecatUtils.transformTagsToHtml(
            text,
            config.isTargetRTL,
          ),
          begin,
          after,
          description: 'Lorem ipsum bla bla2',
        },
        {
          alternativeOriginal: text,
          alternative: DraftMatecatUtils.transformTagsToHtml(
            text,
            config.isTargetRTL,
          ),
          begin,
          after,
          description: 'Lorem ipsum bla bla3',
        },
      ])
    }

    SegmentStore.addListener(
      SegmentConstants.AI_ALTERNATIVES,
      requestAlternatives,
    )

    return () =>
      SegmentStore.removeListener(
        SegmentConstants.AI_ALTERNATIVES,
        requestAlternatives,
      )
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
            {alternatives.map((alternative, index) => (
              <div key={index}>
                <div>
                  <p>
                    <span
                      dangerouslySetInnerHTML={allowHTML(alternative.begin)}
                    ></span>
                    <span
                      className="ai-feature-option-alternative-highlight"
                      dangerouslySetInnerHTML={allowHTML(
                        alternative.alternative,
                      )}
                    ></span>
                    <span
                      dangerouslySetInnerHTML={allowHTML(alternative.after)}
                    ></span>
                  </p>
                  <p className="ai-feature-option-alternative-description">
                    {alternative.description}{' '}
                  </p>
                </div>
                <Button
                  className="ai-feature-button"
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={() =>
                    copyAlternative(alternative.alternativeOriginal)
                  }
                >
                  <Copy size={16} />
                </Button>
              </div>
            ))}
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
