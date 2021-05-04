test('renders properly', async () => {
  global.config = {}

  {
    const header = document.createElement('header')
    const content = document.createElement('div')
    content.id = 'qr-root'

    document.body.appendChild(header)
    document.body.appendChild(content)
  }

  await import('./QualityReport')
})
