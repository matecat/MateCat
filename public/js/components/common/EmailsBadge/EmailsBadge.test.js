import React from 'react'

import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import {EmailsBadge, SPECIALS_SEPARATORS} from './EmailsBadge'

test('Component works properly', async () => {
  const user = userEvent.setup()
  let emails = []
  const setEmails = (data) => (emails = data)

  render(<EmailsBadge name="invite" value={emails} onChange={setEmails} />)

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

describe('EmailsBadge additional behaviors', () => {
  it('renders the default placeholder when there are no emails', () => {
    render(<EmailsBadge name="invite" value={[]} onChange={() => {}} />)
    expect(
      screen.getByText('john@email.com, federico@email.com, sara@email.com'),
    ).toBeInTheDocument()
  })

  it('renders a custom string placeholder', () => {
    render(
      <EmailsBadge
        name="invite"
        value={[]}
        onChange={() => {}}
        placeholder="Type an email"
      />,
    )
    expect(screen.getByText('Type an email')).toBeInTheDocument()
  })

  it('does not render the placeholder once there are emails', () => {
    render(
      <EmailsBadge name="invite" value={['a@mail.com']} onChange={() => {}} />,
    )
    expect(
      screen.queryByText('john@email.com, federico@email.com, sara@email.com'),
    ).not.toBeInTheDocument()
  })

  it('disables the input when disabled is true', () => {
    render(
      <EmailsBadge name="invite" value={[]} onChange={() => {}} disabled />,
    )
    expect(screen.getByTestId('email-input')).toBeDisabled()
  })

  it('renders the error message when an error prop with a message is provided', () => {
    render(
      <EmailsBadge
        name="invite"
        value={[]}
        onChange={() => {}}
        error={{message: 'Invalid email address'}}
      />,
    )
    expect(screen.getByText('Invalid email address')).toBeInTheDocument()
  })

  it('does not render an error message when error is not provided', () => {
    render(<EmailsBadge name="invite" value={[]} onChange={() => {}} />)
    expect(screen.queryByText('Invalid email address')).not.toBeInTheDocument()
  })

  it('removes a chip when its close button is clicked', () => {
    let emails = ['a@mail.com', 'b@mail.com']
    const onChange = (data) => (emails = data)
    const {rerender} = render(
      <EmailsBadge name="invite" value={emails} onChange={onChange} />,
    )

    const chipText = screen.getByText('a@mail.com')
    const closeButton =
      chipText.parentElement.querySelector('svg').parentElement
    fireEvent.click(closeButton)

    expect(emails).toEqual(['b@mail.com'])

    rerender(<EmailsBadge name="invite" value={emails} onChange={onChange} />)
    expect(screen.queryByText('a@mail.com')).not.toBeInTheDocument()
    expect(screen.getByText('b@mail.com')).toBeInTheDocument()
  })

  it('does not commit a new email while typing when validateUserTyping returns false', async () => {
    const user = userEvent.setup()
    let emails = []
    const setEmails = (data) => (emails = data)
    const validateUserTyping = jest.fn(() => false)

    render(
      <EmailsBadge
        name="invite"
        value={emails}
        onChange={setEmails}
        validateUserTyping={validateUserTyping}
      />,
    )

    const inputElement = screen.getByTestId('email-input')
    await user.type(inputElement, 'a')

    expect(validateUserTyping).toHaveBeenCalled()
    expect(emails).toEqual([])
  })

  it('commits emails as usual when validateUserTyping returns true', async () => {
    const user = userEvent.setup()
    let emails = []
    const setEmails = (data) => (emails = data)
    const validateUserTyping = jest.fn(() => true)

    render(
      <EmailsBadge
        name="invite"
        value={emails}
        onChange={setEmails}
        validateUserTyping={validateUserTyping}
      />,
    )

    const inputElement = screen.getByTestId('email-input')
    await user.type(inputElement, 'a@mail.com,')

    expect(emails).toEqual(['a@mail.com'])
  })

  it('commits the pending email on Enter when EnterKey is a configured separator', async () => {
    const user = userEvent.setup()
    let emails = []
    const setEmails = (data) => (emails = data)

    render(
      <EmailsBadge
        name="invite"
        value={emails}
        onChange={setEmails}
        separators={[',', SPECIALS_SEPARATORS.EnterKey]}
      />,
    )

    const inputElement = screen.getByTestId('email-input')
    await user.type(inputElement, 'a@mail.com')
    await user.keyboard('{Enter}')

    expect(emails).toEqual(['a@mail.com'])
  })

  it('updates emails when the value prop changes externally', () => {
    const {rerender} = render(
      <EmailsBadge name="invite" value={['a@mail.com']} onChange={() => {}} />,
    )
    expect(screen.getByText('a@mail.com')).toBeInTheDocument()

    rerender(
      <EmailsBadge name="invite" value={['b@mail.com']} onChange={() => {}} />,
    )
    expect(screen.queryByText('a@mail.com')).not.toBeInTheDocument()
    expect(screen.getByText('b@mail.com')).toBeInTheDocument()
  })

  it('supports a RegExp validateChip prop for marking invalid chips', () => {
    render(
      <EmailsBadge
        name="invite"
        value={['not-an-email']}
        onChange={() => {}}
        validateChip={/^\S+@\S+$/}
      />,
    )
    expect(screen.getByText('not-an-email')).toBeInTheDocument()
  })
})
