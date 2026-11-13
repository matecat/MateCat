import React from 'react'
import {render, screen} from '@testing-library/react'

import SegmentWrapper from './SegmentWrapper'
import {SegmentContext} from './SegmentContext'
import SearchUtils from '../header/cattol/search/searchUtils'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

// Closed segments only: the editors are never rendered here.
jest.mock('./SegmentSource', () => () => null)
jest.mock('./SegmentTarget', () => () => null)
jest.mock('../../actions/CatToolActions')
jest.mock('../../actions/ModalsActions')
jest.mock('../../actions/segmentDispatchActions')
jest.mock('../../api/searchTermIntoSegments')
jest.mock('../../api/replaceAllIntoSegments')

const SID = '7'

const search = ({params, featured = 0, hits = 1, mode = 'normal'}) => {
  SearchUtils.searchMode = mode
  SearchUtils.searchParams = {'match-case': false, ...params}
  SearchUtils.occurrencesList = Array(hits).fill(SID)
  SearchUtils.featuredSearchResult = featured
  SearchUtils.searchResultsDictionary = {
    [SID]: {
      occurrences: Array.from({length: hits}, (_, i) => ({
        searchProgressiveIndex: i,
      })),
    },
  }
}

const renderClosed = (segment, {isTarget = false} = {}) =>
  render(
    <SegmentContext.Provider
      value={{
        segment: {
          sid: SID,
          segment,
          translation: segment,
          opened: false,
          inSearch: true,
        },
      }}
    >
      <SegmentWrapper isTarget={isTarget} />
    </SegmentContext.Provider>,
  )

const marks = () => screen.queryAllByText(/.*/, {selector: 'mark'})

beforeEach(() => {
  setTagSignatureMiddleware('space', () => false)
  window.config = {
    ...window.config,
    source_code: 'en-US',
    target_code: 'it-IT',
    tag_projection_languages: {},
    isSourceRTL: false,
    isTargetRTL: false,
  }
})

afterAll(() => setTagSignatureMiddleware('space', () => undefined))

describe('search highlights on a closed segment', () => {
  test('marks every hit and flags the featured one as current', () => {
    search({params: {source: 'world'}, featured: 1, hits: 2})
    renderClosed('Hello <g id="1">world</g> and world')

    const found = marks()
    expect(found).toHaveLength(2)
    expect(found[0]).toHaveTextContent('world')
    expect(found[0]).toHaveClass('searchMarker')
    expect(found[0]).not.toHaveClass('currSearchItem')
    expect(found[1]).toHaveClass('searchMarker', 'currSearchItem')
  })

  test('never marks inside a tag chip', () => {
    search({params: {source: 'id'}})
    renderClosed('Hello <x id="1"/> <ph id="2" equiv-text="base64:aWQ="/>')

    expect(marks()).toHaveLength(0)
    expect(screen.getByText('id')).toHaveClass('tag')
  })

  test('matches the text as shown, entities decoded', () => {
    search({params: {source: 'a & b'}})
    renderClosed('x a &amp; b')

    expect(marks()).toHaveLength(1)
    expect(marks()[0]).toHaveTextContent('a & b')
  })

  test('marks the target side with the target term', () => {
    search({params: {target: 'mondo'}})
    renderClosed('ciao mondo', {isTarget: true})

    expect(marks()).toHaveLength(1)
    expect(marks()[0]).toHaveTextContent('mondo')
  })

  test('wraps a whitespace chip the hit runs across', () => {
    setTagSignatureMiddleware('space', () => undefined)
    search({params: {source: 'foo bar'}})
    renderClosed('foo bar')

    expect(marks()).toHaveLength(1)
    expect(marks()[0]).toHaveTextContent('foo·bar')
  })
})
