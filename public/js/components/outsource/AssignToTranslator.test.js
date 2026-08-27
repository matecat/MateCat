import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'

import AssignToTranslator from './AssignToTranslator'
import {addJobTranslator} from '../../api/addJobTranslator'
import ModalsActions from '../../actions/ModalsActions'
import CatToolActions from '../../actions/CatToolActions'
import ManageActions from '../../actions/ManageActions'

jest.mock('../../api/addJobTranslator', () => ({
  addJobTranslator: jest.fn(),
}))
jest.mock('../../actions/ModalsActions', () => ({
  onCloseModal: jest.fn(),
}))
jest.mock('../../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
}))
jest.mock('../../actions/ManageActions', () => ({
  changeJobPasswordFromOutsource: jest.fn(),
  assignTranslator: jest.fn(),
}))

jest.mock('react-datepicker', () => ({
  __esModule: true,
  default: ({onChange}) => (
    <button onClick={() => onChange(new Date('2026-08-01T00:00:00.000Z'))}>
      pick-date
    </button>
  ),
}))

jest.mock('./GMTSelect', () => ({
  GMTSelect: ({changeValue}) => (
    <button onClick={() => changeValue('4')}>pick-gmt</button>
  ),
}))

jest.mock('../common/Select', () => ({
  Select: ({options, onSelect}) => (
    <div>
      {options.map((option) => (
        <button key={option.id} onClick={() => onSelect(option)}>
          time-{option.id}
        </button>
      ))}
    </div>
  ),
}))

const project = fromJS({id: 42, name: 'Test project'})

const buildJob = (overrides = {}) =>
  fromJS({
    id: 7,
    password: 'job-pass',
    source: 'en-US',
    target: 'it-IT',
    sourceTxt: 'English',
    targetTxt: 'Italian',
    ...overrides,
  })

const fillValidEmail = (input, value = 'translator@example.com') => {
  fireEvent.change(input, {target: {value}})
  fireEvent.keyUp(input)
}

describe('AssignToTranslator', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    Cookies.remove('matecat_timezone')
  })

  test('renders empty translator email field and disabled send button when there is no translator', () => {
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    expect(input.value).toBe('')
    expect(screen.getByText('Send Job to Translator')).toBeDisabled()
  })

  test('pre-fills the translator email when a translator is already assigned', () => {
    const job = buildJob({
      translator: {
        email: 'existing@translated.net',
        delivery_timestamp: 1700000000,
      },
    })
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    expect(input.value).toBe('existing@translated.net')
  })

  test('keeps the send button disabled for an invalid email', () => {
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fireEvent.change(input, {target: {value: 'not-an-email'}})
    fireEvent.keyUp(input)
    expect(screen.getByText('Send Job to Translator')).toBeDisabled()
  })

  test('enables the send button once a valid email is entered', () => {
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input)
    expect(screen.getByText('Send Job to Translator')).toBeEnabled()
  })

  test('updates the delivery date, time and timezone through the child pickers', () => {
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input)

    fireEvent.click(screen.getByText('pick-date'))
    fireEvent.click(screen.getByText('time-14'))
    fireEvent.click(screen.getByText('pick-gmt'))

    expect(screen.getByText('Send Job to Translator')).toBeEnabled()
  })

  test('closes the modal and shows an error notification when the API resolves without a job', async () => {
    addJobTranslator.mockResolvedValue({})
    const closeOutsource = jest.fn()
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={closeOutsource}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input)

    fireEvent.click(screen.getByText('Send Job to Translator'))
    expect(closeOutsource).toHaveBeenCalled()

    await waitFor(() => expect(ModalsActions.onCloseModal).toHaveBeenCalled())
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Problems sending the job',
        type: 'error',
      }),
    )
  })

  test('shows an error notification when the API call rejects', async () => {
    addJobTranslator.mockRejectedValue(new Error('network error'))
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input)

    fireEvent.click(screen.getByText('Send Job to Translator'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Problems sending the job',
          type: 'error',
        }),
      ),
    )
  })

  test('sends a plain "Job sent" notification when the job had no previous translator', async () => {
    addJobTranslator.mockResolvedValue({
      job: {
        password: 'new-pass',
        translator: {email: 'translator@example.com'},
      },
    })
    const job = buildJob()
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input)

    fireEvent.click(screen.getByText('Send Job to Translator'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Job sent', type: 'success'}),
      ),
    )
    expect(ManageActions.changeJobPasswordFromOutsource).toHaveBeenCalled()
    expect(ManageActions.assignTranslator).toHaveBeenCalledWith(
      42,
      7,
      'job-pass',
      {email: 'translator@example.com'},
    )
  })

  test('notifies a delivery date change when the new date differs from the previous one', async () => {
    addJobTranslator.mockResolvedValue({
      job: {
        password: 'new-pass',
        translator: {email: 'translator@example.com'},
      },
    })
    const expected = new Date('2026-08-01T00:00:00.000Z')
    expected.setHours(14)
    expected.setMinutes(0)
    const differentDeliveryDate = new Date(
      expected.getTime() + 24 * 60 * 60 * 1000,
    )

    const job = buildJob({
      translator: {
        email: 'translator@example.com',
        delivery_timestamp: 1700000000,
        delivery_date: differentDeliveryDate.toISOString(),
      },
    })
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input, 'translator@example.com')
    fireEvent.click(screen.getByText('pick-date'))
    fireEvent.click(screen.getByText('time-14'))

    fireEvent.click(screen.getByText('Send Job to Translator'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Job delivery update',
          type: 'success',
        }),
      ),
    )
  })

  test('notifies a mail change when the delivery date is unchanged but the email differs', async () => {
    addJobTranslator.mockResolvedValue({
      job: {
        password: 'new-pass',
        translator: {email: 'new-translator@example.com'},
      },
    })
    const expected = new Date('2026-08-01T00:00:00.000Z')
    expected.setHours(14)
    expected.setMinutes(0)

    const job = buildJob({
      translator: {
        email: 'original@example.com',
        delivery_timestamp: 1700000000,
        delivery_date: expected.toISOString(),
      },
    })
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input, 'new-translator@example.com')
    fireEvent.click(screen.getByText('pick-date'))
    fireEvent.click(screen.getByText('time-14'))

    fireEvent.click(screen.getByText('Send Job to Translator'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: expect.anything(),
          type: 'success',
        }),
      ),
    )
    const call = CatToolActions.addNotification.mock.calls[0][0]
    const {container: titleContainer} = render(call.title)
    expect(titleContainer.textContent).toContain('new password')
  })

  test('sends a plain notification when neither the date nor the email changed', async () => {
    addJobTranslator.mockResolvedValue({
      job: {password: 'new-pass', translator: {email: 'original@example.com'}},
    })
    const expected = new Date('2026-08-01T00:00:00.000Z')
    expected.setHours(14)
    expected.setMinutes(0)

    const job = buildJob({
      translator: {
        email: 'original@example.com',
        delivery_timestamp: 1700000000,
        delivery_date: expected.toISOString(),
      },
    })
    const {container} = render(
      <AssignToTranslator
        job={job}
        project={project}
        closeOutsource={jest.fn()}
      />,
    )
    const input = container.querySelector('input[type=email]')
    fillValidEmail(input, 'original@example.com')
    fireEvent.click(screen.getByText('pick-date'))
    fireEvent.click(screen.getByText('time-14'))

    fireEvent.click(screen.getByText('Send Job to Translator'))

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Job sent', type: 'success'}),
      ),
    )
  })
})
