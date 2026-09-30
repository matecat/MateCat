import * as DraftMatecatConstants from './editorConstants'

test('exports all decorator name constants', () => {
  expect(DraftMatecatConstants.TAGS_DECORATOR).toBe('tags')
  expect(DraftMatecatConstants.LEXIQA_DECORATOR).toBe('lexiqa')
  expect(DraftMatecatConstants.GLOSSARY_DECORATOR).toBe('glossary')
  expect(DraftMatecatConstants.QA_GLOSSARY_DECORATOR).toBe('qaCheckGlossary')
  expect(DraftMatecatConstants.QA_BLACKLIST_DECORATOR).toBe('qaCheckBlacklist')
  expect(DraftMatecatConstants.SEARCH_DECORATOR).toBe('search')
  expect(DraftMatecatConstants.SPLIT_DECORATOR).toBe('split')
  expect(DraftMatecatConstants.ICU_DECORATOR).toBe('icu')
})
