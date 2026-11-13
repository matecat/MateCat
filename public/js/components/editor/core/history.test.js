import {undo, redo} from '@codemirror/commands'
import {createSegmentState} from './createSegmentEditor'
import {serialize, tagList} from './docModel'

const update = (state, spec) => state.update(spec).state

describe('undo history (restoreDeletedTags)', () => {
  test('undo brings a deleted tag back as a tag, redo deletes it again', () => {
    let state = createSegmentState('a<x id="1"/>b')
    const view = {
      get state() {
        return state
      },
      dispatch: (tr) => {
        state = tr.state
      },
    }
    state = update(state, {changes: {from: 1, to: 12}, userEvent: 'delete'})
    expect(serialize(state)).toBe('ab')
    undo(view)
    expect(serialize(state)).toBe('a<x id="1"/>b')
    expect(tagList(state)).toEqual([{from: 1, to: 12}])
    redo(view)
    expect(serialize(state)).toBe('ab')
    undo(view)
    // still a tag after a second undo: a typed edit inside would be refused
    expect(tagList(state)).toEqual([{from: 1, to: 12}])
  })
})
