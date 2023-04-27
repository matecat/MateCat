import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Set segment to translation on review extended issue panel
 *
 * @param {Object} segment
 * @param {Object} status
 * @param {string} [idJob=config.id_job]
 * @param {string} translation
 * @param {string} source
 * @param {string} [password=config.password]
 * @param {number} [revisionNumber=config.revisionNumber]
 * @param {number} [chosenSuggestionIndex]
 * @param {string} [currentPassword=config.currentPassword]
 * @param {bool} autosave
 * @param {bool} propagate
 * @param {Object} splitStatuses
 * @returns {Promise<object>}
 */
export const setTranslation = async ({
  segment,
  translation,
  source,
  idJob = config.id_job,
  password = config.password,
  status = segment.status,
  revisionNumber = config.revisionNumber,
  currentPassword = config.currentPassword,
  chosenSuggestionIndex = null,
  propagate = false,
  splitStatuses = null,
}) => {
  const {sid, segment: segmentDetails, charactersCounter = 0} = segment

  const contextBefore = UI.getContextBefore(sid)
  const idBefore = UI.getIdBefore(sid)
  const contextAfter = UI.getContextAfter(sid)
  const idAfter = UI.getIdAfter(sid)
  const time_to_edit = UI.editTime ? UI.editTime : new Date() - UI.editStart
  const translationToSend = translation
    ? translation
    : TagUtils.prepareTextToSend(segment.translation)

  const dataParams = {
    id_segment: sid,
    id_job: idJob,
    password,
    status,
    translation: translationToSend,
    segment: source ? source : segmentDetails,
    time_to_edit: time_to_edit,
    chosen_suggestion_index: chosenSuggestionIndex,
    propagate: propagate,
    context_before: contextBefore,
    id_before: idBefore,
    context_after: contextAfter,
    id_after: idAfter,
    revision_number: revisionNumber,
    current_password: currentPassword,
    splitStatuses,
    characters_counter: charactersCounter,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `${getMatecatApiDomain()}?action=setTranslation`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject({response})

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({errors})

  return data
}
