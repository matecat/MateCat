import buildFragmentFromText from './buildFragmentFromText'
import {initTagSignature} from './tagModel'

beforeAll(() => {
  initTagSignature({show_whitespace: 0})
})

describe('buildFragmentFromText', () => {
  test('builds a fragment containing the given plain text', () => {
    const fragment = buildFragmentFromText('hello')
    expect(fragment.first().getText()).toBe('hello')
  })

  test('builds a multi-block fragment for text containing newlines', () => {
    const fragment = buildFragmentFromText('line one\nline two')
    const texts = fragment.valueSeq().toArray().map((b) => b.getText())
    expect(texts).toEqual(['line one', 'line two'])
  })
})
