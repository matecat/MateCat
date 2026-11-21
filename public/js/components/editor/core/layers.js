// Range decorations computed outside the document (search hits, glossary terms, Lexiqa warnings, ICU tokens), one
// named layer each. A layer is replaced whole by `setLayer` and mapped through edits until then. The `search` layer is
// exclusive: while it has ranges, the other layers are hidden.
import {RangeSet, StateEffect, StateField} from '@codemirror/state'
import {Decoration, EditorView} from '@codemirror/view'
import {visibleDoc} from './docModel'

export const SEARCH_LAYER = 'search'

/**
 * Replaces the ranges of one layer: `{name, ranges}`, each range `{from, to, className, tagName?, attributes?,
 * data?}` in document positions. `data` is kept on the decoration for the host (tooltips, clicks).
 */
export const setLayer = StateEffect.define()

const markFor = ({className, tagName, attributes, data}) =>
  Decoration.mark({
    class: className,
    ...(tagName ? {tagName} : {}),
    ...(attributes ? {attributes} : {}),
    data,
  })

/** Layer name -> its DecorationSet. */
export const layers = StateField.define({
  create: () => new Map(),
  update(value, tr) {
    let next = value
    if (tr.docChanged) {
      next = new Map()
      value.forEach((set, name) => next.set(name, set.map(tr.changes)))
    }
    for (const effect of tr.effects) {
      if (!effect.is(setLayer)) continue
      if (next === value) next = new Map(value)
      const {name, ranges} = effect.value
      next.set(
        name,
        Decoration.set(
          ranges
            .filter((r) => r.to > r.from)
            .map((r) => markFor(r).range(r.from, r.to)),
          true,
        ),
      )
    }
    return next
  },
  provide: (field) =>
    EditorView.decorations.compute([field], (state) => {
      const all = state.field(field)
      const search = all.get(SEARCH_LAYER)
      if (search && search.size) return search
      return RangeSet.join([...all.values()])
    }),
})

/** The layer ranges covering `pos`, as `{name, from, to, data}`. A hidden layer still answers. */
export const layerRangesAt = (state, pos) => {
  const out = []
  state.field(layers).forEach((set, name) => {
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

export const layerExtensions = () => [layers]
