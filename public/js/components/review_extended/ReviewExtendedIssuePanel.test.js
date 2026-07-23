import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import ReviewExtendedIssuePanel from './ReviewExtendedIssuePanel'
import {SegmentContext} from '../segments/SegmentContext'
import SegmentActions from '../../actions/SegmentActions'
import CatToolActions from '../../actions/CatToolActions'
import CommonUtils from '../../utils/commonUtils'
import SegmentUtils from '../../utils/segmentUtils'
import {setTranslation} from '../../api/setTranslation'
import {editSegmentIssue} from '../../api/editSegmentIssue/editSegmentIssue'
import {sendSegmentVersionIssue} from '../../api/sendSegmentVersionIssue'
import {REVISE_STEP_NUMBER, SEGMENTS_STATUS} from '../../constants/Constants'

jest.mock('../../actions/SegmentActions', () => ({
  setStatus: jest.fn(),
  getSegmentVersionsIssues: jest.fn(),
  issueAdded: jest.fn(),
  setFocusOnEditArea: jest.fn(),
  addClassToSegment: jest.fn(),
}))

jest.mock('../../actions/CatToolActions', () => ({
  reloadQualityReport: jest.fn(),
  processErrors: jest.fn(),
}))

jest.mock('../../utils/commonUtils', () => ({
  genericErrorAlertMessage: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  createSetTranslationRequest: jest.fn(() => ({requestBuilt: true})),
}))

jest.mock('../../api/setTranslation', () => ({
  setTranslation: jest.fn(),
}))

jest.mock('../../api/editSegmentIssue/editSegmentIssue', () => ({
  editSegmentIssue: jest.fn(),
}))

jest.mock('../../api/sendSegmentVersionIssue', () => ({
  sendSegmentVersionIssue: jest.fn(),
}))

const flatCategories = [
  {
    id: '10',
    label: 'Accuracy',
    severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  },
  {
    id: '20',
    label: 'Fluency',
    severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  },
]

const nestedCategoriesFlatOnly = [
  {
    id: '10',
    label: 'Accuracy',
    subcategories: [],
    severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  },
  {
    id: '20',
    label: 'Fluency',
    subcategories: [],
    severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  },
]

const nestedCategoriesWithSub = [
  {
    id: '10',
    label: 'Accuracy',
    subcategories: [
      {
        id: '11',
        label: 'Mistranslation',
        severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
      },
    ],
  },
  {
    id: '20',
    label: 'Fluency',
    subcategories: [],
    severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  },
]

const makeSegment = (overrides = {}) => ({
  sid: 'seg-1',
  fid: 'file-1',
  id_file: 'file-1',
  revision_number: 1,
  status: 'APPROVED',
  ...overrides,
})

const renderPanel = (props = {}, segment = makeSegment()) =>
  render(
    <SegmentContext.Provider value={{segment}}>
      <ReviewExtendedIssuePanel
        segmentVersion={1}
        submitIssueCallback={jest.fn()}
        setCreationIssueLoader={jest.fn()}
        setIssueEditing={jest.fn()}
        {...props}
      />
    </SegmentContext.Provider>,
  )

