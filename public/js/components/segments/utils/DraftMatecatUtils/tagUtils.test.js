import {setTagSignatureMiddleware} from './tagModel'
import {
  excludeSomeTagsTransformToText,
  excludeSomeTagsFromText,
  transformTagsToHtml,
  transformTagsToLexiqaText,
  transformTagsToText,
  removePlaceholdersForGlossary,
  decodeHtmlEntities,
  encodeHtmlEntities,
  removeTagsFromText,
  textHasTags,
  autoFillTagsInTarget,
  hasDataOriginalTags,
  checkXliffTagsInText,
  removeZeroWidthSpace,
  decodePlaceholdersToPlainText,
  encodePlaceholdersToTags,
  decodeTagsToUnicodeChar,
  encodeTagsFromUnicodeChar,
} from './tagUtils'

setTagSignatureMiddleware('space', () => false)

test('Tags and placeholders to html', () => {
  let text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; &amp;gt; <g id="2"/> <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  let resultHtml =
    'test <span contenteditable="false" class="tag small tag-open">​1​</span>tag<span contenteditable="false" class="tag small tag-close">​1​</span> ph con &lt; &gt; &amp;lt; &amp;gt; <span contenteditable="false" class="tag small tag-selfclosed">2</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;p&gt;</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;strong&gt;</span> <span contenteditable="false" class="tag small tag-selfclosed tag-ph">&lt;/strong&gt;</span>pippoL&apos; placeholder &nbsp; elle-même'

  expect(transformTagsToHtml(text)).toBe(resultHtml)
})

test('Tags ph to text', () => {
  let text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  let resultText =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <p> <strong> </strong>pippoL&apos; placeholder &nbsp; elle-même'
  expect(transformTagsToText(text)).toBe(resultText)
})

test('Placeholders to text', () => {
  let text =
    'test tag ph con ##$_SPLIT$## &lt; &gt; ##$_0A$## &amp;lt; ##$_09$## &amp;gt; ##$_A0$## ##$_0D$## <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; ##$_A0$##elle-même'

  let resultText =
    'test tag ph con  &lt; &gt; \n &amp;lt; ⇥ &amp;gt; ° \r <p> <strong> </strong>pippoL&apos; placeholder &nbsp; °elle-même'

  expect(transformTagsToText(text)).toBe(resultText)
})

test('Convert tags to text and exclude tag g', () => {
  const text =
    'test <g id="1">tag</g> ph con &lt; &gt; &amp;lt; <g id="2"></g> &amp;gt; <ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O3AmZ3Q7"/> <ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O3N0cm9uZyZndDs="/> <ph id="mtc_3" ctype="x-html" equiv-text="base64:Jmx0Oy9zdHJvbmcmZ3Q7"/>pippoL&apos; placeholder &nbsp; elle-même'

  const resultText =
    'test tag ph con &lt; &gt; &amp;lt;  &amp;gt; <p> <strong> </strong>pippoL&apos; placeholder &nbsp; elle-même'

  expect(excludeSomeTagsTransformToText(text, ['g'])).toBe(resultText)
})

test('Convert tags to text and exclude a plain-regex placeholder tag', () => {
  const text = 'value ##$_A0$## end'

  const resultText = 'value  end'

  expect(excludeSomeTagsTransformToText(text, ['nbsp'])).toBe(resultText)
})

test('Convert tags to text keeping a non-excluded plain-regex placeholder tag', () => {
  const text = 'value ##$_A0$## end'

  expect(excludeSomeTagsTransformToText(text, ['g'])).toBe(text)
})

test('excludeSomeTagsFromText strips every occurrence of the excluded tag', () => {
  const text = 'test <g id="1">tag</g> end'

  expect(excludeSomeTagsFromText(text, ['g'])).toBe('test tag end')
})

test('excludeSomeTagsFromText strips a plain-regex placeholder tag', () => {
  const text = 'value ##$_A0$## end'

  expect(excludeSomeTagsFromText(text, ['nbsp'])).toBe('value  end')
})

test('excludeSomeTagsFromText leaves the text untouched when no tag is excluded', () => {
  const text = 'test <g id="1">tag</g> end'

  expect(excludeSomeTagsFromText(text, [])).toBe(text)
})

test('removePlaceholdersForGlossary replaces the nbsp placeholder with a space', () => {
  expect(removePlaceholdersForGlossary('a##$_A0$##b')).toBe('a b')
})

test('decodeHtmlEntities converts basic html entities back to characters', () => {
  expect(decodeHtmlEntities('a &lt;b&gt; c &amp; d')).toBe('a <b> c & d')
})

test('encodeHtmlEntities escapes basic characters into html entities', () => {
  expect(encodeHtmlEntities('a <b> c & d')).toBe('a &lt;b&gt; c &amp; d')
})

test('removeTagsFromText strips xliff tags from the text', () => {
  expect(removeTagsFromText('test <g id="1">tag</g> end')).toBe('test tag end')
})

