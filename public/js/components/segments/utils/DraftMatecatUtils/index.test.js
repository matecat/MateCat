import DraftMatecatUtils from './index'

// Smoke test: this module is a pure re-export barrel. Importing it exercises
// every import statement (and each named export's module top-level code);
// the individual functions have their own dedicated test files.
describe('DraftMatecatUtils barrel export', () => {
  test('exposes the expected entity/fragment/segment/decorator utilities', () => {
    expect(typeof DraftMatecatUtils.getEntities).toBe('function')
    expect(typeof DraftMatecatUtils.encodeContent).toBe('function')
    expect(typeof DraftMatecatUtils.decodeSegment).toBe('function')
    expect(typeof DraftMatecatUtils.insertText).toBe('function')
    expect(typeof DraftMatecatUtils.getSelectedText).toBe('function')
    expect(typeof DraftMatecatUtils.activateSearch).toBe('function')
    expect(typeof DraftMatecatUtils.activateLexiqa).toBe('function')
    expect(typeof DraftMatecatUtils.activateGlossary).toBe('function')
    expect(typeof DraftMatecatUtils.buildFragmentFromJson).toBe('function')
    expect(typeof DraftMatecatUtils.buildFragmentFromText).toBe('function')
    expect(typeof DraftMatecatUtils.duplicateFragment).toBe('function')
    expect(typeof DraftMatecatUtils.canDecorateRange).toBe('function')
    expect(typeof DraftMatecatUtils.addTagEntityToEditor).toBe('function')
  })
})
