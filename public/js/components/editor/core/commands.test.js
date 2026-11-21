import {EditorSelection} from '@codemirror/state'
import {createSegmentState} from './createSegmentEditor'
import {
  replaceOccurrenceSpec,
  textRuns,
  transformSelectionSpec,
} from './commands'
import {serialize} from './docModel'

const stateWith = (value, anchor, head = anchor) => {
  const state = createSegmentState(value, {mode: 'target'})
  return state.update({selection: EditorSelection.single(anchor, head)}).state
}
const apply = (state, spec) => state.update(spec).state

describe('text runs', () => {
  test('are the stretches between tags, a tag cut by the range left out', () => {
    const state = stateWith('ab<x id="1"/>cd<g id="2">e</g>', 0)
    // doc: ab<x id="1"/>cd<g id="2">e</g>
    expect(textRuns(state)).toEqual([
      {from: 0, to: 2},
      {from: 13, to: 15},
      {from: 25, to: 26},
    ])
    expect(textRuns(state, 1, 14)).toEqual([
      {from: 1, to: 2},
      {from: 13, to: 14},
    ])
  })
})

describe('transform of the selection', () => {
  test('changes the selected text run by run and never a tag; the selection still covers it', () => {
    const state = stateWith('ab<g id="1">cd</g>ef', 1, 19)
    const next = apply(
      state,
      transformSelectionSpec(state, (t) => t.toUpperCase()),
    )
    expect(serialize(next)).toBe('aB<g id="1">CD</g>Ef')
    expect(next.selection.main.from).toBe(1)
    expect(next.selection.main.to).toBe(19)
  })

  test('keeps a backward selection backward when the text grows', () => {
    const state = stateWith('straße', 6, 0)
    const next = apply(
      state,
      transformSelectionSpec(state, (t) => t.toUpperCase()),
    )
    expect(next.doc.toString()).toBe('STRASSE')
    expect([next.selection.main.anchor, next.selection.main.head]).toEqual([
      7, 0,
    ])
  })

  // A word start, as formatText's capitalize has it: the start of the text or after a space.
  const capitalize = (t) => t.replace(/(^|\s)\p{L}/gu, (m) => m.toUpperCase())

  test('text after a tag continues the word before it: a tag inside a word starts no new one', () => {
    const segment = 'ka<g id="1">rl</g>sson and<x id="2"/> <g id="3">co</g>'
    const state = stateWith(segment, 0, createSegmentState(segment).doc.length)
    expect(
      serialize(apply(state, transformSelectionSpec(state, capitalize))),
    ).toBe('Ka<g id="1">rl</g>sson And<x id="2"/> <g id="3">Co</g>')
  })

  test('the start of the selection starts a word, even inside one', () => {
    const state = stateWith('ab<x id="1"/>cd', 1, 15)
    expect(
      serialize(apply(state, transformSelectionSpec(state, capitalize))),
    ).toBe('aB<x id="1"/>cd')
  })

  test('does nothing on an empty selection', () => {
    expect(transformSelectionSpec(stateWith('ab', 1), (t) => t)).toBe(null)
  })
})

describe('replace of one occurrence', () => {
  test('counts the matches through the text between tags and replaces the chosen one', () => {
    const state = stateWith('Cat <g id="1">cat</g> cat', 0)
    const regex = /cat/gi
    expect(
      serialize(apply(state, replaceOccurrenceSpec(state, regex, 1, 'dog'))),
    ).toBe('Cat <g id="1">dog</g> cat')
    expect(
      serialize(apply(state, replaceOccurrenceSpec(state, regex, 0, 'dog'))),
    ).toBe('dog <g id="1">cat</g> cat')
  })

  test('never matches inside a tag, and finds nothing past the last match', () => {
    const state = stateWith('<g id="1">g</g>', 0)
    expect(
      serialize(apply(state, replaceOccurrenceSpec(state, /g/i, 0, 'x'))),
    ).toBe('<g id="1">x</g>')
    expect(replaceOccurrenceSpec(state, /g/i, 1, 'x')).toBe(null)
    expect(replaceOccurrenceSpec(state, /g/i, -1, 'x')).toBe(null)
  })

  test('a pattern that can match nothing is skipped rather than looping', () => {
    const state = stateWith('ab', 0)
    expect(replaceOccurrenceSpec(state, /x*/g, 0, 'y')).toBe(null)
  })
})
