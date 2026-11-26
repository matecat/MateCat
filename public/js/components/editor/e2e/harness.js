// Test page for the editor core: a read-only source and an editable target, no backend.
// ?seg=<fixture name> picks the segment; window.__editor is what the Playwright suite drives.
import {createSegmentEditor} from '../core/createSegmentEditor'
import {tokenize} from '../../segmentView/segmentModel'
import {SEGMENTS} from './fixtures'
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
})

// A host shortcut such as Ctrl/Cmd+Enter must reach the page.
document.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    events.push({name: 'hostKey', side: null, payload: 'Mod-Enter'})
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