test('removeTagsFromText returns falsy input untouched', () => {
  expect(removeTagsFromText('')).toBe('')
  expect(removeTagsFromText(null)).toBeNull()
})

describe('textHasTags', () => {
  test('returns true when the text contains an xliff tag', () => {
    expect(textHasTags('test <g id="1">tag</g> end')).toBe(true)
  })

  test('returns false when the text has no xliff tags', () => {
    expect(textHasTags('plain text')).toBe(false)
  })

  test('returns the falsy input untouched', () => {
    expect(textHasTags('')).toBe('')
  })
})

describe('autoFillTagsInTarget', () => {
  test('appends tags present in the source but missing from the translation', () => {
    const result = autoFillTagsInTarget({
      segment: 'test <g id="1">tag</g> end',
      translation: 'translated end',
    })

    expect(result).toBe('translated end<g id="1"></g>')
  })

  test('does not duplicate tags already present in the translation', () => {
    const result = autoFillTagsInTarget({
      segment: 'test <g id="1">tag</g> end',
      translation: 'translated <g id="1">tag</g> end',
    })

    expect(result).toBe('translated <g id="1">tag</g> end')
  })
})

describe('hasDataOriginalTags', () => {
  test('returns true when the original text contains xliff tags', () => {
    expect(hasDataOriginalTags('test <g id="1">tag</g> end')).toBe(true)
  })

  test('returns false when the original text is undefined', () => {
    expect(hasDataOriginalTags(undefined)).toBe(false)
  })
})

describe('checkXliffTagsInText', () => {
  test('returns true when the text contains an xliff tag', () => {
    expect(checkXliffTagsInText('test <g id="1">tag</g> end')).toBe(true)
  })

  test('returns false when the text has no xliff tags', () => {
    expect(checkXliffTagsInText('plain text')).toBe(false)
  })
})

test('removeZeroWidthSpace strips zero-width space characters', () => {
  const zwsp = String.fromCharCode(parseInt('200B', 16))

  expect(removeZeroWidthSpace(`a${zwsp}b${zwsp}c`)).toBe('abc')
})

describe('decodePlaceholdersToPlainText', () => {
  test('converts encoded whitespace placeholders into their raw characters', () => {
    const text = 'a##$_0A$##b##$_0D$##c##$_09$##d##$_A0$##e'

    expect(decodePlaceholdersToPlainText(text)).toBe(
      'a\nb\rc⇥d°e',
    )
  })

  test('returns falsy input untouched', () => {
    expect(decodePlaceholdersToPlainText('')).toBe('')
  })
})

describe('encodePlaceholdersToTags', () => {
  test('converts raw whitespace characters into their encoded placeholders', () => {
    const text = 'a\nb\rc⇥d°e'

    expect(encodePlaceholdersToTags(text)).toBe(
      'a##$_0A$##b##$_0D$##c##$_09$##d##$_A0$##e',
    )
  })

  test('returns falsy input untouched', () => {
    expect(encodePlaceholdersToTags('')).toBe('')
  })
})

describe('decodeTagsToUnicodeChar', () => {
  test('converts encoded whitespace placeholders into their unicode chars', () => {
    const text = 'a##$_0A$##b##$_0D$##c##$_09$##d##$_A0$##e'

    expect(decodeTagsToUnicodeChar(text)).toBe('a\nb\rc	d e')
  })

  test('returns falsy input untouched', () => {
    expect(decodeTagsToUnicodeChar('')).toBe('')
  })
})

describe('encodeTagsFromUnicodeChar', () => {
  test('converts escaped unicode sequences into encoded placeholders', () => {
    const text = 'a\\u000Ab\\u000Dc\\u0009d e'

    expect(encodeTagsFromUnicodeChar(text)).toBe(
      'a##$_0A$##b##$_0D$##c##$_09$##d##$_A0$##e',
    )
  })

  test('returns falsy input untouched', () => {
    expect(encodeTagsFromUnicodeChar('')).toBe('')
  })
})

test('Lexiqa text keeps offsets correct after decoded html entities before a tag', () => {
  const text =
    'They started booking stays through Airbnb.&amp;nbsp;&amp;nbsp;<ph id="mtc_1" ctype="x-html" equiv-text="base64:Jmx0O2JyIC8mZ3Q7"/><ph id="mtc_2" ctype="x-html" equiv-text="base64:Jmx0O2JyIC8mZ3Q7"/>At first, Maria paid for these out of her own pocket, and it was putting a strain on her already limited finances—until Aladina Fundación connected her with Airbnb.org.'

  const result = transformTagsToLexiqaText(text)

  expect(result).toContain(
    'Airbnb.&nbsp;&nbsp;<<br />><<br />>At first, Maria paid for these out of her own pocket, and it was putting a strain on her already limited finances—until Aladina Fundación connected her with Airbnb.org.',
  )
})
