import React from 'react'
import {render, screen} from '@testing-library/react'
import {htmlToReact} from './htmlToReact'

const renderHtml = (html) => render(<p>{htmlToReact(html)}</p>)

test('renders allowed markup as elements, not as text', () => {
  renderHtml('Matecat has a <b>fresh</b> look.<br>Tell us at <em>once</em>')

  expect(screen.getByText('fresh').tagName).toBe('B')
  expect(screen.getByText('once').tagName).toBe('EM')
  expect(screen.queryByText(/<b>/)).not.toBeInTheDocument()
})

test('keeps http and mailto links, opening web links in a new tab', () => {
  renderHtml(
    '<a href="https://guides.matecat.com">Guide</a> or <a href="mailto:info@matecat.com">mail us</a>',
  )

  const guide = screen.getByRole('link', {name: 'Guide'})
  expect(guide).toHaveAttribute('href', 'https://guides.matecat.com')
  expect(guide).toHaveAttribute('target', '_blank')
  expect(guide).toHaveAttribute('rel', 'noopener noreferrer')
  expect(screen.getByRole('link', {name: 'mail us'})).toHaveAttribute(
    'href',
    'mailto:info@matecat.com',
  )
})

test('drops javascript and data URLs, keeping the link text', () => {
  renderHtml(
    '<a href=" javascript:alert(1)">One</a><a href="data:text/html,x">Two</a>',
  )

  expect(screen.getByText('One')).not.toHaveAttribute('href')
  expect(screen.getByText('Two')).not.toHaveAttribute('href')
})

test('drops event handlers and other attributes', () => {
  renderHtml('<b onclick="alert(1)" style="color:red" class="x">Bold</b>')

  const bold = screen.getByText('Bold')
  expect(bold).not.toHaveAttribute('onclick')
  expect(bold).not.toHaveAttribute('style')
  expect(bold).not.toHaveAttribute('class')
})

test('drops scripts with their content and unwraps unknown tags', () => {
  renderHtml(
    'Hello<script>window.hacked = true</script> <img src=x onerror="window.hacked = true"><div>world</div>',
  )

  expect(window.hacked).toBeUndefined()
  expect(screen.getByText('Hello world')).toBeInTheDocument()
  expect(screen.queryByRole('img')).not.toBeInTheDocument()
})

test('decodes entities and returns plain strings as they are', () => {
  renderHtml('Fish &amp; chips')
  expect(screen.getByText('Fish & chips')).toBeInTheDocument()

  expect(htmlToReact('Plain text')).toBe('Plain text')
  expect(htmlToReact(undefined)).toBeUndefined()
})
