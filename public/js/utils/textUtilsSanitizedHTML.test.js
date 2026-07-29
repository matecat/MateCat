import TEXT_UTILS from './textUtils'

describe('sanitizedHTML', () => {
  test('returns the __html shape for dangerouslySetInnerHTML', () => {
    expect(TEXT_UTILS.sanitizedHTML('<b>hi</b>')).toEqual({
      __html: '<b>hi</b>',
    })
  })

  test('strips script tags and event handlers', () => {
    const {__html} = TEXT_UTILS.sanitizedHTML(
      '<img src=x onerror=alert(1)><script>alert(2)</script><b>ok</b>',
    )
    expect(__html).not.toContain('onerror')
    expect(__html).not.toContain('<script')
    expect(__html).toContain('<b>ok</b>')
  })

  test('preserves the tag-pill markup emitted by transformTagsToHtml', () => {
    const pill =
      '<span contenteditable="false" class="tag small">&lt;br/&gt;</span>'
    const {__html} = TEXT_UTILS.sanitizedHTML(pill)
    expect(__html).toContain('class="tag small"')
    expect(__html).toContain('contenteditable="false"')
  })
})
