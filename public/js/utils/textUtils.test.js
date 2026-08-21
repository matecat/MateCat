import TextUtils from './textUtils'

// The values the page actually receives. CattoolController assigns the CatUtils constants
// (lib/Utils/Tools/CatUtils.php:40-44) straight to the view, so these are literal placeholders,
// not patterns — which is what makes replaceAll the right call and what this file pins.
const PLACEHOLDERS = {
  lfPlaceholder: '##$_0A$##',
  crPlaceholder: '##$_0D$##',
  tabPlaceholder: '##$_09$##',
  nbspPlaceholder: '##$_A0$##',
}

describe('TextUtils.replacePlaceholder', () => {
  beforeEach(() => {
    global.config = {...PLACEHOLDERS}
  })

  afterEach(() => {
    delete global.config
  })

  test.each([
    ['lfPlaceholder', 'softReturnMonad'],
    ['crPlaceholder', 'crPlaceholder'],
    ['tabPlaceholder', 'tabMarkerMonad'],
    ['nbspPlaceholder', 'nbspPlMark'],
  ])('replaces every %s with its diff marker', (key, marker) => {
    const placeholder = PLACEHOLDERS[key]

    expect(TextUtils.replacePlaceholder(`a${placeholder}b${placeholder}c`)).toBe(
      `a${marker}b${marker}c`,
    )
  })

  test('replaces placeholders of every kind in one pass', () => {
    const string = `one${PLACEHOLDERS.lfPlaceholder}two${PLACEHOLDERS.tabPlaceholder}three${PLACEHOLDERS.nbspPlaceholder}`

    expect(TextUtils.replacePlaceholder(string)).toBe(
      'onesoftReturnMonadtwotabMarkerMonadthreenbspPlMark',
    )
  })

  test('leaves a string carrying no placeholder untouched', () => {
    expect(TextUtils.replacePlaceholder('plain text')).toBe('plain text')
  })

  test('restorePlaceholders puts every marker back, so the diff round trip is closed', () => {
    const markers =
      'asoftReturnMonadb crPlaceholder ctabMarkerMonadd nbspPlMarke'

    expect(TextUtils.restorePlaceholders(markers)).toBe(
      `a${PLACEHOLDERS.lfPlaceholder}b ${PLACEHOLDERS.crPlaceholder} c${PLACEHOLDERS.tabPlaceholder}d ${PLACEHOLDERS.nbspPlaceholder}e`,
    )
  })

  // What the browser check was for: getDiffHtml normalises both sides through replacePlaceholder
  // before diffing, so if it failed to restore them the user would read 'softReturnMonad' on screen
  // wherever a fuzzy match differs from the segment source. A 100% match never reaches this path
  // (SegmentFooterMultiMatches.js:83 assigns the suggestion directly), so only a fuzzy one shows it.
  test('a fuzzy diff restores the placeholders and leaks no marker word', () => {
    const source = `Consistent with the approach${PLACEHOLDERS.lfPlaceholder}the Departments${PLACEHOLDERS.tabPlaceholder}are taking${PLACEHOLDERS.nbspPlaceholder}`
    const suggestion = `Consistent with the coordinated approach${PLACEHOLDERS.lfPlaceholder}the Departments${PLACEHOLDERS.tabPlaceholder}were taking${PLACEHOLDERS.nbspPlaceholder}`

    const html = TextUtils.getDiffHtml(source, suggestion)

    expect(html).toEqual(expect.stringContaining(PLACEHOLDERS.lfPlaceholder))
    expect(html).toEqual(expect.stringContaining(PLACEHOLDERS.tabPlaceholder))
    expect(html).toEqual(expect.stringContaining(PLACEHOLDERS.nbspPlaceholder))
    expect(html).toEqual(expect.stringContaining('<span class="'))
    ;['softReturnMonad', 'tabMarkerMonad', 'nbspPlMark', 'brMarker'].forEach(
      (marker) => expect(html).not.toContain(marker),
    )
  })

  // The regression this file exists for: the template used to quote the interpolation, so the page
  // received the string '/\#\#\$_0A\$\#\#/g' and replaceAll searched for that text — a silent
  // no-op, and the diff was computed over unnormalised placeholders. A pattern-shaped value must
  // not match the placeholder it describes.
  test('a pattern-shaped config value replaces nothing, which is what the old page did', () => {
    global.config = {
      ...PLACEHOLDERS,
      lfPlaceholder: '/\\#\\#\\$_0A\\$\\#\\#/g',
    }

    expect(TextUtils.replacePlaceholder(`a${PLACEHOLDERS.lfPlaceholder}b`)).toBe(
      `a${PLACEHOLDERS.lfPlaceholder}b`,
    )
  })
})
