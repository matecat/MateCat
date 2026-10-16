import {ANALYSIS_WORKFLOW_TYPES} from '../../../constants/Constants'
import {formatCount, getBuckets} from './buckets'

const STANDARD_RATES = {
  NO_MATCH: 100,
  REPETITIONS: 30,
  INTERNAL: 60,
  '50%-74%': 100,
  '75%-84%': 60,
  '85%-94%': 60,
  '95%-99%': 60,
  '100%': 30,
  '100%_PUBLIC': 30,
  ICE: 0,
  MT: 72,
  ICE_MT: 72,
}

const MTQE_RATES = {
  ice: 0,
  tm_100: 30,
  tm_100_public: 30,
  repetitions: 30,
  ice_mt: 5,
  top_quality_mt: 10,
  higher_quality_mt: 30,
  standard_quality_mt: 70,
}

const match = (type, raw, equivalent) => ({type, raw, equivalent})

describe('getBuckets STANDARD', () => {
  test('lists the buckets in order with their labels and rates', () => {
    const buckets = getBuckets({
      matches: [],
      payableRates: {...STANDARD_RATES, ICE_MT: 50},
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.map(({type, label, rate}) => [type, label, rate])).toEqual([
      ['new', 'New', 100],
      ['repetitions', 'Repetitions', 30],
      ['internal', 'Internal 75-99%', 60],
      ['tm_50_74', 'TM partial 50-74%', 100],
      ['tm_75_84', 'TM partial 75-84%', 60],
      ['tm_85_94', 'TM partial 85-94%', 60],
      ['tm_95_99', 'TM partial 95-99%', 60],
      ['tm_100', 'TM 100%', 30],
      ['tm_100_public', 'Public TM 100%', 30],
      ['ice', 'TM 101%', 0],
      ['MT', 'MT', 72],
      ['ice_mt', 'Top-quality machine translation', 50],
    ])
  })

  test('merges ice_mt into MT when the rates are equal', () => {
    const buckets = getBuckets({
      matches: [match('MT', 100, 72), match('ice_mt', 20, 14)],
      payableRates: STANDARD_RATES,
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'ice_mt')).toBeUndefined()
    expect(buckets.find(({type}) => type === 'MT')).toMatchObject({
      raw: 120,
      equivalent: 86,
      empty: false,
    })
    expect(buckets).toHaveLength(11)
  })

  test('merges ice_mt into MT when the ICE_MT rate is missing', () => {
    const {ICE_MT, ...rates} = STANDARD_RATES // eslint-disable-line no-unused-vars
    const buckets = getBuckets({
      matches: [match('MT', 10, 7), match('ice_mt', 5, 3)],
      payableRates: rates,
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'ice_mt')).toBeUndefined()
    expect(buckets.find(({type}) => type === 'MT').raw).toBe(15)
  })

  test('merges ice_mt into MT when the ICE_MT rate is null', () => {
    const buckets = getBuckets({
      matches: [match('MT', 10, 7), match('ice_mt', 5, 3)],
      payableRates: {...STANDARD_RATES, ICE_MT: null},
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'ice_mt')).toBeUndefined()
    expect(buckets.find(({type}) => type === 'MT')).toMatchObject({
      raw: 15,
      equivalent: 10,
    })
  })

  test('lists ice_mt on its own when its rate is 0', () => {
    const buckets = getBuckets({
      matches: [match('MT', 10, 7), match('ice_mt', 5, 0)],
      payableRates: {...STANDARD_RATES, ICE_MT: 0},
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'MT').raw).toBe(10)
    expect(buckets.find(({type}) => type === 'ice_mt')).toMatchObject({
      rate: 0,
      raw: 5,
      equivalent: 0,
      empty: false,
    })
  })

  test('copes with null payable rates', () => {
    const buckets = getBuckets({
      matches: [match('new', 10, 10), match('ice_mt', 5, 3)],
      payableRates: null,
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets).toHaveLength(11)
    expect(buckets.find(({type}) => type === 'new').rate).toBeUndefined()
    expect(buckets.find(({type}) => type === 'ice').rate).toBe(0)
    expect(buckets.find(({type}) => type === 'MT')).toMatchObject({
      raw: 5,
      equivalent: 3,
    })
  })

  test('lists ice_mt on its own when the rates differ', () => {
    const buckets = getBuckets({
      matches: [match('MT', 100, 72), match('ice_mt', 20, 10)],
      payableRates: {...STANDARD_RATES, ICE_MT: 50},
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'MT')).toMatchObject({
      raw: 100,
      equivalent: 72,
    })
    expect(buckets.find(({type}) => type === 'ice_mt')).toMatchObject({
      raw: 20,
      equivalent: 10,
      rate: 50,
      empty: false,
    })
  })

  test('flags buckets with no raw count as empty', () => {
    const buckets = getBuckets({
      matches: [match('new', 10, 10), match('ice_mt', 0, 0)],
      payableRates: {...STANDARD_RATES, ICE_MT: 50},
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.filter(({empty}) => !empty).map(({type}) => type)).toEqual([
      'new',
    ])
    expect(buckets.find(({type}) => type === 'ice_mt').empty).toBe(true)
  })

  test('shows a missing ICE rate as 0', () => {
    const {ICE, ...rates} = STANDARD_RATES // eslint-disable-line no-unused-vars
    const buckets = getBuckets({
      payableRates: rates,
      workflowType: ANALYSIS_WORKFLOW_TYPES.STANDARD,
    })
    expect(buckets.find(({type}) => type === 'ice').rate).toBe(0)
  })
})

describe('getBuckets MTQE', () => {
  test('lists the MTQE buckets in order with their own rate keys', () => {
    const buckets = getBuckets({
      matches: [match('top_quality_mt', 40, 4), match('MT', 99, 99)],
      payableRates: MTQE_RATES,
      workflowType: ANALYSIS_WORKFLOW_TYPES.MTQE,
    })
    expect(buckets.map(({type, label, rate}) => [type, label, rate])).toEqual([
      ['ice', 'TM 101%', 0],
      ['tm_100', 'TM 100%', 30],
      ['tm_100_public', 'Public TM 100%', 30],
      ['repetitions', 'Repetitions', 30],
      ['ice_mt', 'Top-quality machine translation', 5],
      ['top_quality_mt', 'Premium machine translation', 10],
      ['higher_quality_mt', 'High-quality machine translation', 30],
      ['standard_quality_mt', 'Baseline machine translation', 70],
    ])
    expect(buckets.find(({type}) => type === 'top_quality_mt')).toMatchObject({
      raw: 40,
      equivalent: 4,
      empty: false,
    })
    expect(buckets.find(({type}) => type === 'ice').empty).toBe(true)
  })
})

test('getBuckets returns nothing for an unknown workflow', () => {
  expect(getBuckets({workflowType: 'other'})).toEqual([])
})

test('formatCount rounds and groups thousands', () => {
  expect(formatCount(1234.6)).toBe('1,235')
  expect(formatCount(undefined)).toBe('0')
})
