import React from 'react'
import {render, screen, within} from '@testing-library/react'
import {OtherTab} from './OtherTab'

jest.mock('./Team', () => ({Team: () => <div data-testid="team" />}))
jest.mock('./SourceLanguage', () => ({
  SourceLanguage: () => <div data-testid="source-language" />,
}))
jest.mock('./TargetLanguages', () => ({
  TargetLanguages: () => <div data-testid="target-languages" />,
}))
jest.mock('./Subject', () => ({Subject: () => <div data-testid="subject" />}))
jest.mock('./Tagging', () => ({Tagging: () => <div data-testid="tagging" />}))
jest.mock('./MandatoryIssues', () => ({
  MandatoryIssues: () => <div data-testid="mandatory-issues" />,
}))
jest.mock('./IcuSyntax', () => ({
  IcuSyntax: () => <div data-testid="icu-syntax" />,
}))
jest.mock('./CharacterCounterRules', () => ({
  CharacterCounterRules: () => <div data-testid="character-counter-rules" />,
}))

describe('OtherTab', () => {
  test('renders outer wrapper with correct classes', () => {
    const {container} = render(<OtherTab />)
    expect(
      container.querySelector(
        '.other-options-box.settings-panel-contentwrapper-tab-background',
      ),
    ).toBeInTheDocument()
  })

  test('renders two subcategory sections', () => {
    const {container} = render(<OtherTab />)
    const sections = container.querySelectorAll(
      '.settings-panel-contentwrapper-tab-subcategories',
    )
    expect(sections).toHaveLength(2)
  })

  describe('General settings section', () => {
    let generalSection

    beforeEach(() => {
      const {container} = render(<OtherTab />)
      const sections = container.querySelectorAll(
        '.settings-panel-contentwrapper-tab-subcategories',
      )
      generalSection = sections[0]
    })

    test('has "General settings" heading', () => {
      expect(within(generalSection).getByText('General settings')).toBeInTheDocument()
    })

    test('renders Team', () => {
      expect(within(generalSection).getByTestId('team')).toBeInTheDocument()
    })

    test('renders SourceLanguage', () => {
      expect(
        within(generalSection).getByTestId('source-language'),
      ).toBeInTheDocument()
    })

    test('renders TargetLanguages', () => {
      expect(
        within(generalSection).getByTestId('target-languages'),
      ).toBeInTheDocument()
    })

    test('renders Subject', () => {
      expect(within(generalSection).getByTestId('subject')).toBeInTheDocument()
    })

    test('renders Tagging', () => {
      expect(within(generalSection).getByTestId('tagging')).toBeInTheDocument()
    })

    test('renders MandatoryIssues', () => {
      expect(
        within(generalSection).getByTestId('mandatory-issues'),
      ).toBeInTheDocument()
    })

    test('renders IcuSyntax', () => {
      expect(
        within(generalSection).getByTestId('icu-syntax'),
      ).toBeInTheDocument()
    })

    test('does not render CharacterCounterRules', () => {
      expect(
        within(generalSection).queryByTestId('character-counter-rules'),
      ).not.toBeInTheDocument()
    })
  })

  describe('Character counter settings section', () => {
    let counterSection

    beforeEach(() => {
      const {container} = render(<OtherTab />)
      const sections = container.querySelectorAll(
        '.settings-panel-contentwrapper-tab-subcategories',
      )
      counterSection = sections[1]
    })

    test('has "Character counter settings" heading', () => {
      expect(
        within(counterSection).getByText('Character counter settings'),
      ).toBeInTheDocument()
    })

    test('renders CharacterCounterRules', () => {
      expect(
        within(counterSection).getByTestId('character-counter-rules'),
      ).toBeInTheDocument()
    })

    test('does not render general settings children', () => {
      expect(within(counterSection).queryByTestId('team')).not.toBeInTheDocument()
      expect(
        within(counterSection).queryByTestId('source-language'),
      ).not.toBeInTheDocument()
    })
  })
})
