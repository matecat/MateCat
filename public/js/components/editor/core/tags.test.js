import {Base64} from 'js-base64'
import {createSegmentState} from './createSegmentEditor'
import {serialize} from './docModel'
import {chips, chipTokens, setTagState} from './tags'

const ph = (id, ctype, text, extra = '') =>
  `<ph id="${id}" ctype="${ctype}" equiv-text="base64:${Base64.encode(text)}"${extra}/>`
const gEquiv = (id, text) =>
  `<g id="${id}" equiv-text="base64:${Base64.encode(text)}">`

// The chips of a state, as what the translator sees.
const chipsOf = (state) => {
  const out = []
  state.field(chips).between(0, state.doc.length, (from, to, deco) => {
    const {token} = deco.spec.widget
    out.push({
      // matchTag wraps g labels in zero-width spaces, as the cattool does
      label: token.label.replace(/\u200b/g, ''),
      index: token.index ?? null,
      role: token.role ?? null,
      className: token.className,
      from,
      to,
    })
  })
  return out
}

describe('chip labels and numbers (createNewEntitiesFromMap, compressibleTagUtils)', () => {
  test('a pc pair shares a number; sequential pairs count up', () => {
    const state = createSegmentState(
      ph('1', 'x-original_pc_open', '<b>') +
        'a' +
        ph('2', 'x-original_pc_close', '</b>') +
        ph('3', 'x-original_pc_open', '<i>') +
        'b' +
        ph('4', 'x-original_pc_close', '</i>'),
    )
    expect(chipsOf(state).map(({index, role}) => [index, role])).toEqual([
      [0, 'open'],
      [0, 'close'],
      [1, 'open'],
      [1, 'close'],
    ])
  })

  test('nested pc pairs pair by nesting', () => {
    const state = createSegmentState(
      ph('1', 'x-original_pc_open', '<b>') +
        ph('2', 'x-original_pc_open', '<i>') +
        'a' +
        ph('3', 'x-original_pc_close', '</i>') +
        ph('4', 'x-original_pc_close', '</b>'),
    )
    expect(chipsOf(state).map((c) => c.index)).toEqual([0, 1, 1, 0])
  })

  test('a dataRef pair is paired by its base id', () => {
    const state = createSegmentState(
      ph('source1_1', 'x-pc_open_data_ref', '<a href="#">') +
        'Bob' +
        ph('source1_2', 'x-pc_close_data_ref', '</a>'),
    )
    expect(chipsOf(state).map((c) => c.index)).toEqual([0, 0])
  })

  test('a semantic ph shows its equiv-text and has no number', () => {
    const [chip] = chipsOf(
      createSegmentState(`a${ph('1', 'x-html', '<br/>')}b`),
    )
    expect(chip).toMatchObject({label: '<br/>', index: null})
    expect(chip.className).toContain('tag-ph')
  })

  test('an x-original_x ph is not numbered and does not shift the g numbers', () => {
    const state = createSegmentState(
      ph('1', 'x-original_x', '<br>') + gEquiv('2', '<b>') + 'a</g>',
    )
    expect(chipsOf(state).map((c) => c.index)).toEqual([null, 0, 0])
  })

  test('a g pair with equiv-text shows it on the opener and shares a number', () => {
    const state = createSegmentState(gEquiv('1', '<b>') + 'a</g>')
    expect(chipsOf(state).map(({label, index}) => [label, index])).toEqual([
      ['<b>', 0],
      ['', 0],
    ])
  })

  test('a plain g shows its id on both halves; an orphan close shows ?', () => {
    const state = createSegmentState('<g id="7">a</g></g>')
    expect(chipsOf(state).map((c) => c.label)).toEqual(['7', '7', '?'])
  })

  test('x, bx, ex and a self-closed g show their id', () => {
    const state = createSegmentState(
      '<x id="1"/><bx id="2"/><ex id="3"/><g id="4"/>',
    )
    expect(chipsOf(state).map((c) => c.label)).toEqual(['1', '2', '3', '4'])
  })

  test('the open and close shapes swap in a right-to-left editor', () => {
    const ltr = chipsOf(createSegmentState('<g id="1">a</g>'))
    const rtl = chipsOf(createSegmentState('<g id="1">a</g>', {dir: 'rtl'}))
    expect(ltr.map((c) => c.className.match(/tag-(open|close)/)[0])).toEqual([
      'tag-open',
      'tag-close',
    ])
    expect(rtl.map((c) => c.className.match(/tag-(open|close)/)[0])).toEqual([
      'tag-close',
      'tag-open',
    ])
  })

  test('a carriage return and a split point are chips; line feed, nbsp and tab are not', () => {
    const state = createSegmentState(
      'a##$_0D$##b##$_SPLIT$##c##$_0A$##d##$_A0$##e##$_09$##f',
    )
    expect(chipsOf(state).map((c) => c.className)).toEqual([
      expect.stringContaining('tag-cr'),
      expect.stringContaining('tag-split'),
    ])
  })

  test('numbers follow the document after an edit', () => {
    const p1 = ph('1', 'x-original_pc_open', '<b>')
    const p2 = ph('2', 'x-original_pc_close', '</b>')
    let state = createSegmentState(`${p1}a${p2}`)
    // delete the first pair: the remaining text has no chips
    state = state.update({changes: {from: 0, to: state.doc.length}}).state
    expect(chipsOf(state)).toEqual([])
  })
})

