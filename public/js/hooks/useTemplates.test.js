import {normalizeTemplatesWithNullProps} from './useTemplates'
import defaultFilterParams from '../components/settingsPanel/Contents/defaultTemplates/filterParams.json'

describe('normalizeTemplatesWithNullProps', () => {
  // a filters template saved before the markdown column existed is returned
  // by the API with "markdown": null and must open with the default settings
  test('fills a null markdown block from the default filters template', () => {
    const saved = {
      ...defaultFilterParams,
      id: 7,
      name: 'Saved before markdown',
      json: {...defaultFilterParams.json, translate_keys: ['title']},
      markdown: null,
    }

    const [normalized] = normalizeTemplatesWithNullProps(
      [saved],
      defaultFilterParams,
    )

    expect(normalized.markdown).toEqual({
      extract_frontmatter: false,
      translate_frontmatter_keys: [],
    })
    expect(normalized.json.translate_keys).toEqual(['title'])
    expect(normalized.name).toBe('Saved before markdown')
  })

  test('keeps a saved markdown block as it is', () => {
    const saved = {
      ...defaultFilterParams,
      id: 8,
      markdown: {
        extract_frontmatter: true,
        do_not_translate_frontmatter_keys: ['slug'],
      },
    }

    const [normalized] = normalizeTemplatesWithNullProps(
      [saved],
      defaultFilterParams,
    )

    expect(normalized.markdown.extract_frontmatter).toBe(true)
    expect(normalized.markdown.do_not_translate_frontmatter_keys).toEqual([
      'slug',
    ])
  })

  test('does not mutate the default template', () => {
    normalizeTemplatesWithNullProps(
      [{id: 9, markdown: {extract_frontmatter: true}}],
      defaultFilterParams,
    )

    expect(defaultFilterParams.markdown.extract_frontmatter).toBe(false)
  })
})
