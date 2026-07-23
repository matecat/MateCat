import React from 'react'
import {render, screen} from '@testing-library/react'
import ServiceBox from './ServiceBox'

describe('ServiceBox', () => {
  test('renders the base service description without revision', () => {
    const {container} = render(<ServiceBox revision={false} />)
    expect(
      screen.getByText('Project Management + Translation'),
    ).toBeInTheDocument()
    expect(screen.queryByText('+ Revision')).not.toBeInTheDocument()
    expect(container.querySelector('.fiducial-logo').textContent).toContain(
      'Translated',
    )
  })

  test('shows the revision line when revision is true', () => {
    render(<ServiceBox revision={true} />)
    expect(screen.getByText('+ Revision')).toBeInTheDocument()
  })

  test('shows the revision line when compact is true even without revision', () => {
    render(<ServiceBox revision={false} compact={true} />)
    expect(screen.getByText('+ Revision')).toBeInTheDocument()
    expect(screen.queryByText('Translated')).not.toBeInTheDocument()
  })
})
