import * as indexExports from './index'
import {SegmentFooterTabGlossary} from './SegmentFooterTabGlossary'

describe('SegmentFooterTabGlossary/index', () => {
  test('re-exports SegmentFooterTabGlossary', () => {
    expect(indexExports.SegmentFooterTabGlossary).toBe(SegmentFooterTabGlossary)
  })
})
