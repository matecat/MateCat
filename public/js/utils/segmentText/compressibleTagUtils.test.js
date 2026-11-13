import {
  classifyCompressibleTag,
  hasCompressibleTags,
  createTagNumberer,
  getGEquivText,
} from './compressibleTagUtils'

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

describe('classifyCompressibleTag', () => {
  test('classifies non-dataRef open/close', () => {
    expect(classifyCompressibleTag(open1)).toMatchObject({
      role: 'open',
      hasDataRef: false,
    })
    expect(classifyCompressibleTag(close1)).toMatchObject({
      role: 'close',
      hasDataRef: false,
    })
  })

  test('classifies dataRef open/close and derives base id', () => {
    expect(classifyCompressibleTag(dOpen)).toMatchObject({
      role: 'open',
      hasDataRef: true,
      baseId: 'source1',
    })
    expect(classifyCompressibleTag(dClose)).toMatchObject({
      role: 'close',
      hasDataRef: true,
      baseId: 'source1',
    })
  })

  test('returns null for semantic ph tags and ph without ctype', () => {
    expect(classifyCompressibleTag(semantic)).toBeNull()
    expect(classifyCompressibleTag(noCtype)).toBeNull()
    expect(classifyCompressibleTag('')).toBeNull()
    expect(classifyCompressibleTag(undefined)).toBeNull()
  })
})

describe('hasCompressibleTags', () => {
  test('true when at least one pc-carrying ph tag is present', () => {
    expect(hasCompressibleTags(open1 + semantic)).toBe(true)
    expect(hasCompressibleTags(dOpen)).toBe(true)
  })

  test('false when only semantic / no pc ph tags', () => {
    expect(hasCompressibleTags(semantic + noCtype)).toBe(false)
    expect(hasCompressibleTags('plain text')).toBe(false)
    expect(hasCompressibleTags('')).toBe(false)
    expect(hasCompressibleTags(undefined)).toBe(false)
  })
})

describe('createTagNumberer', () => {
  test('open and close of a pair share the same index', () => {
    const n = createTagNumberer()
    expect(n(open1)).toEqual({index: 0, role: 'open'})
    expect(n(close1)).toEqual({index: 0, role: 'close'})
  })

  test('sequential non-dataRef pairs increment', () => {
    const n = createTagNumberer()
    expect(n(open1).index).toBe(0)
    expect(n(close1).index).toBe(0)
    expect(n(open1).index).toBe(1)
    expect(n(close1).index).toBe(1)
  })

  test('nested non-dataRef pairs pair by stack (0,1,1,0)', () => {
    const n = createTagNumberer()
    expect(n(open1).index).toBe(0)
    expect(n(open1).index).toBe(1)
    expect(n(close1).index).toBe(1)
    expect(n(close1).index).toBe(0)
  })

  test('dataRef pairs by base id, independent of order', () => {
    const n = createTagNumberer()
    expect(n(dOpen).index).toBe(0)
    expect(n(dOpen2).index).toBe(1)
    expect(n(dClose).index).toBe(0)
    expect(n(dClose2).index).toBe(1)
  })

  test('non-pc ph tags return null and do not consume a number', () => {
    const n = createTagNumberer()
    expect(n(semantic)).toBeNull()
    expect(n(open1).index).toBe(0)
  })

  test('inherited index is honoured and keeps the counter monotonic', () => {
    const n = createTagNumberer()
    expect(n(dOpen, 5).index).toBe(5)
    expect(n(open1).index).toBe(6)
  })
})

// g/x tags with equiv-text (XLIFF 1.2). base64 payloads:
//   WyMkZHA0XQ==                       -> [#$dp4]
//   e3sjJmd0OyBlbWFpbC1zdWJqZWN0fX0=   -> {{#&gt; email-subject}}
const gEquiv = '<g id="1" equiv-text="base64:WyMkZHA0XQ==">'
const gEquiv2 = '<g id="2" equiv-text="base64:WyMkZHA0XQ==">'
const gPlain = '<g id="3">'
const gSelfClosing = '<g id="4" equiv-text="base64:WyMkZHA0XQ=="/>'
const gClose = '</g>'
const xEquiv =
  '<ph id="mtc_1" ctype="x-original_x" x-orig="AAA" equiv-text="base64:e3sjJmd0OyBlbWFpbC1zdWJqZWN0fX0="/>'
