import selectionAroundEntity from './selectionAroundEntity'

const ZWSP = String.fromCharCode(parseInt('200B', 16))

test('extends over the zero-width space on both sides', () => {
  //        0123      4..6      7
  const text = 'ciao' + ZWSP + 'TAG' + ZWSP + 'mondo'

  expect(selectionAroundEntity(text, 5, 8)).toEqual({
    anchorOffset: 4,
    focusOffset: 9,
  })
})

test('extends only on the side that has one', () => {
  const leadingOnly = 'ciao' + ZWSP + 'TAG' + 'mondo'
  expect(selectionAroundEntity(leadingOnly, 5, 8)).toEqual({
    anchorOffset: 4,
    focusOffset: 8,
  })

  const trailingOnly = 'ciao' + 'TAG' + ZWSP + 'mondo'
  expect(selectionAroundEntity(trailingOnly, 4, 7)).toEqual({
    anchorOffset: 4,
    focusOffset: 8,
  })
})

test('leaves the range alone when there is no zero-width space', () => {
  expect(selectionAroundEntity('ciao TAG mondo', 5, 8)).toEqual({
    anchorOffset: 5,
    focusOffset: 8,
  })
})

test('does not run off the start of the block', () => {
  // an entity at offset 0 has nothing before it to take in
  const text = 'TAG' + ZWSP + 'mondo'

  expect(selectionAroundEntity(text, 0, 3)).toEqual({
    anchorOffset: 0,
    focusOffset: 4,
  })
})

test('does not run off the end of the block', () => {
  const text = 'ciao' + ZWSP + 'TAG'

  expect(selectionAroundEntity(text, 5, 8)).toEqual({
    anchorOffset: 4,
    focusOffset: 8,
  })
})

test('takes in only one space when two entities sit together', () => {
  // ...TAG<zwsp><zwsp>TAG... -- the shared boundary belongs to one of them
  const text = 'a' + ZWSP + 'ONE' + ZWSP + ZWSP + 'TWO' + ZWSP + 'b'

  expect(selectionAroundEntity(text, 2, 5)).toEqual({
    anchorOffset: 1,
    focusOffset: 6,
  })
  expect(selectionAroundEntity(text, 7, 10)).toEqual({
    anchorOffset: 6,
    focusOffset: 11,
  })
})
