import {fromDraftTranslation, toDraftTranslation} from './draftTagTextCompat'

describe('a translation sent to set-translation', () => {
  test('carries typed tag-like text unencoded, as Draft sent it', () => {
    expect(toDraftTranslation('A &lt;g&gt; b &lt;/x&gt; &lt;example&gt;')).toBe(
      'A <g> b </x> <example>',
    )
  })

  test('keeps other typed markup and entities encoded', () => {
    expect(toDraftTranslation('&lt;b&gt;bold&lt;/b&gt; a &lt; b &amp; c')).toBe(
      '&lt;b&gt;bold&lt;/b&gt; a &lt; b &amp; c',
    )
  })

  test('leaves the real tags of the segment as they are', () => {
    const translation = 'A <g id="1">b</g> <x id="2"/> &lt;g&gt;'
    expect(toDraftTranslation(translation)).toBe(
      'A <g id="1">b</g> <x id="2"/> <g>',
    )
  })

  test('leaves a missing translation alone', () => {
    expect(toDraftTranslation(undefined)).toBeUndefined()
  })
})

describe('a translation loaded from the server', () => {
  test('shows typed tag-like text that Draft saved unencoded as text again', () => {
    expect(fromDraftTranslation('A <g> b <example> <ph>')).toBe(
      'A &lt;g&gt; b &lt;example&gt; &lt;ph&gt;',
    )
  })

  test('keeps the real tags of the file', () => {
    const translation =
      'A <g id="1">b</g> <x id="2"/> <bx id="3"/><ex id="3"/> <ph id="4" equiv-text="base64:JHt4fQ=="/> <mrk mid="1" mtype="seg">c</mrk>'
    expect(fromDraftTranslation(translation)).toBe(translation)
  })

  test('comes back as the editor sent it, after the round trip through the server', () => {
    const typed = 'A &lt;g&gt; b &lt;example&gt; <g id="1">c</g> &lt;b&gt;'
    expect(fromDraftTranslation(toDraftTranslation(typed))).toBe(typed)
  })
})
