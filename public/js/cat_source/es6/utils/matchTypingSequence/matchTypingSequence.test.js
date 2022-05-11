import matchTypingSequence from './matchTypingSequence'

test('Match typing sequence', async () => {
  const {get: checkSequence, reset} = matchTypingSequence(
    ['u', '2', '0', '6', '0'],
    1000,
  )

  checkSequence('u')
  checkSequence('2')
  checkSequence('0')
  checkSequence('6')
  let result = checkSequence('0')

  expect(result).toBeTruthy()

  checkSequence('u')
  setTimeout(() => checkSequence('2'), 10)
  setTimeout(() => checkSequence('0'), 20)
  setTimeout(() => checkSequence('6'), 30)
  result = await new Promise((resolve) => {
    setTimeout(() => resolve(checkSequence('0')), 500)
  })

  expect(result).toBeTruthy()

  checkSequence('u')
  setTimeout(() => checkSequence('2'), 10)
  setTimeout(() => checkSequence('0'), 20)
  setTimeout(() => checkSequence('6'), 30)
  result = await new Promise((resolve) => {
    setTimeout(() => resolve(checkSequence('a')), 200)
  })
  expect(result).toBeFalsy()

  checkSequence('u')
  setTimeout(() => checkSequence('2'), 10)
  result = await new Promise((resolve) => {
    setTimeout(() => resolve(checkSequence('0')), 200)
  })
  expect(result).toBeFalsy()

  reset()

  checkSequence('u')
  checkSequence('2')
  checkSequence('0')
  checkSequence('6')
  result = await new Promise((resolve) => {
    setTimeout(() => resolve(checkSequence('0')), 1400)
  })
  expect(result).toBeFalsy()
})

test('Match typing sequence with multiple options', () => {
  const {get: checkSequence} = matchTypingSequence(
    [
      [50, 98],
      [48, 96],
      [54, 102],
      [48, 96],
    ],
    1000,
  )

  checkSequence(98)
  checkSequence(48)
  checkSequence(54)
  let result = checkSequence(96)

  expect(result).toBeTruthy()
})
