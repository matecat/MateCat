// Tag chips: one atomic replace widget per tag range, plain DOM with the classes and labels of the cattool tags
// (segmentModel `tokenize`), plus the filter that keeps user edits from cutting through a tag.
import {
  EditorState,
  Facet,
  StateEffect,
  StateField,
  Transaction,
} from '@codemirror/state'
import {Decoration, EditorView, WidgetType} from '@codemirror/view'
import {addTags, locateTags, serializeWithOffsets, tagRanges} from './docModel'

/** Receives chip events: `(name, payload)`. */
export const tagEventSink = Facet.define({combine: (values) => values[0]})

/** Text direction of the editor, for the open/close chip shapes. */
export const tagDirection = Facet.define({
  combine: (values) => values[0] ?? 'ltr',
})

export const TAG_SELECTOR = '.tag'

/**
 * Replaces the extra classes of the chips (`tag-clicked`, `tag-focused`…): a list of `{from, to, className}`, one
 * per chip, by the chip's document range. Kept until the next one, mapped through changes.
 */
export const setTagState = StateEffect.define()

const stateMark = (className) => Decoration.mark({className})

/** The extra classes of the chips, as marks on their ranges. */
export const tagState = StateField.define({
  create: () => Decoration.none,
  update(value, tr) {
    let next = value.map(tr.changes)
    for (const effect of tr.effects) {
      if (effect.is(setTagState)) {
        next = Decoration.set(
          effect.value
            .filter((r) => r.to > r.from && r.className)
            .map((r) => stateMark(r.className).range(r.from, r.to)),
          true,
        )
      }
    }
    return next
  },
})

const stateClassAt = (state, from, to) => {
  const classes = []
  state.field(tagState).between(from, to, (f, t, mark) => {
    if (f === from && t === to) classes.push(mark.spec.className)
  })
  return classes.join(' ')
}

class TagWidget extends WidgetType {
  constructor(token, length, stateClass = '') {
    super()
    this.token = token
    this.length = length
    this.stateClass = stateClass
  }

  eq(other) {
    const a = this.token
    const b = other.token
    return (
      a.raw === b.raw &&
      a.label === b.label &&
      a.index === b.index &&
      a.className === b.className &&
      this.length === other.length &&
      this.stateClass === other.stateClass
    )
  }

  toDOM(view) {
    const {token} = this
    const el = document.createElement('span')
    el.className = this.stateClass
      ? `${token.className} ${this.stateClass}`
      : token.className
    el.setAttribute('contenteditable', 'false')
    el.dataset.tagName = token.name
    if (token.index !== undefined && token.index !== null) {
      el.dataset.tagIndex = token.index
      const counter = el.appendChild(document.createElement('span'))
      counter.className = 'index-counter'
      counter.textContent = String(token.index + 1)
      if (token.role !== 'close') {
        const label = el.appendChild(document.createElement('span'))
        label.dataset.text = 'true'
        label.textContent = token.label
      }
    } else {
      el.textContent = token.label
    }
    el.addEventListener('mousedown', (event) => {
      if (event.button !== 0) return
      event.preventDefault()
      const from = view.posAtDOM(el)
      selectTag(view, from, from + this.length, token)
    })
    return el
  }

  // Drag and drop events reach the editor's handlers, so a drop onto a chip can be refused there.
  ignoreEvent(event) {
    return !/^(drag|drop)/.test(event.type)
  }
}

/** Selects the tag at from..to and reports `tagClick`. */
export const selectTag = (view, from, to, token) => {
  view.focus()
  view.dispatch({
    selection: {anchor: from, head: to},
    userEvent: 'select.pointer',
  })
  view.state.facet(tagEventSink)?.('tagClick', {
    name: token.name,
    index: token.index ?? null,
    raw: token.raw,
    label: token.label,
    from,
    to,
  })
}

/**
 * The tags of the document with the segmentModel token each one shows (an `unknown` token for XLIFF markup tokenize
 * does not know), in document order.
 * @returns {Array<{from: number, to: number, token: object}>}
 */
export const chipTokens = (state) => {
  const {raw, tagAt} = serializeWithOffsets(state)
  const isRtl = state.facet(tagDirection) === 'rtl'
  const byOffset = new Map(
    locateTags(raw, {isRtl})
      .filter(({token}) => token)
      .map(({token, from}) => [from, token]),
  )
  return tagAt.map((tag) => {
    const text = state.sliceDoc(tag.from, tag.to)
    const token = byOffset.get(tag.raw) ?? {
      name: 'unknown',
      className: 'tag small tag-selfclosed tag-unknown',
      label: text,
      raw: text,
    }
    return {from: tag.from, to: tag.to, token}
  })
}

// Chips are rebuilt from the serialised string, so labels, pair numbers and shapes are the ones tokenize() gives.
const buildChips = (state) =>
  Decoration.set(
    chipTokens(state).map(({from, to, token}) =>
      Decoration.replace({
        widget: new TagWidget(token, to - from, stateClassAt(state, from, to)),
      }).range(from, to),
    ),
  )

export const chips = StateField.define({
  create: buildChips,
  update: (value, tr) =>
    tr.docChanged || tr.effects.some((e) => e.is(addTags) || e.is(setTagState))
      ? buildChips(tr.state)
      : value,
  provide: (field) => [
    EditorView.decorations.from(field),
    EditorView.atomicRanges.of((view) => view.state.field(field)),
  ],
})

/**
 * Drops user edits that start or end inside a tag. Programmatic changes (no user event) and IME composition pass:
 * refusing a composition would leave CodeMirror's DOM out of step.
 */
export const protectTags = EditorState.transactionFilter.of((tr) => {
  const event = tr.annotation(Transaction.userEvent)
  if (!tr.docChanged || !event || event.startsWith('input.type.compose'))
    return tr
  const ranges = tr.startState.field(tagRanges)
  let cuts = false
  tr.changes.iterChangedRanges((fromA, toA) => {
    ranges.between(fromA, toA, (from, to) => {
      if ((fromA > from && fromA < to) || (toA > from && toA < to)) cuts = true
    })
  })
  return cuts ? [] : tr
})

export const tagExtensions = ({dir, onEvent}) => [
  tagDirection.of(dir),
  tagEventSink.of(onEvent),
  tagState,
  chips,
  protectTags,
]
