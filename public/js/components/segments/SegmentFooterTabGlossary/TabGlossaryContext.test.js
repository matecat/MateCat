import React, {useContext} from 'react'
import {render, screen} from '@testing-library/react'
import {TabGlossaryContext} from './TabGlossaryContext'

const Consumer = () => {
  const value = useContext(TabGlossaryContext)
  return <span>{JSON.stringify(value)}</span>
}

describe('TabGlossaryContext', () => {
  test('defaults to an empty object when no provider is present', () => {
    render(<Consumer />)
    expect(screen.getByText('{}')).toBeInTheDocument()
  })

  test('exposes the value supplied by a Provider', () => {
    render(
      <TabGlossaryContext.Provider value={{isActive: true}}>
        <Consumer />
      </TabGlossaryContext.Provider>,
    )
    expect(screen.getByText('{"isActive":true}')).toBeInTheDocument()
  })
})
