import {setTagSignatureMiddleware} from './tagModel'
import {transformTagsToHtml} from './tagUtils'

setTagSignatureMiddleware('space', () => false)

// equiv-text base64 payloads (already HTML-escaped before encoding):
//   Jmx0O3AmZ3Q7      -> &lt;p&gt;
//   Jmx0Oy9wJmd0Ow==  -> &lt;/p&gt;
const pcOpen =
  '<ph id="mtc_1" ctype="x-original_pc_open" equiv-text="base64:Jmx0O3AmZ3Q7"/>'
const pcClose =
  '<ph id="mtc_2" ctype="x-original_pc_close" equiv-text="base64:Jmx0Oy9wJmd0Ow=="/>'
const pcOpenDataRef =
  '<ph id="source1_1" ctype="x-pc_open_data_ref" equiv-text="base64:Jmx0O3AmZ3Q7" x-orig="base64:Jmx0O3AmZ3Q7"/>'
const pcCloseDataRef =
  '<ph id="source1_2" ctype="x-pc_close_data_ref" equiv-text="base64:Jmx0Oy9wJmd0Ow==" x-orig="base64:Jmx0Oy9wJmd0Ow=="/>'
const semanticPh =
  '<ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/>'

describe('transformTagsToHtml - pc-carrying ph tags', () => {
  test('a pc open tag is numbered and tagged tag-pc-open', () => {
    const result = transformTagsToHtml(pcOpen)
    expect(result).toBe(
      '<span contenteditable="false" class="tag small tag-selfclosed tag-ph tag-pc-open">' +
        '<span class="index-counter">1</span>' +
        '<span data-text="true">&lt;p&gt;</span>' +
        '</span>',
    )
  })

  test('open and close of a pair share the same number, distinct roles', () => {
    const result = transformTagsToHtml(pcOpen + 'inner' + pcClose)
    expect(result).toContain('tag-pc-open')
    expect(result).toContain('tag-pc-close')
    // both numbered 1
    const counters = result.match(/<span class="index-counter">(\d+)<\/span>/g)
    expect(counters).toEqual([
      '<span class="index-counter">1</span>',
      '<span class="index-counter">1</span>',
    ])
  })

  test('two sequential pairs are numbered 1 and 2', () => {
    const result = transformTagsToHtml(pcOpen + pcClose + pcOpen + pcClose)
    expect(result).toContain('<span class="index-counter">1</span>')
    expect(result).toContain('<span class="index-counter">2</span>')
  })

  test('dataRef pair is paired by base id', () => {
    const result = transformTagsToHtml(pcOpenDataRef + 'x' + pcCloseDataRef)
    const counters = result.match(/index-counter">(\d+)</g)
    expect(counters).toEqual(['index-counter">1<', 'index-counter">1<'])
    expect(result).toContain('tag-pc-open')
    expect(result).toContain('tag-pc-close')
  })

  test('semantic (non-pc) ph tags are NOT numbered and render plain', () => {
    const result = transformTagsToHtml(semanticPh)
    expect(result).toBe(
      '<span contenteditable="false" class="tag small tag-selfclosed tag-ph">' +
        '&lt;p&gt;</span>',
    )
    expect(result).not.toContain('index-counter')
    expect(result).not.toContain('data-text')
    expect(result).not.toContain('tag-pc-')
  })

  test('mixed pc and semantic ph: only pc gets numbered', () => {
    const result = transformTagsToHtml(semanticPh + pcOpen + semanticPh)
    expect(result.match(/index-counter/g)).toHaveLength(1)
  })

  test('non-ph tags do not get an index counter', () => {
    const result = transformTagsToHtml('<g id="1">content</g>')
    expect(result).not.toContain('index-counter')
    expect(result).not.toContain('data-text')
  })

  test('numbering resets between calls (deterministic output)', () => {
    const r1 = transformTagsToHtml(pcOpen + pcClose)
    const r2 = transformTagsToHtml(pcOpen + pcClose)
    expect(r1).toBe(r2)
  })

  test('RTL keeps the tag-ph styling', () => {
    expect(transformTagsToHtml(pcOpen, 1)).toContain('tag-ph')
  })

  test('null/undefined/empty input returned unchanged', () => {
    expect(transformTagsToHtml(null)).toBeNull()
    expect(transformTagsToHtml(undefined)).toBeUndefined()
    expect(transformTagsToHtml('')).toBe('')
  })

  test('text with no tags returns unchanged', () => {
    const text = 'Hello world no tags here'
    expect(transformTagsToHtml(text)).toBe(text)
  })
})
