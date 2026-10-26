import {ContentState} from 'draft-js'
import createNewEntitiesFromMap from './createNewEntitiesFromMap'
import checkForMissingTags from './TagMenu/checkForMissingTag'
import {transformTagsToLexiqaText} from './tagUtils'

// Runs the real matchTag/decodeTagInfo, unlike createNewEntitiesFromMap.test.js.
// base64 payloads:
//   WyMkZHA0XQ==  -> [#$dp4]
//   WyMkZHA1XQ==  -> [#$dp5]
const gOpen = (id, equiv) => `<g id="${id}" equiv-text="base64:${equiv}">`
const xTag = (id) =>
  `<ph id="${id}" ctype="x-original_x" x-orig="AAA" equiv-text="base64:WyMkZHA1XQ=="/>`

const makeEditorState = () => ({
  getCurrentContent: () => ContentState.createFromText(''),
})
const tagsOf = (text, sourceTagMap) =>
  createNewEntitiesFromMap(makeEditorState(), [], text, sourceTagMap)
    .tagRange.filter(({data}) => ['g', 'gCl', 'ph'].includes(data.name))
    .sort((a, b) => a.offset - b.offset)

describe('createNewEntitiesFromMap - g and x tags with equiv-text', () => {
  test('a g pair shows the equiv-text on both halves and shares a number', () => {
    const [open, close] = tagsOf(`${gOpen('1', 'WyMkZHA0XQ==')}Reset</g>`)
    expect(open.data).toMatchObject({
      name: 'g',
      placeholder: '[#$dp4]',
      index: 0,
      tagRole: 'open',
    })
    expect(close.data).toMatchObject({
      name: 'gCl',
      placeholder: '[#$dp4]',
      index: 0,
      tagRole: 'close',
    })
  })

  test('a plain g keeps its id as the label and gets no number', () => {
    const [open, close] = tagsOf('<g id="7">Reset</g>')
    expect(open.data.placeholder).toBe('7')
    expect(close.data.placeholder).toBe('7')
    expect(open.data.index).toBeUndefined()
    expect(close.data.index).toBeUndefined()
  })

  test('g, pc and x tags are numbered together in document order', () => {
    const tags = tagsOf(
      `${xTag('mtc_1')} ${gOpen('1', 'WyMkZHA0XQ==')}a</g> ${xTag('mtc_2')}`,
    )
    expect(tags.map(({data}) => [data.name, data.index, data.tagRole])).toEqual(
      [
        ['ph', 0, 'single'],
        ['g', 1, 'open'],
        ['gCl', 1, 'close'],
        ['ph', 2, 'single'],
      ],
    )
  })

  test('target g tags inherit the source numbers by id, without mixing up the halves', () => {
    const source = tagsOf(
      `${gOpen('1', 'WyMkZHA0XQ==')}a</g> ${gOpen('2', 'WyMkZHA1XQ==')}b</g>`,
    )
    // the translator swapped the two pairs
    const target = tagsOf(
      `${gOpen('2', 'WyMkZHA1XQ==')}B</g> ${gOpen('1', 'WyMkZHA0XQ==')}A</g>`,
      source,
    )
    expect(target.map(({data}) => [data.name, data.index])).toEqual([
      ['g', 1],
      ['gCl', 1],
      ['g', 0],
      ['gCl', 0],
    ])
  })
})

describe('checkForMissingTags - g tags with equiv-text', () => {
  test('a g pair missing from the target is reported, both halves', () => {
    const source = tagsOf(`${gOpen('1', 'WyMkZHA0XQ==')}Reset</g>`)
    const {missingTags} = checkForMissingTags(source, tagsOf('Reset'))
    expect(missingTags.map(({data}) => data.name)).toEqual(['g', 'gCl'])
  })

  test('nothing is missing when the target has the same g pair', () => {
    const source = tagsOf(`${gOpen('1', 'WyMkZHA0XQ==')}Reset</g>`)
    const target = tagsOf(`${gOpen('1', 'WyMkZHA0XQ==')}Réinitialiser</g>`)
    expect(checkForMissingTags(source, target).missingTags).toEqual([])
  })
})

describe('transformTagsToLexiqaText - g tags with equiv-text', () => {
  // lexiqa offsets are mapped back to the editor text, so each tag must be
  // sent with the same text the editor shows for it
  test('a g pair is sent with its equiv-text, a plain g with its id', () => {
    expect(
      transformTagsToLexiqaText(
        `${gOpen('1', 'WyMkZHA0XQ==')}Reset</g><g id="7">teh</g>`,
      ),
    ).toBe('<[#$dp4]>Reset<[#$dp4]><7>teh<7>')
  })
})
