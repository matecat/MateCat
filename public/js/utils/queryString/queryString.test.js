import {
  flattenObjectProps,
  getQueryStringFromProps,
  getQueryStringFromNestedProps,
} from './queryString'

test('Flatten object properties', () => {
  const input = {
    action: 'outsourceTo',
    pid: 17,
    currency: 'EUR',
    ppassword: '3d17443dd94c',
    fixedDelivery: '',
    typeOfService: 'professional',
    timezone: '2',
    jobs: [
      {
        jid: 110,
        jpassword: '9599a9febd1e',
      },
    ],
  }

  const result = [
    {action: 'outsourceTo'},
    {pid: 17},
    {currency: 'EUR'},
    {ppassword: '3d17443dd94c'},
    {fixedDelivery: ''},
    {typeOfService: 'professional'},
    {timezone: '2'},
    {'jobs[0][jid]': 110},
    {'jobs[0][jpassword]': '9599a9febd1e'},
  ]

  expect(flattenObjectProps(input)).toEqual(result)
})

test('Querystring from flatten properties', () => {
  const input = {
    action: 'outsourceTo',
    pid: 17,
    currency: 'EUR',
    ppassword: '3d17443dd94c',
    fixedDelivery: '',
    typeOfService: 'professional',
  }

  const result =
    '?action=outsourceTo&pid=17&currency=EUR&ppassword=3d17443dd94c&fixedDelivery=&typeOfService=professional'

  expect(getQueryStringFromProps(input)).toBe(result)
})

test('Querystring from nested properties', () => {
  const input = {
    action: 'outsourceTo',
    pid: 17,
    currency: 'EUR',
    ppassword: '3d17443dd94c',
    fixedDelivery: '',
    typeOfService: 'professional',
    timezone: '2',
    jobs: [
      {
        jid: 110,
        jpassword: '9599a9febd1e',
      },
    ],
    prop1: {
      prop2: {
        prop3: {
          value: 'result',
        },
      },
    },
  }

  const result =
    '?action=outsourceTo&pid=17&currency=EUR&ppassword=3d17443dd94c&fixedDelivery=&typeOfService=professional&timezone=2&jobs%5B0%5D%5Bjid%5D=110&jobs%5B0%5D%5Bjpassword%5D=9599a9febd1e&prop1%5Bprop2%5D%5Bprop3%5D%5Bvalue%5D=result'

  expect(getQueryStringFromNestedProps(input)).toBe(result)
})
