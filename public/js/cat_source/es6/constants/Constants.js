export const JOB_WORD_CONT_TYPE = {
  RAW: 'raw',
  EQUIVALENT: 'equivalent',
}

export const REVISE_STEP_NUMBER = {
  REVISE1: 1,
  REVISE2: 2,
}

export const SEGMENTS_STATUS = {
  APPROVED: 'APPROVED',
  APPROVED2: 'APPROVED2',
  NEW: 'NEW',
  DRAFT: 'DRAFT',
  TRANSLATED: 'TRANSLATED',
  UNTRANSLATED: 'UNTRANSLATED',
  UNAPPROVED: 'UNAPPROVED',
}

export const ANALYSIS_STATUS = {
  NEW: 'NEW',
  BUSY: 'BUSY',
  EMPTY: 'EMPTY',
  DONE: 'DONE',
  NOT_TO_ANALYZE: 'NOT_TO_ANALYZE',
}

export const UNIT_COUNT = {
  WORDS: 'words',
  CHARACTERS: 'characters',
}

export const EMAIL_PATTERN =
  /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
