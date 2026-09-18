import {JobMetadataProxy} from './JobMetadataProxy'

const makeProxy = (jobMetadata) => new JobMetadataProxy(jobMetadata).proxy

describe('JobMetadataProxy', () => {
  test('a job field wins over the same field on project', () => {
    const proxy = makeProxy({
      job: {tm_prioritization: true},
      project: {tm_prioritization: false},
    })

    expect(proxy.tm_prioritization).toBe(true)
  })

  test('falls back to the project field when the job does not override it', () => {
    const proxy = makeProxy({
      job: {},
      project: {mt_quality_value_in_editor: 80},
    })

    expect(proxy.mt_quality_value_in_editor).toBe(80)
  })

  test('falls back to a root-level field when neither job nor project has it', () => {
    const proxy = makeProxy({job: {}, project: {}, sid: 42})

    expect(proxy.sid).toBe(42)
  })

  test('returns undefined for a field defined nowhere', () => {
    const proxy = makeProxy({job: {}, project: {}})

    expect(proxy.does_not_exist).toBeUndefined()
  })

  test('merges a nested object, preferring job keys but keeping project-only keys', () => {
    const proxy = makeProxy({
      job: {mt_extra: {lara_style: 'creative'}},
      project: {
        mt_extra: {lara_style: 'faithful', deepl_id_glossary: 'g1'},
      },
    })

    expect(proxy.mt_extra).toEqual({
      lara_style: 'creative',
      deepl_id_glossary: 'g1',
    })
  })

  test('returns the raw job value without merging when project has no object at that key', () => {
    const proxy = makeProxy({
      job: {mt_extra: {lara_style: 'creative'}},
      project: {},
    })

    expect(proxy.mt_extra).toEqual({lara_style: 'creative'})
  })

  test('a job array overrides a project array outright, not merged by index', () => {
    const proxy = makeProxy({
      job: {mandatory_issues: ['r2']},
      project: {mandatory_issues: ['r1', 'r2']},
    })

    expect(Array.isArray(proxy.mandatory_issues)).toBe(true)
    expect(proxy.mandatory_issues).toEqual(['r2'])
  })

  test('falls back to the project array when the job does not define it', () => {
    const proxy = makeProxy({
      job: {},
      project: {mandatory_issues: ['r1', 'r2']},
    })

    expect(Array.isArray(proxy.mandatory_issues)).toBe(true)
    expect(proxy.mandatory_issues).toEqual(['r1', 'r2'])
  })

  test('a null job value does not attempt to merge and wins outright', () => {
    const proxy = makeProxy({
      job: {mt_extra: null},
      project: {mt_extra: {lara_style: 'faithful'}},
    })

    expect(proxy.mt_extra).toBeNull()
  })
})
