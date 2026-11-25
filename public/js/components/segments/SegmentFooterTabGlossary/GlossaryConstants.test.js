import React from 'react'
import {render} from '@testing-library/react'
import {
  TERM_FORM_FIELDS,
  DeleteIcon,
  ModifyIcon,
  GlossaryDefinitionIcon,
  MoreIcon,
  LockIcon,
} from './GlossaryConstants'

describe('GlossaryConstants', () => {
  test('TERM_FORM_FIELDS exposes the expected field keys', () => {
    expect(TERM_FORM_FIELDS).toEqual({
      DEFINITION: 'definition',
      ORIGINAL_TERM: 'originalTerm',
      ORIGINAL_DESCRIPTION: 'originalDescription',
      ORIGINAL_EXAMPLE: 'originalExample',
      TRANSLATED_TERM: 'translatedTerm',
      TRANSLATED_DESCRIPTION: 'translatedDescription',
      TRANSLATED_EXAMPLE: 'translatedExample',
    })
  })

  test.each([
    ['DeleteIcon', DeleteIcon],
    ['ModifyIcon', ModifyIcon],
    ['GlossaryDefinitionIcon', GlossaryDefinitionIcon],
    ['MoreIcon', MoreIcon],
    ['LockIcon', LockIcon],
  ])('%s renders an svg element', (name, Icon) => {
    const {container} = render(<Icon />)
    expect(container.querySelector('svg')).toBeInTheDocument()
  })
})