const xNoEquiv = '<ph id="mtc_2" ctype="x-original_x" x-orig="AAA"/>'

describe('getGEquivText', () => {
  test('decodes the equiv-text of an opening g tag', () => {
    expect(getGEquivText(gEquiv)).toBe('[#$dp4]')
  })

  test('null for a g without equiv-text, a self-closing g and a close', () => {
    expect(getGEquivText(gPlain)).toBeNull()
    expect(getGEquivText(gSelfClosing)).toBeNull()
    expect(getGEquivText(gClose)).toBeNull()
    expect(getGEquivText(undefined)).toBeNull()
  })
})

describe('classifyCompressibleTag - g and x tags', () => {
  test('an opening g with equiv-text is a g open', () => {
    expect(classifyCompressibleTag(gEquiv)).toMatchObject({
      kind: 'g',
      role: 'open',
      id: '1',
    })
  })

  test('an x-original_x ph is not compressible, even with equiv-text', () => {
    expect(classifyCompressibleTag(xEquiv)).toBeNull()
  })

  test('pc tags report the pc kind', () => {
    expect(classifyCompressibleTag(open1).kind).toBe('pc')
  })

  test('plain g, self-closing g, </g> and x without equiv-text are not compressible', () => {
    expect(classifyCompressibleTag(gPlain)).toBeNull()
    expect(classifyCompressibleTag(gSelfClosing)).toBeNull()
    expect(classifyCompressibleTag(gClose)).toBeNull()
    expect(classifyCompressibleTag(xNoEquiv)).toBeNull()
  })
})

describe('hasCompressibleTags - g and x tags', () => {
  test('true for a g carrying equiv-text', () => {
    expect(hasCompressibleTags(`a ${gEquiv}b${gClose}`)).toBe(true)
  })

  test('false for an x-original_x ph, even with equiv-text', () => {
    expect(hasCompressibleTags(`a ${xEquiv} b`)).toBe(false)
  })

  test('false for g/x tags without equiv-text', () => {
    expect(hasCompressibleTags(`${gPlain}b${gClose}`)).toBe(false)
    expect(hasCompressibleTags(gSelfClosing)).toBe(false)
    expect(hasCompressibleTags(xNoEquiv)).toBe(false)
  })
})

describe('createTagNumberer - g and x tags', () => {
  test('a g pair shares one number; its </g> is the close', () => {
    const n = createTagNumberer()
    expect(n(gEquiv)).toEqual({index: 0, role: 'open'})
    expect(n(gClose)).toEqual({index: 0, role: 'close'})
  })

  test('an x-original_x ph is not numbered and does not consume a number', () => {
    const n = createTagNumberer()
    expect(n(xEquiv)).toBeNull()
    expect(n(gEquiv)).toEqual({index: 0, role: 'open'})
  })

  test('a plain g nested in an equiv-text g does not steal its </g>', () => {
    const n = createTagNumberer()
    expect(n(gEquiv)).toEqual({index: 0, role: 'open'})
    expect(n(gPlain)).toBeNull()
    expect(n(gClose)).toBeNull() // closes the plain g
    expect(n(gClose)).toEqual({index: 0, role: 'close'})
  })

  test('an equiv-text g nested in a plain g keeps its own close', () => {
    const n = createTagNumberer()
    expect(n(gPlain)).toBeNull()
    expect(n(gEquiv)).toEqual({index: 0, role: 'open'})
    expect(n(gClose)).toEqual({index: 0, role: 'close'})
    expect(n(gClose)).toBeNull()
  })

  test('pc and g share one counter in document order', () => {
    const n = createTagNumberer()
    expect(n(gEquiv).index).toBe(0)
    expect(n(open1).index).toBe(1)
    expect(n(close1).index).toBe(1)
    expect(n(gClose).index).toBe(0)
    expect(n(gEquiv2).index).toBe(2)
  })

  test('an orphan </g> is not numbered', () => {
    expect(createTagNumberer()(gClose)).toBeNull()
  })

  test('an inherited g index is honoured and keeps the counter monotonic', () => {
    const n = createTagNumberer()
    expect(n(gEquiv, 4)).toEqual({index: 4, role: 'open'})
    expect(n(gClose)).toEqual({index: 4, role: 'close'})
    expect(n(gEquiv2).index).toBe(5)
  })
})
