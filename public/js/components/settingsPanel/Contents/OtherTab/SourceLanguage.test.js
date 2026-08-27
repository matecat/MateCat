import React from 'react'
import {render, screen} from '@testing-library/react'
import {SourceLanguage} from './SourceLanguage'

jest.mock('../../../createProject/SourceLanguageSelect', () => ({
  SourceLanguageSelect: (props) => (
    <div
      data-testid="source-language-select"
      data-is-rendered-inside-tab={String(props.isRenderedInsideTab)}
      data-dropdown-class-name={props.dropdownClassName}
    />
  ),
}))

describe('SourceLanguage', () => {
  test('renders heading and description', () => {
    render(<SourceLanguage />)
    expect(screen.getByText('Source language')).toBeInTheDocument()
    expect(
      screen.getByText('Select the source language for your project.'),
    ).toBeInTheDocument()
  })

  test('renders SourceLanguageSelect with expected props', () => {
    render(<SourceLanguage />)
    const select = screen.getByTestId('source-language-select')
    expect(select).toBeInTheDocument()
    expect(select).toHaveAttribute('data-is-rendered-inside-tab', 'true')
    expect(select).toHaveAttribute(
      'data-dropdown-class-name',
      'select-dropdown__wrapper-portal',
    )
  })
})
