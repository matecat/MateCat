import React from 'react'

import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import {EmailsBadge} from './EmailsBadge'

test('Component works properly', async () => {
  const user = userEvent.setup()
  let emails = []
  const setEmails = (data) => (emails = data)

  render(<EmailsBadge name="invite" onChange={setEmails} />)

  const inputElement = screen.getByTestId('email-input')
  const areaElement = screen.getByTestId('email-area')

  await user.type(inputElement, 'abcd test@mail.com')
  expect(inputElement).toHaveValue('test@mail.com')

  await user.click(document.body)
  expect(emails).toEqual(['abcd', 'test@mail.com'])

  await user.type(inputElement, '{backspace}')
  expect(emails).toEqual(['abcd', 'test@mail.com'])
  expect(areaElement).toHaveFocus()

  await user.keyboard('{backspace}')
  expect(emails).toEqual(['abcd'])

  await user.keyboard('{backspace}')
  expect(emails).toEqual(['abcd'])

  await user.keyboard('{backspace}')
  expect(emails).toEqual([])

  await user.type(
    inputElement,
    'abcd test@mail.com; mail@yahoo.com test@mail.com,',
  )
  expect(emails).toEqual(['abcd', 'test@mail.com', 'mail@yahoo.com'])

  const emailsPaste = `lwuckert@gmail.com
    collins.cory@yahoo.com
    rrice@gmail.com
    lia84@gmail.com
    david65@gmail.com
    haley.alycia@hotmail.com
    schroeder.malinda@lakin.info
    jeanette90@jacobs.com
    kunze.isaac@buckridge.com
    enienow@gmail.com`

  inputElement.focus()
  await user.paste(emailsPaste)
  expect(emails).toEqual([
    'abcd',
    'test@mail.com',
    'mail@yahoo.com',
    'lwuckert@gmail.com',
    'collins.cory@yahoo.com',
    'rrice@gmail.com',
    'lia84@gmail.com',
    'david65@gmail.com',
    'haley.alycia@hotmail.com',
    'schroeder.malinda@lakin.info',
    'jeanette90@jacobs.com',
    'kunze.isaac@buckridge.com',
    'enienow@gmail.com',
  ])
})
