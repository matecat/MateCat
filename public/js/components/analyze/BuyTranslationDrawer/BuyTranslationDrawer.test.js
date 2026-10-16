import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {buildVolumeAnalysis} from '../../../../mocks/volumeAnalysisMock'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {BuyTranslationDrawer, getOrderItem} from './BuyTranslationDrawer'

const mockPanelProps = []
jest.mock('../../outsource/OutsourceOrderPanel', () => {
  const MockPanel = (props) => {
    mockPanelProps.push(props)
    return <div data-testid="order-panel">{props.job.get('password')}</div>
  }
  return {__esModule: true, default: MockPanel, OutsourceOrderPanel: MockPanel}
})

const {jobs} = deriveAnalysisView({analysis: buildVolumeAnalysis()})
const [italian, french] = jobs
const project = {
  id: 20,
  password: 'projpass',
  jobs: [{id: 102, password: 'frpass', outsource: null}],
}

const renderDrawer = (props = {}) => {
  const handlers = {
    onClose: jest.fn(),
    onPickChunk: jest.fn(),
    onMerge: jest.fn(),
  }
  render(
    <BuyTranslationDrawer
      open={true}
      project={project}
      job={french}
      chunk={french.chunks[0]}
      {...handlers}
      {...props}
    />,
  )
  return handlers
}

describe('BuyTranslationDrawer', () => {
  beforeEach(() => {
    mockPanelProps.length = 0
  })

  test('orders a chunk with the shared panel, quotes cached', () => {
    renderDrawer()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByTestId('order-panel')).toHaveTextContent('frpass')
    const props = mockPanelProps.at(-1)
    expect(props.cache).toBe(true)
    expect(props.standardWC).toBe(french.chunks[0].totals.equivalent)
    expect(props.project.get('id')).toBe(20)
    expect(
      screen.getByText(getOrderItem(french, french.chunks[0])),
    ).toBeVisible()
  })

  test('a whole split job picks a chunk first, or merges', () => {
    const {onPickChunk, onMerge} = renderDrawer({job: italian, chunk: null})
    expect(screen.queryByTestId('order-panel')).not.toBeInTheDocument()
    expect(
      screen.getByText(
        'This job is split into 2 chunks, and each chunk is bought on its own.',
      ),
    ).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', {name: /^Chunk 2 /}))
    expect(onPickChunk).toHaveBeenCalledWith(italian.chunks[1])

    fireEvent.click(screen.getByRole('button', {name: 'Merge'}))
    expect(onMerge).toHaveBeenCalledTimes(1)
  })

  test('without merge rights there is no merge button', () => {
    renderDrawer({job: italian, chunk: null, onMerge: undefined})
    expect(
      screen.queryByRole('button', {name: 'Merge'}),
    ).not.toBeInTheDocument()
  })

  test('getOrderItem describes what is bought', () => {
    expect(getOrderItem(french, french.chunks[0])).toBe(
      'en-US › fr-FR · 100 Matecat weighted words',
    )
    expect(getOrderItem(italian, italian.chunks[1])).toBe(
      `en-US › it-IT · chunk 2 of 2 · ${italian.chunks[1].totals.equivalent} Matecat weighted words`,
    )
    expect(getOrderItem(italian, null)).toBe(
      `en-US › it-IT · 2 chunks · ${italian.totals.equivalent} Matecat weighted words`,
    )
  })
})
