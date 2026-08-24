import SegmentSource from './SegmentSource'
import {segmentsMock} from '../../../mocks/segmentsMock'

describe('SegmentSource.updateOptionsToolbarVisibility', () => {
  beforeEach(() => {
    global.config = {
      ...global.config,
      source_code: 'en-US',
      target_code: 'it-IT',
      tag_projection_languages: {},
    }
  })

  const buildInstance = () =>
    new SegmentSource({
      segment: segmentsMock[0],
      splitGroupLength: 1,
    })

  test('does not throw when the editor ref is null', () => {
    const instance = buildInstance()
    instance.editor = null
    instance.setState = jest.fn()
    instance.helpAiAssistant = jest.fn()

    expect(() => instance.updateOptionsToolbarVisibility()).not.toThrow()
    expect(instance.setState).not.toHaveBeenCalled()
    expect(instance.helpAiAssistant).not.toHaveBeenCalled()
  })

  test('updates the toolbar visibility when the editor ref is set', () => {
    const instance = buildInstance()
    instance.editor = {
      _latestEditorState: {
        getSelection: () => ({isCollapsed: () => false}),
      },
    }
    instance.setState = jest.fn()
    instance.helpAiAssistant = jest.fn()

    instance.updateOptionsToolbarVisibility()

    expect(instance.setState).toHaveBeenCalledWith({
      isShowingOptionsToolbar: true,
    })
    expect(instance.helpAiAssistant).toHaveBeenCalled()
  })
})
