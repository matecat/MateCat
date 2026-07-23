import {
  classifyPcPhTag,
  hasCompressiblePhTags,
  createPcNumberer,
} from './pcTagUtils'

const open1 =
  '<ph id="mtc_1" ctype="x-original_pc_open" equiv-text="base64:AAA"/>'
const close1 =
  '<ph id="mtc_2" ctype="x-original_pc_close" equiv-text="base64:BBB"/>'
const dOpen =
  '<ph id="source1_1" ctype="x-pc_open_data_ref" equiv-text="base64:CCC" x-orig="base64:DDD"/>'
const dClose =
  '<ph id="source1_2" ctype="x-pc_close_data_ref" equiv-text="base64:EEE" x-orig="base64:FFF"/>'
const dOpen2 =
  '<ph id="source2_1" ctype="x-pc_open_data_ref" equiv-text="base64:GGG" x-orig="base64:HHH"/>'
const dClose2 =
  '<ph id="source2_2" ctype="x-pc_close_data_ref" equiv-text="base64:III" x-orig="base64:JJJ"/>'
const semantic = '<ph id="mtc_3" ctype="x-html" equiv-text="base64:KKK"/>'
const noCtype = '<ph id="mtc_9" equiv-text="base64:LLL"/>'

describe('classifyPcPhTag', () => {
  test('classifies non-dataRef open/close', () => {
    expect(classifyPcPhTag(open1)).toMatchObject({role: 'open', hasDataRef: false})
    expect(classifyPcPhTag(close1)).toMatchObject({role: 'close', hasDataRef: false})
  })

  test('classifies dataRef open/close and derives base id', () => {
    expect(classifyPcPhTag(dOpen)).toMatchObject({
      role: 'open',
      hasDataRef: true,
      baseId: 'source1',
    })
    expect(classifyPcPhTag(dClose)).toMatchObject({
      role: 'close',
      hasDataRef: true,
      baseId: 'source1',
    })
  })

  test('returns null for semantic ph tags and ph without ctype', () => {
    expect(classifyPcPhTag(semantic)).toBeNull()
    expect(classifyPcPhTag(noCtype)).toBeNull()
    expect(classifyPcPhTag('')).toBeNull()
    expect(classifyPcPhTag(undefined)).toBeNull()
  })
})

describe('hasCompressiblePhTags', () => {
  test('true when at least one pc-carrying ph tag is present', () => {
    expect(hasCompressiblePhTags(open1 + semantic)).toBe(true)
    expect(hasCompressiblePhTags(dOpen)).toBe(true)
  })

  test('false when only semantic / no pc ph tags', () => {
    expect(hasCompressiblePhTags(semantic + noCtype)).toBe(false)
    expect(hasCompressiblePhTags('plain text')).toBe(false)
    expect(hasCompressiblePhTags('')).toBe(false)
    expect(hasCompressiblePhTags(undefined)).toBe(false)
  })
})

describe('createPcNumberer', () => {
  test('open and close of a pair share the same index', () => {
    const n = createPcNumberer()
    expect(n(open1)).toEqual({index: 0, role: 'open'})
    expect(n(close1)).toEqual({index: 0, role: 'close'})
  })

  test('sequential non-dataRef pairs increment', () => {
    const n = createPcNumberer()
    expect(n(open1).index).toBe(0)
    expect(n(close1).index).toBe(0)
    expect(n(open1).index).toBe(1)
    expect(n(close1).index).toBe(1)
  })

  test('nested non-dataRef pairs pair by stack (0,1,1,0)', () => {
    const n = createPcNumberer()
    expect(n(open1).index).toBe(0)
    expect(n(open1).index).toBe(1)
    expect(n(close1).index).toBe(1)
    expect(n(close1).index).toBe(0)
  })

  test('dataRef pairs by base id, independent of order', () => {
    const n = createPcNumberer()
    expect(n(dOpen).index).toBe(0)
    expect(n(dOpen2).index).toBe(1)
    expect(n(dClose).index).toBe(0)
    expect(n(dClose2).index).toBe(1)
  })

  test('non-pc ph tags return null and do not consume a number', () => {
    const n = createPcNumberer()
    expect(n(semantic)).toBeNull()
    expect(n(open1).index).toBe(0)
  })

  test('inherited index is honoured and keeps the counter monotonic', () => {
    const n = createPcNumberer()
    expect(n(dOpen, 5).index).toBe(5)
    expect(n(open1).index).toBe(6)
  })
})
