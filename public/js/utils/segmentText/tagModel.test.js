import {
  tagSignatures,
  TagStruct,
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
} from './tagModel'

afterEach(() => {
  setTagSignatureMiddleware('space', undefined)
})

test('TagStruct sets default fields from its constructor arguments', () => {
  const tag = new TagStruct(5, 3, 'g', 'g')

  expect(tag.offset).toBe(5)
  expect(tag.length).toBe(3)
  expect(tag.type).toBe('g')
  expect(tag.mutability).toBe('IMMUTABLE')
  expect(tag.data).toEqual({
    id: null,
    name: 'g',
    encodedText: null,
    decodedText: null,
    openTagId: null,
    closeTagId: null,
    openTagKey: null,
    closeTagKey: null,
    placeholder: null,
    originalOffset: -1,
  })
})

test('TagStruct falls back to its default arguments', () => {
  const tag = new TagStruct()

  expect(tag.offset).toBe(-1)
  expect(tag.length).toBe(0)
  expect(tag.type).toBeNull()
  expect(tag.data.name).toBeNull()
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
