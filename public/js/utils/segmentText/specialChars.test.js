import {
  CHARACTER_TAGS,
  LINE_BREAK,
  SPACE,
  SPECIAL_CHARS,
  TAB,
  specialCharMark,
  specialCharsPattern,
} from './specialChars'
import {tagSignatures} from './tagModel'

// Ordinary text, bidi controls (CodeMirror's own marks in the editor) and the characters of the table.
const CANDIDATES = [
  'a',
  '°',
  ' ',
  '\t',
  '\n',
  '\r',
  '‎',
  '⁦',
  ...Object.keys(SPECIAL_CHARS),
]

describe('specialCharMark', () => {
  test('the tab and the line break always have their mark, ordinary text none', () => {
    expect(specialCharMark('\t')).toBe(TAB)
    expect(specialCharMark('\n')).toBe(LINE_BREAK)
    expect(specialCharMark('a')).toBeNull()
    expect(specialCharMark('‎')).toBeNull()
    // a typed degree sign is text, not the nbsp mark that shows one
    expect(specialCharMark('°')).toBeNull()
  })

  test('the space has a mark only with showWhitespace, and it has no tooltip', () => {
    expect(specialCharMark(' ')).toBeNull()
    expect(specialCharMark(' ', {showWhitespace: true})).toBe(SPACE)
    expect(SPACE.title).toBeNull()
    expect(SPACE.keepsWidth).toBe(true)
  })
})

describe('specialCharsPattern', () => {
  test.each([false, true])(
    'matches exactly the characters that have a mark (showWhitespace %s)',
    (showWhitespace) => {
      const regex = new RegExp(`^${specialCharsPattern({showWhitespace})}$`)
      for (const ch of CANDIDATES) {
        expect([ch, regex.test(ch)]).toEqual([
          ch,
          specialCharMark(ch, {showWhitespace}) !== null,
        ])
      }
    },
  )
})

describe('CHARACTER_TAGS', () => {
  test('each placeholder tag stands for the character its tag signature names', () => {
    expect(CHARACTER_TAGS.nbsp).toBe(tagSignatures.nbsp.unicodeChar)
    expect(CHARACTER_TAGS.tab).toBe(tagSignatures.tab.unicodeChar)
    expect(CHARACTER_TAGS.lineFeed).toBe(tagSignatures.lineFeed.unicodeChar)
    expect(CHARACTER_TAGS.wordJoiner).toBe(tagSignatures.wordJoiner.placeholder)
  })

  test('every one of them has a mark: none is left to show as nothing', () => {
    for (const ch of Object.values(CHARACTER_TAGS)) {
      expect(specialCharMark(ch)).not.toBeNull()
    }
  })
})
