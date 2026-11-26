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

describe('characters of the last private use plane', () => {
  // U+10F800..U+10FFFF (icon fonts) are written with the high surrogates \uDBFE and \uDBFF: text, never markup
  const pua = String.fromCodePoint(0x10f800) + String.fromCodePoint(0x10ffff)

  test.each([
    ['alone', `a ${pua} b`, `a ${pua} b`, `a·${pua}·b`, []],
    [
      'next to a real tag',
      `a ${pua}<g id="1">${pua}</g> c`,
      `a ${pua}<g id="1">${pua}</g> c`,
      `a·${pua}1${pua}1·c`,
      [
        [6, 'g'],
        [20, 'gCl'],
      ],
    ],
    [
      'next to typed brackets',
      `&lt;${pua}&gt;${pua}&lt;g id="1"&gt;`,
      `&lt;${pua}&gt;${pua}&lt;g id="1"&gt;`,
      `<${pua}>${pua}<g id="1">`,
      [],
    ],
  ])('survive %s in a source', (label, source, decoded, plain, tags) => {
    const {decodedSegment, plainText, tagRange, lxqText} =
      sourceStoreData(source)

    expect(decodedSegment).toBe(decoded)
    expect(plainText).toBe(plain)
    expect(tagRange.map(({offset, data}) => [offset, data.name])).toEqual(tags)
    expect(lxqText.split(pua)).toHaveLength(source.split(pua).length)
  })

  test('survive in a target, which keeps the source tags', () => {
    const source = `a <g id="1">b</g> c`
    const translation = `${pua} <g id="1">${pua}</g> &lt;${pua}&gt;`
    const {tagRange} = sourceStoreData(source)

    const {plainText, tagMap, missingTags, lxqDecodedTranslation} =
      translationUpdate(translation, tagRange)

    expect(plainText).toBe(`${pua}·1${pua}1·<${pua}>`)
    expect(
      tagMap
        .filter(({data}) => data.name !== 'space')
        .map(({offset, data}) => [offset, data.name]),
    ).toEqual([
      [5, 'g'],
      [19, 'gCl'],
    ])
    expect(missingTags).toEqual([])
    expect(lxqDecodedTranslation).toContain(`<${pua}>`)
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

describe('tag markup typed as text', () => {
  // The editor stores what the translator types HTML-escaped: typed tag markup reaches the segment string as entities
  const typedG = '&lt;g id="1"&gt;x&lt;/g&gt;'

  test('does not stand for a tag the target misses', () => {
    const {tagRange} = sourceStoreData('<g id="1">x</g>')

    const {tagMap, missingTags} = translationUpdate(typedG, tagRange)

    expect(tagMap.filter(({data}) => data.name !== 'space')).toEqual([])
    expect(missingTags.map(({data}) => data.name)).toEqual(['g', 'gCl'])
  })

  test('gives a source no tags and stays escaped in its decoded segment', () => {
    const source = `Type ${typedG} here`

    const {decodedSegment, plainText, tagRange} = sourceStoreData(source)

    expect(tagRange).toEqual([])
    expect(tagIdentities(source)).toEqual([])
    expect(decodedSegment).toBe(source)
    expect(plainText).toContain('<g id="1">x</g>')
  })

  test('next to a real tag leaves the real tag as it is', () => {
    const source = `<g id="1">${typedG}</g>`

    const {decodedSegment, tagRange} = sourceStoreData(source)

    expect(decodedSegment).toBe(source)
    expect(tagRange.map(({offset, data}) => [offset, data.name])).toEqual([
      [0, 'g'],
      [25, 'gCl'],
    ])
  })

  test('never comes out as markup in the decoded segment', () => {
    const source = '&lt;x onmouseover=alert(1)&gt;hi&lt;/x&gt; &lt;mrk&gt;'

    const {decodedSegment, tagRange} = sourceStoreData(source)

    expect(tagRange).toEqual([])
    expect(decodedSegment).toBe(source)
  })
})

describe('markup the segment string carries outside its tags', () => {
  test('stays as is when it is an XLIFF tag', () => {
    const source =
      'A <ph id="mtc_1" ctype="x-original_x"/> and <mrk mtype="x">b</mrk>'

    expect(sourceStoreData(source).decodedSegment).toBe(source)
  })

  test('is encoded when it only starts like one', () => {
    const {decodedSegment} = sourceStoreData(
      'A <x onmouseover=alert(1)>b</x> <example> <phone>',
    )

    expect(decodedSegment).toBe(
      'A &lt;x onmouseover=alert(1)&gt;b</x> &lt;example&gt; &lt;phone&gt;',
    )
  })
})

describe('a long segment', () => {
  // Several steps rebuilt the whole string for every tag, blank or line break, so a 256 KB segment took seconds. A time
  // limit depends on the machine running the tests: four times the text must take about four times as long instead,
  // where quadratic code takes twelve to sixteen times as long. The best of three runs keeps a pause out of the ratio.
  const {tagRange} = sourceStoreData('<g id="1">x</g>')
  const bestTime = (segment) => {
    let best = Infinity
    for (let run = 0; run < 3; run++) {
      const start = performance.now()
      translationUpdate(segment, tagRange)
      sourceStoreData(segment)
      best = Math.min(best, performance.now() - start)
    }
    return best
  }

  test.each([
    ['plain words', 'Some words and more words here. '],
    [
      'tags, entities and line breaks',
      'a &lt;b&gt; <g id="1">c</g> d##$_0A$##e ',
    ],
  ])('of %s is processed in linear time', (_, unit) => {
    bestTime(unit.repeat(256))
    const ratio = bestTime(unit.repeat(4096)) / bestTime(unit.repeat(1024))

    expect(ratio).toBeLessThan(8)
  })

  test('keeps its tags', () => {
    const translation = 'Some words and more words here. '.repeat(2048)

    expect(translationUpdate(translation, tagRange).missingTags).toHaveLength(2)
  })
})

describe('raw line breaks mixed with tags and line break placeholders', () => {
  // Not a supported input: the backend sends line breaks as placeholders, never as raw CR and LF characters. Each one
  // is stored as the placeholder the editor reads it as, and the tags after it keep their place. (Splitting the text
  // into lines, as Draft did, counted a raw CR LF as one character and garbled these strings.)
  const segment = '\r\n\r\n>##$_A0$####$_0A$##'

  test('is stored with every line break and tag in place, as found by the fuzz', () => {
    expect(sourceStoreData(segment)).toEqual({
      decodedSegment:
        '##$_0D$####$_0A$####$_0D$####$_0A$##&gt;##$_A0$####$_0A$##',
      plainText: '\n\n>°\n',
      tagRange: [],
      lxqText: '\n\n\n\n>< >\n',
    })
    const {plainText, tagMap, missingTags} = translationUpdate(segment, [])
    expect(plainText).toBe('\n\n>°\n')
    expect(tagMap.map(({offset, data}) => [offset, data.name])).toEqual([
      [5, 'nbsp'],
    ])
    expect(missingTags).toEqual([])
  })
})
