import {getMatecatApiDomain} from '../../utils/getMatecatApiDomain'

/**
 * Change name of project
 *
 * @param {string} idProject
 * @param {string} password
 * @param {string} name
 * @returns {Promise<object>}
 */
export const changeProjectName = async ({
  idProject,
  passwordProject,
  newName,
}) => {
  const paramsData = {
    name: newName,
  }

  const formData = new FormData()

  Object.keys(paramsData).forEach((key) => {
    formData.append(key, paramsData[key])
  })

  const response = await fetch(
    `${getMatecatApiDomain()}api/app/projects/${idProject}/${passwordProject}/change-name`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) {
    if (response.headers.get('Content-Length') !== '0') {
      const data = await response.json()
      return Promise.reject({response, errors: data.errors ?? data})
    } else {
      return Promise.reject({response})
    }
  }

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject({response, errors})

  return data
}
