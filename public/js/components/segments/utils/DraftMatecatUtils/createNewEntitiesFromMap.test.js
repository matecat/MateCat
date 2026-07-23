import {ContentState} from 'draft-js'
import createNewEntitiesFromMap from './createNewEntitiesFromMap'

jest.mock('./matchTag', () => {
  return jest.fn((text) => {
    const results = []
    const phRegex = /<ph\b[^>]+?equiv-text="base64:([^"]+)"[^>]*?\/>/g
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

const B = 'base64:Jmx0O3AmZ3Q7' // -> &lt;p&gt;
const pcOpen = (id) => `<ph id="${id}" ctype="x-original_pc_open" equiv-text="${B}"/>`
const pcClose = (id) =>
  `<ph id="${id}" ctype="x-original_pc_close" equiv-text="${B}"/>`
const dOpen = (id) =>
  `<ph id="${id}" ctype="x-pc_open_data_ref" equiv-text="${B}" x-orig="${B}"/>`
const dClose = (id) =>
  `<ph id="${id}" ctype="x-pc_close_data_ref" equiv-text="${B}" x-orig="${B}"/>`
const semantic = (id) => `<ph id="${id}" ctype="x-html" equiv-text="${B}"/>`

describe('createNewEntitiesFromMap - pc numbering', () => {
  const makeEditorState = () => ({
    getCurrentContent: () => ContentState.createFromText(''),
  })
  const phOf = (text, sourceTagMap) =>
    createNewEntitiesFromMap(makeEditorState(), [], text, sourceTagMap).tagRange.filter(
      (t) => t.data.name === 'ph',
    )

  test('numbers a pc pair with a shared index and open/close roles', () => {
    const ph = phOf(pcOpen('mtc_1') + pcClose('mtc_2'))
    expect(ph[0].data).toMatchObject({index: 0, pcRole: 'open'})
    expect(ph[1].data).toMatchObject({index: 0, pcRole: 'close'})
  })

  test('two sequential pairs get indices 0,0,1,1', () => {
    const ph = phOf(
      pcOpen('mtc_1') + pcClose('mtc_2') + pcOpen('mtc_3') + pcClose('mtc_4'),
    )
    expect(ph.map((t) => t.data.index)).toEqual([0, 0, 1, 1])
  })

  test('dataRef pair is paired by base id', () => {
    const ph = phOf(dOpen('source1_1') + dClose('source1_2'))
    expect(ph[0].data).toMatchObject({index: 0, pcRole: 'open'})
    expect(ph[1].data).toMatchObject({index: 0, pcRole: 'close'})
  })

  test('semantic (x-html) ph tags get no index or pcRole', () => {
    const ph = phOf(semantic('mtc_1') + semantic('mtc_2'))
    expect(ph[0].data.index).toBeUndefined()
    expect(ph[0].data.pcRole).toBeUndefined()
  })

  test('only pc tags are numbered when mixed with semantic ph', () => {
    const ph = phOf(semantic('mtc_1') + pcOpen('mtc_2') + pcClose('mtc_3'))
    expect(ph[0].data.index).toBeUndefined()
    expect(ph[1].data).toMatchObject({index: 0, pcRole: 'open'})
    expect(ph[2].data).toMatchObject({index: 0, pcRole: 'close'})
  })

  test('inherits index and pcRole from sourceTagMap when ids match', () => {
    const sourceTagMap = [
      {data: {id: 'mtc_1', name: 'ph', index: 3, pcRole: 'open'}},
      {data: {id: 'mtc_2', name: 'ph', index: 3, pcRole: 'close'}},
    ]
    const ph = phOf(pcOpen('mtc_1') + pcClose('mtc_2'), sourceTagMap)
    expect(ph[0].data).toMatchObject({index: 3, pcRole: 'open'})
    expect(ph[1].data).toMatchObject({index: 3, pcRole: 'close'})
  })

  test('fresh pc pairs continue numbering after inherited indices', () => {
    const sourceTagMap = [
      {data: {id: 'mtc_1', name: 'ph', index: 2, pcRole: 'open'}},
      {data: {id: 'mtc_2', name: 'ph', index: 2, pcRole: 'close'}},
    ]
    const ph = phOf(
      pcOpen('mtc_1') + pcClose('mtc_2') + pcOpen('mtc_3') + pcClose('mtc_4'),
      sourceTagMap,
    )
    expect(ph.map((t) => t.data.index)).toEqual([2, 2, 3, 3])
  })

  test('non-ph tags do not get an index', () => {
    const {tagRange} = createNewEntitiesFromMap(
      makeEditorState(),
      [],
      '<g id="1">x</g>',
    )
    const gTags = tagRange.filter((t) => t.data.name === 'g')
    expect(gTags[0].data.index).toBeUndefined()
  })
})
