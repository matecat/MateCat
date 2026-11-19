import {
  tagSignatures,
  getErrorCheckTag,
  getNoLexiqaTag,
  getNoGlossaryTag,
  getBuildableTag,
  getSplitBlockTag,
  getSplitPointTag,
  getTooltipTag,
  getStyleForName,
  getCorrectClosureTag,
  getCorrectTag,
  getXliffRegExpression,
  isToReplaceForLexiqa,
  getTagSignature,
  setTagSignatureMiddleware,
  initTagSignature,
  findSpaces,
  SPACE_REGEX,
} from './tagModel'
import CORPUS from '../../components/editor/core/__fixtures__/roundTripCorpus.json'
import {decodeHtmlEntities} from './tagUtils'

afterEach(() => {
  setTagSignatureMiddleware('space', undefined)
})

test('getSplitBlockTag lists the line-break tags', () => {
  expect(getSplitBlockTag()).toEqual(['lineFeed', 'carriageReturn'])
})

test('getSplitPointTag lists the split-point tag', () => {
  expect(getSplitPointTag()).toEqual(['splitPoint'])
})

test('getBuildableTag lists tags with an encoded placeholder', () => {
  const buildable = getBuildableTag()

  expect(buildable).toContain('nbsp')
  expect(buildable).not.toContain('g')
})

test('getErrorCheckTag lists tags available for error checking', () => {
  const errorCheckTags = getErrorCheckTag()

  expect(errorCheckTags).toContain('g')
  expect(errorCheckTags).not.toContain('nbsp')
})

test('getNoLexiqaTag lists tags not available for lexiqa', () => {
  const noLexiqaTags = getNoLexiqaTag()

  expect(noLexiqaTags).toContain('g')
  expect(noLexiqaTags).toContain('nbsp')
})

test('getNoGlossaryTag lists tags not available for glossary', () => {
  const noGlossaryTags = getNoGlossaryTag()

  expect(noGlossaryTags).toContain('g')
})

test('getTooltipTag lists tags configured to show a tooltip', () => {
  const tooltipTags = getTooltipTag()

  expect(Array.isArray(tooltipTags)).toBe(true)
  tooltipTags.forEach((tagName) => {
    expect(tagSignatures[tagName].showTooltip).toBe(true)
  })
})

test('getStyleForName returns the LTR style for a tag by default', () => {
  expect(getStyleForName('g', false)).toEqual(['tag-open'])
})

test('getStyleForName returns the RTL style when available and requested', () => {
  expect(getStyleForName('g', true)).toEqual(['tag-close'])
})

test('getCorrectClosureTag lists the closing tags for a given type', () => {
  expect(getCorrectClosureTag('g')).toEqual(['gCl'])
})

test('getCorrectTag lists the opening tags for a given type', () => {
  expect(getCorrectTag('g').split(',')).toEqual(
    expect.arrayContaining(['g', 'gSc']),
  )
})

test('getCorrectTag lists the closing tags for a given type when requested', () => {
  expect(getCorrectTag('g', true)).toBe('gCl')
})

test('isToReplaceForLexiqa is true for tags flagged as lexiqa-replaceable', () => {
  expect(isToReplaceForLexiqa('nbsp')).toBe(true)
})

test('isToReplaceForLexiqa is false for tags not flagged as lexiqa-replaceable', () => {
  expect(isToReplaceForLexiqa('g')).toBe(false)
})

test('getXliffRegExpression returns a regex matching xliff tags', () => {
  const regex = getXliffRegExpression()

  expect('<g id="1">tag</g>'.match(regex)).toHaveLength(2)
})

test('getTagSignature returns the raw signature for a tag name', () => {
  expect(getTagSignature('nbsp').type).toBe('nbsp')
})

describe('setTagSignatureMiddleware', () => {
  test('overrides a boolean flag on the tagSignatures proxy', () => {
    setTagSignatureMiddleware('space', () => false)

    expect(tagSignatures.space).toBeUndefined()
  })

  test('leaves the signature untouched when the middleware returns a non-boolean', () => {
    setTagSignatureMiddleware('space', (value) => value)

    expect(tagSignatures.space.type).toBe('space')
  })
})

describe('initTagSignature', () => {
  test('enables the space tag when show_whitespace is active', () => {
    initTagSignature({show_whitespace: 1})

    expect(tagSignatures.space).toBeDefined()
  })

  test('disables the space tag when show_whitespace is inactive', () => {
    initTagSignature({show_whitespace: 0})

    expect(tagSignatures.space).toBeUndefined()
  })
})

describe('findSpaces', () => {
  // The space signature's regex is the reference: findSpaces must find exactly its matches, in linear time
  const byRegex = (text) =>
    [...text.matchAll(SPACE_REGEX)].map((match) => [match.index, match[0]])
  const byFindSpaces = (text) =>
    findSpaces(text).map((match) => [match.index, match[0]])

  test.each([
    '',
    'no blanks',
    'a b',
    '<g id="1">a b</g> c',
    '<a <b c> d',
    'a < b',
    'a > b',
    'a > b < c',
    '< a > b > c',
    'a <b <c d> e> f',
    'tab\tand\nline feed\r\nand cr',
    'nbsp\u00a0ideographic\u3000thin\u2009bom\ufeff',
    '<x id="1"/>\u00a0<x id="2"/>',
    '  <  >  ',
  ])('finds what the regex finds in %j', (text) => {
    expect(byFindSpaces(text)).toEqual(byRegex(text))
  })

  test('finds what the regex finds in every corpus segment, raw and decoded', () => {
    CORPUS.forEach(({segment}) => {
      expect(byFindSpaces(segment)).toEqual(byRegex(segment))
      const decoded = decodeHtmlEntities(segment)
      expect(byFindSpaces(decoded)).toEqual(byRegex(decoded))
    })
  })

  test('finds what the regex finds in every short mix of brackets and blanks', () => {
    const alphabet = ['<', '>', ' ', '\n', '\t', '\r', '\u00a0', 'a']
    const strings = ['']
    for (let length = 1; length <= 5; length++)
      strings
        .filter((text) => text.length === length - 1)
        .forEach((text) =>
          alphabet.forEach((char) => strings.push(text + char)),
        )
    strings.forEach((text) => expect(byFindSpaces(text)).toEqual(byRegex(text)))
  })
})
