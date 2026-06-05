import {ContentState} from 'draft-js'
import createNewEntitiesFromMap from './createNewEntitiesFromMap'

jest.mock('./matchTag', () => {
  return jest.fn((text) => {
    const results = []
    const phRegex =
      /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/g
    let match
    while ((match = phRegex.exec(text)) !== null) {
      const {Base64} = require('js-base64')
      const decoded = Base64.decode(match[1])
      results.push({
        offset: match.index,
        data: {
          name: 'ph',
          id: (match[0].match(/id="([^"]*)"/) || [])[1] || '',
          placeholder: decoded,
          encodedText: match[0],
          decodedText: decoded,
          index: undefined,
        },
        type: 'TAG',
        mutability: 'IMMUTABLE',
      })
    }
    const gOpenRegex = /<g\b[^>]+?id="([^"]+)"(?![^>]*\/>)[^>]*>/g
    while ((match = gOpenRegex.exec(text)) !== null) {
      results.push({
        offset: match.index,
        data: {
          name: 'g',
          id: match[1],
          placeholder: match[1],
          encodedText: match[0],
          decodedText: match[1],
          index: undefined,
        },
        type: 'TAG',
        mutability: 'IMMUTABLE',
      })
    }
    return results
  })
})

describe('createNewEntitiesFromMap - addIncrementalIndex', () => {
  const makeEditorState = () => ({
    getCurrentContent: () => ContentState.createFromText(''),
  })

  test('assigns sequential index to ph tags without sourceTagMap', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>' +
      '<ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>'
    const {tagRange} = createNewEntitiesFromMap(makeEditorState(), [], text)
    const phTags = tagRange.filter((t) => t.data.name === 'ph')
    expect(phTags[0].data.index).toBe(0)
    expect(phTags[1].data.index).toBe(1)
  })

  test('assigns sequential index to ph tags with empty sourceTagMap', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
    const {tagRange} = createNewEntitiesFromMap(makeEditorState(), [], text, [])
    const phTags = tagRange.filter((t) => t.data.name === 'ph')
    expect(phTags[0].data.index).toBe(0)
  })

  test('preserves index from sourceTagMap when ids match', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>' +
      '<ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>'
    const sourceTagMap = [
      {data: {id: 'mtc_1', name: 'ph', index: 5}},
      {data: {id: 'mtc_2', name: 'ph', index: 6}},
    ]
    const {tagRange} = createNewEntitiesFromMap(
      makeEditorState(),
      [],
      text,
      sourceTagMap,
    )
    const phTags = tagRange.filter((t) => t.data.name === 'ph')
    expect(phTags[0].data.index).toBe(5)
    expect(phTags[1].data.index).toBe(6)
  })

  test('skips sourceTagMap matching when ph tag id is empty (XLIFF2)', () => {
    const text =
      '<ph id="" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>' +
      '<ph id="" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>'
    const sourceTagMap = [
      {data: {id: 'mtc_1', name: 'ph', index: 10}},
    ]
    const {tagRange} = createNewEntitiesFromMap(
      makeEditorState(),
      [],
      text,
      sourceTagMap,
    )
    const phTags = tagRange.filter((t) => t.data.name === 'ph')
    expect(phTags[0].data.index).toBe(0)
    expect(phTags[1].data.index).toBe(1)
  })

  test('non-ph tags do not get index assigned', () => {
    const text = '<g id="1">hello</g>'
    const {tagRange} = createNewEntitiesFromMap(makeEditorState(), [], text)
    const gTags = tagRange.filter((t) => t.data.name === 'g')
    expect(gTags[0].data.index).toBeUndefined()
  })

  test('index continues after sourceTagMap-assigned indices', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>' +
      '<ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>' +
      '<ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>'
    const sourceTagMap = [
      {data: {id: 'mtc_1', name: 'ph', index: 0}},
    ]
    const {tagRange} = createNewEntitiesFromMap(
      makeEditorState(),
      [],
      text,
      sourceTagMap,
    )
    const phTags = tagRange.filter((t) => t.data.name === 'ph')
    expect(phTags[0].data.index).toBe(0)
    expect(phTags[1].data.index).toBe(1)
    expect(phTags[2].data.index).toBe(2)
  })
})
