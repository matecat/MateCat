// Range decorations computed outside the document (search hits, glossary terms, Lexiqa warnings, ICU tokens), one
// named layer each. A layer is replaced whole by `setLayer` and mapped through edits until then. The `search` layer is
// exclusive: while it has ranges, the other layers are hidden.
import {RangeSet, StateEffect, StateField, Transaction} from '@codemirror/state'
import {Decoration, EditorView, ViewPlugin} from '@codemirror/view'
import {insertContent, visibleDoc} from './docModel'

export const SEARCH_LAYER = 'search'

/** The attribute naming the layer of a mark element, which hover tooltips find the mark by. */
export const LAYER_ATTRIBUTE = 'data-layer'

/**
 * Replaces the ranges of one layer: `{name, ranges, volatile?}`, each range `{from, to, className, tagName?,
 * attributes?, data?}` in document positions. `data` is kept on the decoration for the host (tooltips, clicks). A
 * `volatile` layer describes text as it was checked (Lexiqa): a user edit touching one of its ranges drops that range,
 * the others are mapped.
 */
export const setLayer = StateEffect.define()

const markFor = (name, {className, tagName, attributes, data}) =>
  Decoration.mark({
    class: className,
    ...(tagName ? {tagName} : {}),
    attributes: {...attributes, [LAYER_ATTRIBUTE]: name},
    data,
  })

// Whether a change of `changes` replaces or touches from..to (positions before the change).
const touches = (changes, from, to) => {
  let hit = false
  changes.iterChangedRanges((fromA, toA) => {
    if (fromA <= to && toA >= from) hit = true
  })
  return hit
}

// A user edit, not an IME composition: a range dropped mid-composition would redraw the text being composed.
const isUserEdit = (tr) => {
  const event = tr.annotation(Transaction.userEvent)
  return Boolean(event) && !event.startsWith('input.type.compose')
}

/** Layer name -> `{set, volatile}`. */
export const layers = StateField.define({
  create: () => new Map(),
  update(value, tr) {
    let next = value
    if (tr.docChanged) {
      next = new Map()
      const byUser = isUserEdit(tr)
      value.forEach(({set, volatile}, name) => {
        const kept =
          volatile && byUser
            ? set.update({filter: (from, to) => !touches(tr.changes, from, to)})
            : set
        next.set(name, {set: kept.map(tr.changes), volatile})
      })
    }
    for (const effect of tr.effects) {
      if (!effect.is(setLayer)) continue
      if (next === value) next = new Map(value)
      const {name, ranges, volatile = false} = effect.value
      next.set(name, {
        set: Decoration.set(
          ranges
            .filter((r) => r.to > r.from)
            .map((r) => markFor(name, r).range(r.from, r.to)),
          true,
        ),
        volatile,
      })
    }
    return next
  },
  provide: (field) =>
    EditorView.decorations.compute([field], (state) => {
      const all = state.field(field)
      const search = all.get(SEARCH_LAYER)
      if (search && search.set.size) return search.set
      return RangeSet.join([...all.values()].map(({set}) => set))
    }),
})

/** Whether layer `name` is hidden by the search layer. */
export const isLayerHidden = (state, name) =>
  name !== SEARCH_LAYER &&
  Boolean(state.field(layers).get(SEARCH_LAYER)?.set.size)

/** The layer ranges covering `pos`, as `{name, from, to, data}`. A hidden layer still answers. */
export const layerRangesAt = (state, pos) => {
  const out = []
  state.field(layers).forEach(({set}, name) => {
    set.between(pos, pos, (from, to, mark) => {
      if (from <= pos && pos <= to)
        out.push({name, from, to, data: mark.spec.data})
    })
  })
  return out
}

/**
 * Turns ranges over `visibleDoc().text` ({start, end, …}) into document ranges ({from, to, …}): the offsets the
 * segment view and its marks use mark the same text in the editor.
 */
export const visibleToDoc = (state, marks) => {
  const {pos} = visibleDoc(state)
  const at = (i) => (i < pos.length ? pos[i] : state.doc.length)
  return marks
    .filter(({start, end}) => end > start && start < pos.length)
    .map(({start, end, ...rest}) => ({...rest, from: at(start), to: at(end)}))
}

/**
 * Replaces the text a layer range covers (`{from, to}`) with plain `text`, as a user edit: a Lexiqa suggestion. The
 * range is a document range, so the right line is replaced in a multi-line target. False when the editor is read-only.
 */
export const replaceLayerRange = (view, {from, to}, text) => {
  if (view.state.readOnly) return false
  view.dispatch(insertContent(view.state, from, to, {text, tags: []}), {
    userEvent: 'input.replace',
  })
  return true
}

// Layer updates asked for while an IME composition runs: dispatched once it has ended.
const deferredLayers = ViewPlugin.fromClass(
  class {
    constructor(view) {
      this.view = view
      this.compute = null
      this.timer = null
    }

    defer(compute) {
      this.compute = compute
    }

    flush() {
      clearTimeout(this.timer)
      this.timer = null
      if (!this.compute) return
      // compositionend can come before the composition's last input: wait for the view to leave it.
      if (this.view.composing) {
        this.timer = setTimeout(() => this.flush(), 50)
        return
      }
      const compute = this.compute
      this.compute = null
      applyLayers(this.view, compute)
    }

    destroy() {
      clearTimeout(this.timer)
      this.compute = null
    }
  },
  {
    eventHandlers: {
      compositionend() {
        clearTimeout(this.timer)
        this.timer = setTimeout(() => this.flush())
      },
    },
  },
)

/**
 * Dispatches the effects `compute(state)` returns (setLayer and the like), except while an IME composition runs: a
 * decoration redrawn under the text being composed can break the composition, so the latest `compute` waits for
 * compositionend and runs then, on the state of that moment. True when dispatched now.
 */
export const applyLayers = (view, compute) => {
  const plugin = view.plugin(deferredLayers)
  if (view.composing && plugin) {
    plugin.defer(compute)
    return false
  }
  plugin?.defer(null)
  const effects = compute(view.state)
  if (effects.length) view.dispatch({effects})
  return true
}

export const layerExtensions = () => [layers, deferredLayers]
