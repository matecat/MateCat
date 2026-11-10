// Test page for the editor core: a read-only source and an editable target, no backend.
// ?seg=<fixture name> picks the segment; window.__editor is what the Playwright suite drives.
import {createSegmentEditor} from '../core/createSegmentEditor'
import {tokenize} from '../../segmentView/segmentModel'
import {SEGMENTS} from './fixtures'
// The cattool's global tooltip: it owns ::before/::after of every [aria-label] element, the marks have to live with it.
import '../../../../css/sass/commons/_aria-label-tooltip.scss'
import {setLayer} from '../core/layers'
import {chipTokens, setTagState} from '../core/tags'

const params = new URLSearchParams(location.search)
const segment = SEGMENTS[params.get('seg') || 'basic']
const events = []
const record = (side) => (name, payload) => events.push({name, side, payload})

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
