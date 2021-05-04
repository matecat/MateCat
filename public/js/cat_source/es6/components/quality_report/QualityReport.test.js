import {screen} from '@testing-library/react'

test('renders properly', async () => {
  global.config = {}

  require('../../../../common')

  {
    const header = document.createElement('header')
    const content = document.createElement('div')
    content.id = 'qr-root'

    document.body.appendChild(header)
    document.body.appendChild(content)
  }

  await import('./QualityReport')

  expect(screen.getByText('Loading')).toBeVisible()

  screen.debug()
})
