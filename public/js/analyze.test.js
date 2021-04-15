test('renders properly', async () => {
  globalThis.config = {
    isLoggedIn: false,
  }
  globalThis.UI = null
  globalThis.APP = {USER: {STORE: null}}

  const elHeader = document.createElement('header')
  const elAnalyzeContainer = document.createElement('div')
  elAnalyzeContainer.id = 'analyze-container'

  document.body.appendChild(elHeader)
  document.body.appendChild(elAnalyzeContainer)

  await import('./analyze')

  UI.init()
})