describe('tag protection', () => {
  const segment = 'a<x id="1"/>b'
  const tagFrom = 1
  const tagTo = 12

  test.each([
    [
      'typing inside a tag',
      {changes: {from: 5, insert: 'z'}, userEvent: 'input.type'},
    ],
    [
      'a deletion ending inside a tag',
      {changes: {from: 0, to: 5}, userEvent: 'delete.backward'},
    ],
    [
      'a deletion starting inside a tag',
      {changes: {from: 5, to: 13}, userEvent: 'delete.forward'},
    ],
    [
      'a paste over part of a tag',
      {changes: {from: 3, to: 13, insert: 'z'}, userEvent: 'input.paste'},
    ],
  ])('refuses %s', (name, spec) => {
    const state = createSegmentState(segment)
    expect(serialize(state.update(spec).state)).toBe(segment)
  })

  test('lets a user edit through when it takes the whole tag', () => {
    const state = createSegmentState(segment)
    const next = state.update({
      changes: {from: tagFrom, to: tagTo},
      userEvent: 'delete.backward',
    }).state
    expect(serialize(next)).toBe('ab')
  })

  test('lets IME composition through, so the DOM stays in step', () => {
    const state = createSegmentState(segment)
    const next = state.update({
      changes: {from: 5, insert: '한'},
      userEvent: 'input.type.compose',
    }).state
    expect(next.doc.toString()).toContain('한')
  })

  test('lets programmatic changes through', () => {
    const state = createSegmentState(segment)
    const next = state.update({changes: {from: 5, insert: 'z'}}).state
    expect(next.doc.toString()).toContain('z')
  })
})

describe('chip state classes (TagEntity tag-clicked / tag-focused)', () => {
  const segment = 'a<x id="1"/>b<x id="2"/>'
  // chipsOf drops the widget's extra class; read it straight off the decoration
  const chipsWithState = (state) => {
    const out = []
    state.field(chips).between(0, state.doc.length, (from, to, deco) => {
      out.push({from, to, stateClass: deco.spec.widget.stateClass})
    })
    return out
  }

  test('a class set on a chip range shows on that chip only', () => {
    let state = createSegmentState(segment)
    const [first] = chipTokens(state)
    state = state.update({
      effects: setTagState.of([
        {from: first.from, to: first.to, className: 'tag-clicked'},
      ]),
    }).state
    expect(chipsWithState(state).map((c) => c.stateClass)).toEqual([
      'tag-clicked',
      '',
    ])
  })

  test('a new list replaces the previous one, and an empty list clears every chip', () => {
    let state = createSegmentState(segment)
    const [first, second] = chipTokens(state)
    state = state.update({
      effects: setTagState.of([{...first, className: 'tag-clicked'}]),
    }).state
    state = state.update({
      effects: setTagState.of([{...second, className: 'tag-focused'}]),
    }).state
    expect(chipsWithState(state).map((c) => c.stateClass)).toEqual([
      '',
      'tag-focused',
    ])
    state = state.update({effects: setTagState.of([])}).state
    expect(chipsWithState(state).map((c) => c.stateClass)).toEqual(['', ''])
  })

  test('a class follows its chip when text is inserted before it', () => {
    let state = createSegmentState(segment)
    const [, second] = chipTokens(state)
    state = state.update({
      effects: setTagState.of([{...second, className: 'tag-clicked'}]),
    }).state
    state = state.update({changes: {from: 0, insert: 'zz'}}).state
    expect(chipsWithState(state)).toEqual([
      {from: 3, to: 14, stateClass: ''},
      {from: 15, to: 26, stateClass: 'tag-clicked'},
    ])
  })

  test('a range that is not exactly a chip marks nothing', () => {
    let state = createSegmentState(segment)
    state = state.update({
      effects: setTagState.of([{from: 0, to: 3, className: 'tag-clicked'}]),
    }).state
    expect(chipsWithState(state).map((c) => c.stateClass)).toEqual(['', ''])
  })

  test('chipTokens gives each tag its document range and token, unknown markup included', () => {
    const state = createSegmentState('a<x id="1"/>b<bpt id="2">c')
    expect(
      chipTokens(state).map(({from, to, token}) => [from, to, token.name]),
    ).toEqual([
      [1, 12, 'x'],
      [13, 25, 'unknown'],
    ])
  })
})
