import {MODAL_KEY} from '../../constants/ModalKeys'
import {resolveModal} from './modalRegistry'
import ConfirmMessageModal from './ConfirmMessageModal'
import AlertModal from './AlertModal'

describe('modalRegistry', () => {
  afterEach(() => jest.restoreAllMocks())

  test('resolves a known modal key to its component', () => {
    expect(resolveModal(MODAL_KEY.CONFIRM_MESSAGE)).toBe(ConfirmMessageModal)
    expect(resolveModal(MODAL_KEY.ALERT)).toBe(AlertModal)
  })

  test('logs an error and returns undefined for an unknown string key', () => {
    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {})

    const result = resolveModal('SomeUnknownModalKey')

    expect(result).toBeUndefined()
    expect(errorSpy).toHaveBeenCalledWith(
      'Unknown modal key: "SomeUnknownModalKey"',
    )
  })

  test('returns the value unchanged when given a component reference', () => {
    const CustomComponent = () => null
    expect(resolveModal(CustomComponent)).toBe(CustomComponent)
  })
})
