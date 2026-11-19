import {getFilenameFromContentDisposition} from './contentDisposition'

describe('getFilenameFromContentDisposition', () => {
  test('returns the quoted name of a plain ascii header', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="report.docx"'),
    ).toBe('report.docx')
  })

  test('prefers the encoded original over the ascii copy', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename="_______.txt"; filename*=UTF-8\'\'%D1%81%D0%BF%D0%B0%D1%81%D0%B8%D0%B1%D0%BE.txt',
      ),
    ).toBe('спасибо.txt')
  })

  test('keeps a semicolon and an equals sign of the original', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename="a_b=c.txt"; filename*=UTF-8\'\'a%3Bb%3Dc.txt',
      ),
    ).toBe('a;b=c.txt')
  })

  test('keeps a semicolon inside the quoted name', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="a;b.txt"'),
    ).toBe('a;b.txt')
  })

  test('falls back to the quoted name when the encoded one is malformed', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename="100_.txt"; filename*=UTF-8\'\'100%ZZ.txt',
      ),
    ).toBe('100_.txt')
  })

  test('reads an unquoted name', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename=report.docx'),
    ).toBe('report.docx')
  })

  test('returns the fallback without a header or without a name', () => {
    expect(getFilenameFromContentDisposition(null, 'download')).toBe('download')
    expect(getFilenameFromContentDisposition('attachment', 'download')).toBe(
      'download',
    )
    expect(getFilenameFromContentDisposition('attachment')).toBe('')
  })
})
