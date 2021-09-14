import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Set segment to translation on review extended issue panel
 *
 * @param {Object} segment
 * @param {string} [idJob=config.id_job]
 * @param {string} [password=config.password]
 * @param {number} [revisionNumber=config.revisionNumber]
 * @param {string} [currentPassword=config.currentPassword]
 * @returns {Promise<object>}
 */
export const setTranslation = async (
  segment,
  idJob = config.id_job,
  password = config.password,
  revisionNumber = config.revisionNumber,
  currentPassword = config.currentPassword,
) => {
  const {sid, translation, status, segment: segmentDetails} = segment

  const contextBefore = UI.getContextBefore(sid)
  const idBefore = UI.getIdBefore(sid)
  const contextAfter = UI.getContextAfter(sid)
  const idAfter = UI.getIdAfter(sid)
  const translationToSend = TagUtils.prepareTextToSend(translation)
  const time_to_edit = new Date() - UI.editStart

  const dataParams = {
    id_segment: sid,
    id_job: idJob,
    password,
    status,
    translation: translationToSend,
    segment: segmentDetails,
    propagate: false,
    context_before: contextBefore,
    id_before: idBefore,
    context_after: contextAfter,
    id_after: idAfter,
    time_to_edit: time_to_edit,
    revision_number: revisionNumber,
    current_password: currentPassword,
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

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
