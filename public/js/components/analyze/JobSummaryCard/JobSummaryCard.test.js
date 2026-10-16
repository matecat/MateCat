import React from 'react'
import {render, screen} from '@testing-library/react'
import {getSavedLabel, JobSummaryCard} from './JobSummaryCard'

const totals = {raw: 1200, industry: 900, equivalent: 600}
const doneKnown = {raw: true, weighted: true, liveWeighted: false}

const renderCard = (props = {}) =>
  render(
    <JobSummaryCard
      title="en-US › fr-FR"
      subtitle="Job ID 421"
      totals={totals}
      known={doneKnown}
      workflowType="standard"
      {...props}
    />,
  )

describe('JobSummaryCard', () => {
  test('shows the pair, the subtitle and the actions', () => {
    renderCard({
      badge: 'Chunk 2 of 6',
      actions: <button type="button">Translate</button>,
    })
    expect(
      screen.getByRole('heading', {name: 'en-US › fr-FR'}),
    ).toBeInTheDocument()
    expect(screen.getByText('Chunk 2 of 6')).toBeInTheDocument()
    expect(screen.getByText('Job ID 421')).toBeInTheDocument()
    expect(screen.getByRole('button', {name: 'Translate'})).toBeInTheDocument()
  })

  test('the three totals use commas and say what the weighting saves', () => {
    renderCard()
    expect(screen.getByText('Total word count')).toBeInTheDocument()
    expect(screen.getByText('1,200')).toBeInTheDocument()
    expect(screen.getByText('Words in the source')).toBeInTheDocument()
    expect(screen.getByText('900')).toBeInTheDocument()
    expect(screen.getByText('25% less than the total')).toBeInTheDocument()
    expect(screen.getByText('600')).toBeInTheDocument()
    expect(screen.getByText('50% less than the total')).toBeInTheDocument()
  })

  test('weighted totals are pending until known', () => {
    renderCard({known: {raw: true, weighted: false, liveWeighted: false}})
    expect(screen.getAllByTestId('pending')).toHaveLength(2)
    expect(screen.queryByText(/less than the total/)).not.toBeInTheDocument()
  })

  test('no industry total outside the standard workflow', () => {
    renderCard({workflowType: 'mtqe'})
    expect(screen.queryByText('Industry weighted')).not.toBeInTheDocument()
  })

  test('a character count says characters', () => {
    renderCard({countUnit: 'characters'})
    expect(screen.getByText('Total character count')).toBeInTheDocument()
    expect(screen.getByText('Characters in the source')).toBeInTheDocument()
  })

  test('getSavedLabel', () => {
    expect(getSavedLabel(72, 100)).toBe('28% less than the total')
    expect(getSavedLabel(0, 0)).toBe('')
    // 99.86% is not 100%
    expect(getSavedLabel(6, 4193)).toBe('99.9% less than the total')
  })
})
