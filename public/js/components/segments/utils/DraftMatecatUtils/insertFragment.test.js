import insertFragment from './insertFragment'

// NOTE: this module references `Modifier` and `EditorState` without importing
// them (pre-existing bug, out of scope for this test pass), and it is never
// invoked anywhere else in the codebase (confirmed via a project-wide grep).
// Calling it always throws a ReferenceError before it can do anything useful;
// this test simply documents that current, real behaviour rather than
// asserting a happy path that cannot occur.
describe('insertFragment', () => {
  test('throws a ReferenceError because Modifier/EditorState are not imported', () => {
    expect(() => insertFragment({}, {})).toThrow(ReferenceError)
  })
})
