import React from 'react'
import {render, act} from '@testing-library/react'
import RowSegment, {ProjectBar} from './RowSegment'
import SegmentUtils from '../../../../utils/segmentUtils'

global.ResizeObserver = class ResizeObserver {
  constructor(cb) {
    this.cb = cb
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

window.config = {
  source_code: 'en-EN',
  target_code: 'it-IT',
  tag_projection_languages: [],
}

const mockSegment = {
  internal_id: 'seg-1',
  sid: 101,
  original: 'Source text',
  translation: '',
  status: 'NEW',
  id_file: 1,
}

const mockFiles = [
  {
    id: 1,
    file_name: 'document.docx',
    weighted_words: 500,
    first_segment: 101,
    metadata: {},
  },
]

const defaultProjectBarProps = {
  segment: mockSegment,
  files: mockFiles,
  sideOpen: false,
  isSticky: false,
  listRef: null,
  previousOpenedFileIdRef: {current: undefined},
}

const createMockListRef = (scrollTop = 0) => {
  const el = document.createElement('div')
  Object.defineProperty(el, 'scrollTop', {writable: true, value: scrollTop})
  jest.spyOn(el, 'addEventListener')
  jest.spyOn(el, 'removeEventListener')
  return el
}

describe('ProjectBar', () => {
  afterEach(() => jest.restoreAllMocks())

  describe('rendering', () => {
    it('renders without crashing with default props', () => {
      expect(() =>
        render(<ProjectBar {...defaultProjectBarProps} />),
      ).not.toThrow()
    })

    it('renders file name when file is found', () => {
      const {getByText} = render(<ProjectBar {...defaultProjectBarProps} />)
      expect(getByText('document.docx')).toBeInTheDocument()
    })

    it('renders word count when weighted_words > 0', () => {
      const {getByText} = render(<ProjectBar {...defaultProjectBarProps} />)
      expect(getByText('500')).toBeInTheDocument()
    })

    it('does not render word count when weighted_words is 0', () => {
      const props = {
        ...defaultProjectBarProps,
        files: [{...mockFiles[0], weighted_words: 0}],
      }
      const {queryByText} = render(<ProjectBar {...props} />)
      expect(queryByText(/Payable Words/)).not.toBeInTheDocument()
    })
  })

  describe('scroll listener (isSticky=true)', () => {
    it('registers scroll listener on listRef when isSticky', () => {
      const listRef = createMockListRef()
      render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      expect(listRef.addEventListener).toHaveBeenCalledWith(
        'scroll',
        expect.any(Function),
      )
    })

    it('removes scroll listener on unmount', () => {
      const listRef = createMockListRef()
      const {unmount} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      unmount()
      expect(listRef.removeEventListener).toHaveBeenCalledWith(
        'scroll',
        expect.any(Function),
      )
    })

    it('does not register scroll listener when isSticky=false', () => {
      const listRef = createMockListRef()
      render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={false}
          listRef={listRef}
        />,
      )
      expect(listRef.addEventListener).not.toHaveBeenCalledWith(
        'scroll',
        expect.any(Function),
      )
    })

    it('does not crash when listRef is null and isSticky=true', () => {
      expect(() =>
        render(
          <ProjectBar
            {...defaultProjectBarProps}
            isSticky={true}
            listRef={null}
          />,
        ),
      ).not.toThrow()
    })
  })

  describe('file change animation (isSticky=true)', () => {
    it('does not trigger animation on first render', () => {
      const {container} = render(
        <ProjectBar {...defaultProjectBarProps} isSticky={true} />,
      )
      expect(container.firstChild).not.toHaveClass(
        'sticky-project-bar-change-file-animation',
      )
    })

    it('triggers animation when idFileSegment changes while sticky', () => {
      const segmentFile2 = {...mockSegment, id_file: 2}
      const files = [
        ...mockFiles,
        {...mockFiles[0], id: 2, file_name: 'other.docx'},
      ]

      const {rerender, container} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          files={files}
        />,
      )

      act(() => {
        rerender(
          <ProjectBar
            {...defaultProjectBarProps}
            isSticky={true}
            files={files}
            segment={segmentFile2}
          />,
        )
      })

      expect(container.firstChild).toHaveClass(
        'sticky-project-bar-change-file-animation',
      )
    })

    it('cleans up animation listener on unmount', () => {
      const {unmount} = render(
        <ProjectBar {...defaultProjectBarProps} isSticky={true} />,
      )
      expect(() => unmount()).not.toThrow()
    })
  })

  describe('blink animation (isSegmentOpenedNotRendered)', () => {
    const BLINK_ANIMATION_DURATION = 3600

    afterEach(() => {
      jest.useRealTimers()
    })

    it('does not blink by default (no isSegmentOpenedNotRendered prop)', () => {
      const {container} = render(
        <ProjectBar {...defaultProjectBarProps} isSticky={true} />,
      )
      expect(container.querySelector('.projectbar-filename')).not.toHaveClass(
        'project-bar-blink',
      )
    })

    it('blinks when isSegmentOpenedNotRendered describes "true"', () => {
      const {container} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          isSegmentOpenedNotRendered={Symbol(true)}
        />,
      )
      expect(container.querySelector('.projectbar-filename')).toHaveClass(
        'project-bar-blink',
      )
    })

    it('does not blink when isSegmentOpenedNotRendered describes "false"', () => {
      const {container} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          isSegmentOpenedNotRendered={Symbol(false)}
        />,
      )
      expect(container.querySelector('.projectbar-filename')).not.toHaveClass(
        'project-bar-blink',
      )
    })

    it('does not blink when not sticky, even if the signal says true', () => {
      const {container} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={false}
          isSegmentOpenedNotRendered={Symbol(true)}
        />,
      )
      expect(container.querySelector('.projectbar-filename')).not.toHaveClass(
        'project-bar-blink',
      )
    })

    it('clears the blink automatically after the animation duration, even without an animationend event', () => {
      jest.useFakeTimers()
      const {container} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          isSegmentOpenedNotRendered={Symbol(true)}
        />,
      )
      expect(container.querySelector('.projectbar-filename')).toHaveClass(
        'project-bar-blink',
      )

      act(() => {
        jest.advanceTimersByTime(BLINK_ANIMATION_DURATION)
      })

      expect(container.querySelector('.projectbar-filename')).not.toHaveClass(
        'project-bar-blink',
      )
    })

    it('re-triggers the blink when a new Symbol signal describes "true" again', () => {
      jest.useFakeTimers()
      const {container, rerender} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          isSegmentOpenedNotRendered={Symbol(true)}
        />,
      )

      act(() => {
        jest.advanceTimersByTime(BLINK_ANIMATION_DURATION)
      })
      expect(container.querySelector('.projectbar-filename')).not.toHaveClass(
        'project-bar-blink',
      )

      act(() => {
        rerender(
          <ProjectBar
            {...defaultProjectBarProps}
            isSticky={true}
            isSegmentOpenedNotRendered={Symbol(true)}
          />,
        )
      })

      expect(container.querySelector('.projectbar-filename')).toHaveClass(
        'project-bar-blink',
      )
    })
  })

  describe('scroll direction tracking', () => {
    it('detects forward scroll', () => {
      const listRef = createMockListRef(0)
      const {rerender} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      act(() => {
        listRef.scrollTop = 100
      })
      rerender(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      expect(listRef.scrollTop).toBe(100)
    })

    it('detects reverse scroll', () => {
      const listRef = createMockListRef(100)
      const {rerender} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      act(() => {
        listRef.scrollTop = 50
      })
      rerender(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          listRef={listRef}
        />,
      )
      expect(listRef.scrollTop).toBe(50)
    })
  })

  describe('previousOpenedFileIdRef syncing (isSticky=true)', () => {
    it('sets the ref to the rendered segment file id when sticky', () => {
      const previousOpenedFileIdRef = {current: undefined}
      render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          previousOpenedFileIdRef={previousOpenedFileIdRef}
        />,
      )
      expect(previousOpenedFileIdRef.current).toBe(mockSegment.id_file)
    })

    it('updates the ref again when the sticky bar re-renders for a different file', () => {
      const previousOpenedFileIdRef = {current: undefined}
      const segmentFile2 = {...mockSegment, id_file: 2}
      const {rerender} = render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          previousOpenedFileIdRef={previousOpenedFileIdRef}
        />,
      )
      expect(previousOpenedFileIdRef.current).toBe(1)

      rerender(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          segment={segmentFile2}
          previousOpenedFileIdRef={previousOpenedFileIdRef}
        />,
      )
      expect(previousOpenedFileIdRef.current).toBe(2)
    })

    it('does not touch the ref when not sticky', () => {
      const previousOpenedFileIdRef = {current: 'unchanged'}
      render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={false}
          previousOpenedFileIdRef={previousOpenedFileIdRef}
        />,
      )
      expect(previousOpenedFileIdRef.current).toBe('unchanged')
    })

    it('uses the resolved file id rather than segment.id_file when they diverge (e.g. jsont2 split files)', () => {
      jest.spyOn(SegmentUtils, 'getSegmentFileId').mockReturnValue(999)
      const previousOpenedFileIdRef = {current: undefined}
      render(
        <ProjectBar
          {...defaultProjectBarProps}
          isSticky={true}
          previousOpenedFileIdRef={previousOpenedFileIdRef}
        />,
      )
      expect(previousOpenedFileIdRef.current).toBe(999)
    })
  })
})

describe('RowSegment', () => {
  const defaultRowProps = {
    id: '1',
    height: 100,
    onChangeRowHeight: jest.fn(),
    hasRendered: false,
    isLastRow: false,
    currentFileId: '1',
    segment: mockSegment,
    files: mockFiles,
    sideOpen: false,
    isSticky: false,
    listRef: null,
    previousSegment: null,
    nextSegment: null,
  }

  it('renders without crashing', () => {
    expect(() => render(<RowSegment {...defaultRowProps} />)).not.toThrow()
  })
})
