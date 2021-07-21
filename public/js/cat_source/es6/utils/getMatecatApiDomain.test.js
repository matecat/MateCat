import {getMatecatApiDomain} from './getMatecatApiDomain'

test('return basepath if multi domain is disabled', () => {
  global.config = {
    basepath: '/',
  }

  expect(getMatecatApiDomain()).toBe('/')
})

test('return random domain if multi domain is enabled', () => {
  global.config = {
    enableMultiDomainApi: true,
    ajaxDomainsNumber: 20,
    basepath: '/',
  }

  expect(getMatecatApiDomain()).not.toBe('/')
})
