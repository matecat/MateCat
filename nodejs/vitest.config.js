import {defineConfig} from 'vitest/config';

export default defineConfig({
  test: {
    include: ['tests/**/*.test.js'],
    environment: 'node',
    coverage: {
      provider: 'v8',
      reportsDirectory: './.coverage/',
      reporter: ['clover', 'json', 'lcov', ['text', {skipFull: true}]],
      include: ['server.js', 'src/**/*.js'],
    },
  },
});
