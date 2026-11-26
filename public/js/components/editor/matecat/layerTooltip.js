// Hover tooltips of the editor layers (what GlossaryHighlight, QaCheckGlossaryHighlight, QaCheckBlacklistHighlight,
// LexiqaHighlight and IcuHighlight show in Draft), drawn with common/Tooltip as they are, and the click on a glossary
// term that opens it in the footer glossary tab. The Lexiqa tooltip stays open while the pointer moves into it: its
// suggestions replace the warned text (in the target) and Ignore drops the warning (LXQ.ignoreError).
import React from 'react'
import {createRoot} from 'react-dom/client'
import {EditorView} from '@codemirror/view'
import {layerRangesAt, replaceLayerRange} from '../core/layers'
import {layerHover} from '../core/layerHover'
import Tooltip, {TOOLTIP_MODE} from '../../common/Tooltip'
import LexiqaTooltipInfo from '../../segments/TooltipInfo/LexiqaTooltipInfo.component'
import {highlightGlossaryTerm} from '../../../actions/segmentDispatchActions'
import {blacklistMessage} from './layers/blacklist'

const TITLES = {
  glossary: 'Termbase entry',
  check: 'Termbase translation not found in target',
}

// The height of common/Tooltip's arrow.
const ARROW = 7

/**
 * The tooltip content for the layer ranges at a position, or null. Lexiqa first: its tooltip is interactive.
 * `onReplace(range, text)` applies a Lexiqa suggestion; without it (the source) a suggestion does nothing.
 */
export const tooltipContent = (ranges, {onReplace} = {}) => {
  const lexiqa = ranges.find(({data}) => data?.type === 'lexiqa')
  if (lexiqa?.data.messages?.length) {
    return {
      range: lexiqa,
      element: (
        <LexiqaTooltipInfo
          messages={lexiqa.data.messages}
          onReplaceWord={({newWord}) => onReplace?.(lexiqa, newWord)}
        />
      ),
    }
  }
  const blacklisted = ranges.find(({data}) => data?.type === 'blacklist')
  if (blacklisted) {
    return {
      range: blacklisted,
      element: <span>{blacklistMessage(blacklisted.data.term)}</span>,
    }
  }
  const icuError = ranges.find(({data}) => data?.type === 'icu')
  if (icuError) {
    return {
      range: icuError,
      element: (
        <div className="icu-tooltip">
          <h3>ICU syntax error</h3>
          {icuError.data.messages.map((message) => (
            <p key={message}>{message}</p>
          ))}
        </div>
      ),
    }
  }
  const term = ranges.find(({data}) => TITLES[data?.type])
  if (term) return {range: term, element: <span>{TITLES[term.data.type]}</span>}
  return null
}

/**
 * The tooltip of the hovered ranges for layerHover: common/Tooltip above the hovered mark, rendered by React in its
 * own root. `editable`: Lexiqa suggestions replace the warned text.
 */
export const createLayerTooltip =
  ({editable}) =>
  (view, {ranges, rect, close}) => {
    const content = tooltipContent(ranges, {
      onReplace: editable
        ? (range, text) => {
            close()
            replaceLayerRange(view, range, text)
          }
        : undefined,
    })
    if (!content) return null
    const dom = document.createElement('div')
    dom.className = 'cm-layer-tooltip'
    const root = createRoot(dom)
    root.render(
      <Tooltip
        mode={TOOLTIP_MODE.STATIC}
        isInteractiveContent
        style={{
          top: rect.top + window.scrollY - ARROW,
          left: rect.left + window.scrollX + rect.width / 2,
        }}
        content={content.element}
      />,
    )
    return {
      dom,
      // Unmounting while React is rendering warns: wait for the current task to end.
      destroy: () => setTimeout(() => root.unmount()),
    }
  }

/** A click on a glossary or missing-term highlight shows that term in the footer glossary tab. */
export const glossaryClick = EditorView.domEventHandlers({
  click(event, view) {
    if (!event.target.closest?.('.glossaryItem, .qaCheckGlossaryItem')) {
      return false
    }
    const pos = view.posAtDOM(event.target)
    const term = layerRangesAt(view.state, pos).find(
      ({data}) => TITLES[data?.type] && data.termId !== undefined,
    )
    if (!term) return false
    highlightGlossaryTerm({
      sid: term.data.sid,
      termId: term.data.termId,
      type: term.data.type,
    })
    return false
  },
})

/** `editable`: the target, where a Lexiqa suggestion is applied. */
export const layerTooltipExtensions = ({editable = false} = {}) => [
  layerHover({render: createLayerTooltip({editable})}),
  glossaryClick,
]
