import {createContext} from 'react'

export const SUBTEMPLATE_MODIFIERS = {
  CREATE: 'create',
  UPDATE: 'update',
}

export const isStandardSubTemplate = ({id} = {}) => id === 0

export const SubTemplatesContext = createContext({})
