import {
  sourceStoreData,
  tagIdentities,
  translationUpdate,
} from './segmentStoreData'

const b64 = (text) => Buffer.from(text, 'utf8').toString('base64')
const ph = (id, text) => `<ph id="${id}" equiv-text="base64:${b64(text)}"/>`
const phTags = (tags) => tags.filter(({data}) => data.name === 'ph')

beforeEach(() => {
  Object.assign(global.config, {
    lfPlaceholder: '##$_0A$##',
    crPlaceholder: '##$_0D$##',
    tabPlaceholder: '##$_09$##',
    nbspPlaceholder: '##$_A0$##',
  })
})

describe('a tag whose text holds a line break', () => {
  test('stays in the decoded source', () => {
    const source = `Before${ph('1', 'a##$_0A$##b')}after`

    const {decodedSegment, plainText} = sourceStoreData(source)

    expect(decodedSegment).toBe(source)
    expect(plainText).toBe('Beforea##$_0A$##bafter')
  })

  test.each([
    ['the line feed placeholder', `x${ph('1', 'a##$_0A$##b')}y`],
    ['the CR LF placeholder', `x${ph('1', 'a##$_0D$####$_0A$##b')}y`],
    ['a raw carriage return', 'x<x id="a\rb"/>y'],
    ['a raw CR LF', 'x<x id="a\r\nb"/>y'],
  ])('is listed once, holding %s', (label, segment) => {
    const {tagRange} = sourceStoreData(segment)
    const {tagMap} = translationUpdate(segment, tagRange)

    expect(tagRange).toHaveLength(1)
    expect(tagMap).toHaveLength(1)
    expect(tagMap[0].offset).toBe(1)
    expect(tagMap[0].data.encodedText).toBe(tagRange[0].data.encodedText)
    expect(tagIdentities(segment, tagRange)).toHaveLength(1)
  })

  test('is not reported missing when the target keeps it', () => {
    const segment = `One${ph('1', 'a##$_0A$##b')}two`
    const {tagRange} = sourceStoreData(segment)

    expect(translationUpdate(segment, tagRange).missingTags).toEqual([])
  })

  test('is reported missing once when the target drops one of two copies', () => {
    const tag = ph('1', 'a##$_0A$##b')
    const {tagRange} = sourceStoreData(`One${tag}two${tag}three`)

    const {tagMap, missingTags} = translationUpdate(
      `Uno${tag}due tre`,
      tagRange,
    )

    expect(phTags(tagMap)).toHaveLength(1)
    expect(missingTags).toHaveLength(1)
    expect(missingTags[0].data.encodedText).toBe(tag)
  })
})
