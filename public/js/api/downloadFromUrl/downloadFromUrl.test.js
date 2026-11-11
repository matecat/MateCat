import {getFilenameFromContentDisposition} from './downloadFromUrl'

describe('getFilenameFromContentDisposition', () => {
  test('reads a quoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="job_12.zip"'),
    ).toBe('job_12.zip')
  })

  test('keeps a semicolon inside a quoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="a;b.docx"'),
    ).toBe('a;b.docx')
  })

  test('reads an unquoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename=report.xlsx'),
    ).toBe('report.xlsx')
  })

  test('prefers the encoded filename* over the plain one', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename="fallback.zip"; filename*=UTF-8\'\'%E6%97%A5%E6%9C%AC.zip',
      ),
    ).toBe('日本.zip')
  })

  test('falls back to the plain filename when filename* is malformed', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename*=UTF-8\'\'%E6%97; filename="fallback.zip"',
      ),
    ).toBe('fallback.zip')
  })

  test('returns undefined without a header or a filename', () => {
    expect(getFilenameFromContentDisposition(null)).toBeUndefined()
    expect(getFilenameFromContentDisposition('attachment')).toBeUndefined()
  })
})