describe('ReviewExtendedIssuePanel', () => {
  beforeEach(() => {
    global.config.lqa_flat_categories = flatCategories
    global.config.lqa_nested_categories = {categories: nestedCategoriesFlatOnly}
    global.config.revisionNumber = REVISE_STEP_NUMBER.REVISE1
    sendSegmentVersionIssue.mockResolvedValue({issue: {id: 123}})
    editSegmentIssue.mockResolvedValue({issue: {id: 124}})
    setTranslation.mockResolvedValue({translation: {version_number: 7}})
  })

  afterEach(() => {
    document.removeEventListener = document.removeEventListener
  })

  describe('rendering', () => {
    test('renders "New issue" header and category selectors when not editing', () => {
      renderPanel()
      expect(screen.getByText('New issue')).toBeInTheDocument()
      expect(screen.getByText('Accuracy')).toBeInTheDocument()
      expect(screen.getByText('Fluency')).toBeInTheDocument()
    })

    test('does not render "New issue" header when issueEditing is provided', () => {
      renderPanel({issueEditing: {id: 1, id_category: '10', severity: 'MINOR'}})
      expect(screen.queryByText('New issue')).not.toBeInTheDocument()
    })

    test('renders subcategory grouping headers when categories have subcategories', () => {
      global.config.lqa_nested_categories = {
        categories: nestedCategoriesWithSub,
      }
      const {container} = renderPanel()
      const headers = Array.from(
        container.querySelectorAll('.re-item-head'),
      ).map((el) => el.textContent)
      expect(headers).toEqual(['Accuracy', 'Fluency'])
      // category 10's actual subcategory rendered underneath its own header
      expect(screen.getByText('Mistranslation')).toBeInTheDocument()
    })

    test('renders the re-category-list id scoped to the current segment sid', () => {
      const {container} = renderPanel({}, makeSegment({sid: 'seg-42'}))
      expect(
        container.querySelector('#re-category-list-seg-42'),
      ).toBeInTheDocument()
    })
  })

  describe('sendIssue - already approved & matching revision (direct submit)', () => {
    test('calls sendSegmentVersionIssue and downstream success actions when not editing', async () => {
      const submitIssueCallback = jest.fn()
      const setCreationIssueLoader = jest.fn()
      renderPanel({submitIssueCallback, setCreationIssueLoader}, makeSegment())

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(setCreationIssueLoader).toHaveBeenCalledWith(true)
      expect(SegmentActions.setStatus).toHaveBeenCalledWith(
        'seg-1',
        'file-1',
        'APPROVED',
      )
      expect(sendSegmentVersionIssue).toHaveBeenCalledWith(
        expect.objectContaining({
          idSegment: 'seg-1',
          issueDetails: expect.objectContaining({
            id_category: '10',
            severity: 'MINOR',
          }),
        }),
      )
      expect(SegmentActions.getSegmentVersionsIssues).toHaveBeenCalledWith(
        'seg-1',
      )
      expect(CatToolActions.reloadQualityReport).toHaveBeenCalled()
      expect(submitIssueCallback).toHaveBeenCalled()
      expect(setCreationIssueLoader).toHaveBeenCalledWith(false)
    })

    test('calls editSegmentIssue instead when issueEditing is set, and clears editing on success', async () => {
      const setIssueEditing = jest.fn()
      renderPanel(
        {
          issueEditing: {id: 5, id_category: '10', severity: 'MINOR'},
          setIssueEditing,
        },
        makeSegment(),
      )

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(editSegmentIssue).toHaveBeenCalledWith(
        expect.objectContaining({idSegment: 'seg-1', issueId: 5}),
      )
      expect(sendSegmentVersionIssue).not.toHaveBeenCalled()
      expect(setIssueEditing).toHaveBeenCalledWith(undefined)
    })

    test('calls SegmentActions.issueAdded after submit succeeds', async () => {
      jest.useFakeTimers()
      renderPanel({}, makeSegment())

      fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])

      await act(async () => {
        await Promise.resolve()
        await Promise.resolve()
      })
      act(() => {
        jest.runOnlyPendingTimers()
      })

      expect(SegmentActions.issueAdded).toHaveBeenCalledWith('seg-1', 123)
      jest.useRealTimers()
    })

    test('does not send the API call twice when clicked again before the first resolves', async () => {
      let resolvePromise
      sendSegmentVersionIssue.mockReturnValue(
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
      )
      renderPanel({}, makeSegment())

      fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])

      expect(sendSegmentVersionIssue).toHaveBeenCalledTimes(1)

      await act(async () => {
        resolvePromise({issue: {id: 1}})
        await Promise.resolve()
      })
    })
  })

  describe('sendIssue - not approved / revision mismatch (setTranslation first)', () => {
    test('calls createSetTranslationRequest and setTranslation, then submits the issue', async () => {
      renderPanel({}, makeSegment({status: 'DRAFT'}))

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(SegmentUtils.createSetTranslationRequest).toHaveBeenCalled()
      expect(setTranslation).toHaveBeenCalledWith({requestBuilt: true})
      expect(SegmentActions.addClassToSegment).toHaveBeenCalledWith(
        'seg-1',
        'modified',
      )
      expect(sendSegmentVersionIssue).toHaveBeenCalled()
    })

    test('sets segment status to APPROVED when config.revisionNumber is REVISE1', async () => {
      const segment = makeSegment({status: 'DRAFT', revision_number: 1})
      global.config.revisionNumber = REVISE_STEP_NUMBER.REVISE1
      renderPanel({}, segment)

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(segment.status).toEqual(SEGMENTS_STATUS.APPROVED)
    })

    test('sets segment status to APPROVED2 when config.revisionNumber is REVISE2', async () => {
      const segment = makeSegment({status: 'DRAFT', revision_number: 2})
      global.config.revisionNumber = REVISE_STEP_NUMBER.REVISE2
      renderPanel({}, segment)

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(segment.status).toEqual(SEGMENTS_STATUS.APPROVED2)
    })

    test('triggers the setTranslation path when the revision number does not match config', async () => {
      renderPanel({}, makeSegment({status: 'APPROVED', revision_number: 99}))

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(setTranslation).toHaveBeenCalled()
    })
  })

  describe('failure handling', () => {
    test('calls CatToolActions.processErrors when the failure has error code -2000', async () => {
      const handleFail = jest.fn()
      const setCreationIssueLoader = jest.fn()
      setTranslation.mockRejectedValueOnce({errors: [{code: -2000}]})
      renderPanel(
        {handleFail, setCreationIssueLoader},
        makeSegment({status: 'DRAFT'}),
      )

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(CatToolActions.processErrors).toHaveBeenCalledWith(
        [{code: -2000}],
        'createIssue',
      )
      expect(setCreationIssueLoader).toHaveBeenCalledWith(false)
      expect(handleFail).toHaveBeenCalled()
    })

    test('calls CommonUtils.genericErrorAlertMessage for other error codes', async () => {
      setTranslation.mockRejectedValueOnce({errors: [{code: 500}]})
      renderPanel({}, makeSegment({status: 'DRAFT'}))

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(CommonUtils.genericErrorAlertMessage).toHaveBeenCalled()
    })

    test('calls CommonUtils.genericErrorAlertMessage when the deferred submit call fails', async () => {
      sendSegmentVersionIssue.mockRejectedValueOnce({errors: [{code: 1}]})
      renderPanel({}, makeSegment())

      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', {name: 'MIN'})[0])
      })

      expect(CommonUtils.genericErrorAlertMessage).toHaveBeenCalled()
    })
  })

  describe('keyboard shortcuts', () => {
    test('ctrl+alt keydown enables arrow navigation, highlighting the first category', () => {
      const {container} = renderPanel({}, makeSegment())
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      const items = container.querySelectorAll('.re-category-item')
      expect(items[0]).toHaveClass('active')
    })

    test('ArrowDown moves the active category to the next one', () => {
      const {container} = renderPanel({}, makeSegment())
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ArrowDown',
        })
      })
      const items = container.querySelectorAll('.re-category-item')
      expect(items[1]).toHaveClass('active')
      expect(items[0]).not.toHaveClass('active')
    })

    test('ArrowUp wraps around to the last category', () => {
      const {container} = renderPanel({}, makeSegment())
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ArrowUp',
        })
      })
      const items = container.querySelectorAll('.re-category-item')
      expect(items[1]).toHaveClass('active')
    })

    test('ArrowRight/ArrowLeft move the active severity index', () => {
      const {container} = renderPanel({}, makeSegment())
      const getButtons = () =>
        container
          .querySelectorAll('.re-category-item')[0]
          .querySelectorAll('button')

      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ArrowRight',
        })
      })
      expect(getButtons()[1]).toHaveClass('active')

      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ArrowLeft',
        })
      })
      // severityIndex is back to 0: no button is highlighted, since the component
      // computes severityActiveIndex as `(cond ? state.severityIndex : null) || ...`,
      // and a severityIndex of 0 is falsy so it falls through to null.
      expect(getButtons()[0]).not.toHaveClass('active')
      expect(getButtons()[1]).not.toHaveClass('active')
    })

    test('Enter sends the currently selected category/severity and focuses the edit area', async () => {
      jest.useFakeTimers()
      renderPanel({}, makeSegment())
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'Enter',
        })
      })

      await act(async () => {
        await Promise.resolve()
        await Promise.resolve()
      })
      act(() => {
        jest.runOnlyPendingTimers()
      })

      expect(sendSegmentVersionIssue).toHaveBeenCalledWith(
        expect.objectContaining({
          issueDetails: expect.objectContaining({
            id_category: '10',
            severity: 'MINOR',
          }),
        }),
      )
      expect(SegmentActions.setFocusOnEditArea).toHaveBeenCalled()
      jest.useRealTimers()
    })

    test('releasing ctrl/alt resets arrow navigation state', () => {
      const {container} = renderPanel({}, makeSegment())
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      })
      act(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ArrowDown',
        })
      })
      act(() => {
        fireEvent.keyUp(document, {
          ctrlKey: false,
          altKey: false,
          code: 'ControlLeft',
        })
      })
      const items = container.querySelectorAll('.re-category-item')
      expect(items[0]).not.toHaveClass('active')
      expect(items[1]).not.toHaveClass('active')
    })

    test('removes document listeners on unmount without throwing', () => {
      const {unmount} = renderPanel({}, makeSegment())
      unmount()
      expect(() => {
        fireEvent.keyDown(document, {
          ctrlKey: true,
          altKey: true,
          code: 'ControlLeft',
        })
      }).not.toThrow()
    })
  })
})
