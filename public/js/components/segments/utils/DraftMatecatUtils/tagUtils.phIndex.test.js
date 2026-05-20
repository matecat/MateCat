import {setTagSignatureMiddleware} from './tagModel'
import {transformTagsToHtml} from './tagUtils'

setTagSignatureMiddleware('space', () => false)

describe('transformTagsToHtml - ph tag indexing', () => {
  test('single ph tag gets index 1', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
    const result = transformTagsToHtml(text)
    expect(result).toContain('<span class="index-counter">1</span>')
    expect(result).toContain('<span data-text="true">&lt;p&gt;</span>')
  })

  test('multiple ph tags get sequential indices', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> text <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>'
    const result = transformTagsToHtml(text)
    expect(result).toContain('<span class="index-counter">1</span>')
    expect(result).toContain('<span class="index-counter">2</span>')
  })

  test('ph tag index counter resets between calls', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
    const result1 = transformTagsToHtml(text)
    const result2 = transformTagsToHtml(text)
    expect(result1).toBe(result2)
    expect(result1).toContain('<span class="index-counter">1</span>')
  })

  test('non-ph tags do not get index counter', () => {
    const text = '<g id="1">content</g>'
    const result = transformTagsToHtml(text)
    expect(result).not.toContain('index-counter')
    expect(result).not.toContain('data-text')
  })

  test('mixed ph and non-ph tags: only ph gets indexed', () => {
    const text =
      '<g id="1">hello</g> <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <g id="2">world</g>'
    const result = transformTagsToHtml(text)
    const indexCounterMatches = result.match(/index-counter/g)
    expect(indexCounterMatches).toHaveLength(1)
  })

  test('ph tag HTML structure is correct', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
    const result = transformTagsToHtml(text)
    expect(result).toBe(
      '<span contenteditable="false" class="tag small tag-selfclosed tag-ph">' +
        '<span class="index-counter">1</span>' +
        '<span data-text="true">&lt;p&gt;</span>' +
        '</span>',
    )
  })

  test('RTL mode uses styleRTL class', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
    const resultLTR = transformTagsToHtml(text, 0)
    const resultRTL = transformTagsToHtml(text, 1)
    expect(resultLTR).toContain('tag-selfclosed tag-ph')
    expect(resultRTL).toContain('tag-selfclosed tag-ph')
  })

  test('returns null/undefined input unchanged', () => {
    expect(transformTagsToHtml(null)).toBeNull()
    expect(transformTagsToHtml(undefined)).toBeUndefined()
    expect(transformTagsToHtml('')).toBe('')
  })

  test('text with no tags returns unchanged', () => {
    const text = 'Hello world no tags here'
    expect(transformTagsToHtml(text)).toBe(text)
  })

  test('three ph tags get indices 1, 2, 3', () => {
    const text =
      '<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>' +
      '<ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/>' +
      '<ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>'
    const result = transformTagsToHtml(text)
    expect(result).toContain('<span class="index-counter">1</span>')
    expect(result).toContain('<span class="index-counter">2</span>')
    expect(result).toContain('<span class="index-counter">3</span>')
  })
})
