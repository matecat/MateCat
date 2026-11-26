import {defineConfig, devices} from '@playwright/test'

const PORT = 5299

// Acceptance suite of the editor core, against the harness page. CI runs Chromium; Firefox and WebKit run locally
// (`yarn test:editor-e2e --project=firefox`).
export default defineConfig({
  testDir: '.',
  testMatch: '*.e2e.js',
  timeout: 20000,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['github']] : 'list',
  outputDir: '../../../../../test-results/editor-e2e',
  use: {baseURL: `http://localhost:${PORT}`, trace: 'retain-on-failure'},
  webServer: {
    command: `npx vite --config vite.config.js --port ${PORT} --strictPort`,
    url: `http://localhost:${PORT}`,
    reuseExistingServer: !process.env.CI,
  },
  projects: [
    {name: 'chromium', use: {...devices['Desktop Chrome']}},
    {name: 'firefox', use: {...devices['Desktop Firefox']}},
    {name: 'webkit', use: {...devices['Desktop Safari']}},
  ],
})
