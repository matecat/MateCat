import React from 'react'
import {render, screen} from '@testing-library/react'
import NotificationItem from './NotificationItem'

const baseProps = {
  uid: 'test-notification',
  type: 'success',
  position: 'br',
  autoDismiss: false,
  onRemove: () => {},
}

test('renders a ReactNode text with user data as plain text', () => {
  const name = '<img src=x onerror="window.__xss=1"> R&D'
  render(
    <NotificationItem
      {...baseProps}
      title="Resource shared"
      text={
        <>
          The resource <b>{name}</b> has been shared.
        </>
      }
    />,
  )

  expect(screen.queryByRole('img')).toBeNull()
  expect(screen.getByText(name)).toBeInTheDocument()
  expect(document.querySelector('.notification-message b')).not.toBeNull()
  expect(window.__xss).toBeUndefined()
})

test('sanitizes markup rendered through allowHtml', () => {
  render(
    <NotificationItem
      {...baseProps}
      title="Notice"
      text='<b>hello</b><img src="x" onerror="window.__xss=1">'
      allowHtml
    />,
  )

  const message = document.querySelector('.notification-message')
  expect(message.querySelector('b')).not.toBeNull()
  const img = message.querySelector('img')
  if (img) {
    expect(img.getAttribute('onerror')).toBeNull()
  }
  expect(window.__xss).toBeUndefined()
})

test('renders a plain string text as text', () => {
  render(
    <NotificationItem
      {...baseProps}
      title="Notice"
      text="<script>alert(1)</script>"
    />,
  )

  expect(screen.getByText('<script>alert(1)</script>')).toBeInTheDocument()
})
