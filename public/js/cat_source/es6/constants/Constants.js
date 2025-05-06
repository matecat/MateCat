export const JOB_STATUS = {
  ACTIVE: 'active',
  ARCHIVED: 'archived',
  CANCELLED: 'cancelled',
}

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

export const NUM_CONTRIBUTION_RESULTS = 3
export const NUM_CONCORDANCE_RESULTS = 10

export const EMAIL_PATTERN =
  /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/

export const METADATA_KEY = 'cross_language_matches'

export const ANLYSIS_BUCKETS_LABELS = {
  MT: 'Machine Translation',
  NO_MATCH: 'New',
  REPETITIONS: 'Repetitions',
  INTERNAL: 'Internal 75-99%',
  '50%-74%': 'TM Partial 50-74%',
  '75%-84%': 'TM Partial 75-84%',
  '85%-94%': 'TM Partial 85-94%',
  '95%-99%': 'TM Partial 95-99%',
  '100%': 'TM 100%',
  '100%_PUBLIC': 'Public TM 100%',
  ICE: 'TM 100% in context',
  ICE_MT: 'Top Quality Machine Translation',
}
