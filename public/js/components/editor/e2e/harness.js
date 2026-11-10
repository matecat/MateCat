// Test page for the editor core: a read-only source and an editable target, no backend.
// ?seg=<fixture name> picks the segment; window.__editor is what the Playwright suite drives.
import {createSegmentEditor} from '../core/createSegmentEditor'
import {tokenize} from '../../segmentView/segmentModel'
import {SEGMENTS} from './fixtures'
// The cattool's global tooltip: it owns ::before/::after of every [aria-label] element, the marks have to live with it.
import '../../../../css/sass/commons/_aria-label-tooltip.scss'
// The marks of the special characters: the styles the editor shares with SegmentView.
import '../../../../css/sass/components/segment/SpecialCharMarks.scss'
// The cattool chip styles: trimming (ellipsis) and tag compaction are measured against them.
import '../../../../css/sass/components/segment/Tag.scss'
import {
  applyLayers,
  layerRangesAt,
  layers as layersField,
  replaceLayerRange,
  setLayer,
} from '../core/layers'
import {layerHover} from '../core/layerHover'
import {chipTokens, setTagState} from '../core/tags'

const params = new URLSearchParams(location.search)
const segment = SEGMENTS[params.get('seg') || 'basic']
const events = []
const record = (side) => (name, payload) => events.push({name, side, payload})

// The hover tooltip of the layer marks, as the Matecat editors use it: a Lexiqa-like range (`data.suggestions`) gets
// one button per suggestion, which replaces the range, and an Ignore button, which drops that range only.
const GAP = 6
const renderTooltip = (view, {ranges, rect, close}) => {
  const range = ranges.find((r) => r.data?.suggestions)
  if (!range) return null
  const dom = document.createElement('div')
  dom.className = 'harness-tooltip'
  Object.assign(dom.style, {
    position: 'absolute',
    top: `${rect.top + window.scrollY - GAP}px`,
    left: `${rect.left + window.scrollX}px`,
    transform: 'translateY(-100%)',
  })
  const button = (className, text, onClick) => {
    const el = dom.appendChild(document.createElement('button'))
    el.className = className
    el.textContent = text
    el.addEventListener('click', onClick)
  }
  range.data.suggestions.forEach((text) =>
    button('harness-suggestion', text, () => {
      events.push({name: 'tooltipAction', payload: {id: range.data.id, text}})
      close()
      replaceLayerRange(view, range, text)
    }),
  )
  button('harness-ignore', 'Ignore', () => {
    events.push({
      name: 'tooltipAction',
      payload: {id: range.data.id, action: 'ignore'},
    })
    const kept = []
    view.state
      .field(layersField)
      .get(range.name)
      .set.between(0, view.state.doc.length, (from, to, mark) => {
        if (mark.spec.data?.id !== range.data.id)
          kept.push({
            from,
            to,
            className: mark.spec.class,
            data: mark.spec.data,
          })
      })
    view.dispatch({effects: setLayer.of({name: range.name, ranges: kept})})
  })
  return {dom}
}

const source = createSegmentEditor({
  parent: document.querySelector('[data-testid=source]'),
  value: segment.source,
  mode: 'source',
  lang: segment.lang.source,
  dir: 'ltr',
  onEvent: record('source'),
})
const target = createSegmentEditor({
  parent: document.querySelector('[data-testid=target]'),
  value: segment.target,
  mode: 'target',
  lang: segment.lang.target,
  dir: segment.dir,
  showWhitespace: params.has('whitespace'),
  onChange: (value) =>
    events.push({name: 'change', side: 'target', payload: value}),
  onEvent: record('target'),
  extensions: [layerHover({render: renderTooltip})],
  // ?notags: a segment with no source tags to offer, the tag menu stays shut
  tagMenu: {
    canOpen: () => !params.has('notags'),
    onChange: (menu) =>
      events.push({name: 'tagMenu', side: 'target', payload: menu}),
    onKey: (key) =>
      events.push({name: 'tagMenuKey', side: 'target', payload: key}),
  },
})

// Host shortcuts (Ctrl/Cmd+Enter, add issue, Ctrl+K) must reach the page.
document.addEventListener('keydown', (e) => {
  const name =
    (e.key === 'Enter' && e.ctrlKey && e.altKey && 'Ctrl-Alt-Enter') ||
    (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && 'Mod-Enter') ||
    (e.key === 'k' && e.ctrlKey && 'Ctrl-k')
  if (name) {
    events.push({
      name: 'hostKey',
      side: null,
      payload: name,
      defaultPrevented: e.defaultPrevented,
    })
  }
})

window.__editor = {
  ready: true,
  segment,
  events,
  source,
  target,
  tokenize,
  /** Puts the caret of a pane at a document position (pane: 'source' | 'target'). */
  caret(pane, pos) {
    const editor = pane === 'source' ? source : target
    editor.view.focus()
    editor.view.dispatch({selection: {anchor: pos}})
  },
  select(pane, from, to) {
    const editor = pane === 'source' ? source : target
    editor.view.focus()
    editor.view.dispatch({selection: {anchor: from, head: to}})
  },
  /** Replaces a decoration layer of a pane: ranges `{from, to, className, tagName?}`. */
  layer(pane, name, ranges) {
    const editor = pane === 'source' ? source : target
    editor.view.dispatch({effects: setLayer.of({name, ranges})})
  },
  /**
   * Marks `word` of the target in layer `name` through applyLayers, as the Matecat editors update their layers: the
   * position is read when the update is dispatched, which waits for an IME composition to end.
   */
  layerOnWord(name, word, className) {
    return applyLayers(target.view, (state) => {
      const from = state.doc.toString().indexOf(word)
      return [
        setLayer.of({
          name,
          ranges: from < 0 ? [] : [{from, to: from + word.length, className}],
        }),
      ]
    })
  },
  /** The layer ranges of the target at a document position, as `{name, from, to}`. */
  rangesAt: (pos) =>
    layerRangesAt(target.view.state, pos).map(({name, from, to}) => ({
      name,
      from,
      to,
    })),
  /** Gives the n-th chips of a pane an extra class: `[[n, className]]`. */
  tagState(pane, list) {
    const editor = pane === 'source' ? source : target
    const chips = chipTokens(editor.view.state)
    editor.view.dispatch({
      effects: setTagState.of(
        list.map(([n, className]) => ({...chips[n], className})),
      ),
    })
  },
  doc: (pane) =>
    (pane === 'source' ? source : target).view.state.doc.toString(),
}
