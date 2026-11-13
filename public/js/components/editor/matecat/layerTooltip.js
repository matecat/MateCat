// Hover tooltips of the source layers (what GlossaryHighlight, QaCheckGlossaryHighlight and LexiqaHighlight show in
// Draft), and the click on a glossary term that opens it in the footer glossary tab.
import React from 'react'
import {createRoot} from 'react-dom/client'
import {EditorView, hoverTooltip} from '@codemirror/view'
import {layerRangesAt} from '../core/layers'
import LexiqaTooltipInfo from '../../segments/TooltipInfo/LexiqaTooltipInfo.component'
import {highlightGlossaryTerm} from '../../../actions/segmentDispatchActions'

const TITLES = {
  glossary: 'Termbase entry',
  check: 'Termbase translation not found in target',
}

/** The tooltip content for the layer ranges at a position, or null. Lexiqa first: its tooltip is interactive. */
export const tooltipContent = (ranges) => {
  const lexiqa = ranges.find(({data}) => data?.type === 'lexiqa')
  if (lexiqa?.data.messages?.length) {
    return {
      range: lexiqa,
      element: (
        <LexiqaTooltipInfo
          messages={lexiqa.data.messages}
          // The source cannot be edited: a suggestion is not applied to it
          onReplaceWord={() => {}}
        />
      ),
    }
  }
  const term = ranges.find(({data}) => TITLES[data?.type])
  if (term) return {range: term, element: <span>{TITLES[term.data.type]}</span>}
  return null
}

const tooltipTheme = EditorView.baseTheme({
  '.cm-tooltip.cm-layer-tooltip': {
    border: 'none',
    borderRadius: '4px',
    padding: '5px 10px',
    backgroundColor: '#fff',
    boxShadow: '0 0 16px rgba(0, 0, 0, 0.25)',
    maxWidth: '600px',
    whiteSpace: 'normal',
    textAlign: 'left',
  },
})

/** The hover tooltip at `pos`: the layer range it explains, its content rendered by React. */
export const layerTooltipAt = (view, pos) => {
  const content = tooltipContent(layerRangesAt(view.state, pos))
  if (!content) return null
  return {
    pos: content.range.from,
    end: content.range.to,
    above: true,
    create() {
      const dom = document.createElement('div')
      dom.className = 'cm-layer-tooltip'
      const root = createRoot(dom)
      root.render(content.element)
      return {
        dom,
        // Unmounting while React is rendering warns: wait for the current task to end.
        destroy: () => setTimeout(() => root.unmount()),
      }
    },
  }
}

const layerHover = hoverTooltip(layerTooltipAt, {hoverTime: 300})

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

export const layerTooltipExtensions = () => [
  layerHover,
  tooltipTheme,
  glossaryClick,
]
