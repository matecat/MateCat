import React, {createContext} from 'react'
import useAuth from '../../../hooks/useAuth'
export const DataLoaderContext = createContext({})

export const DataLoader = ({children}) => {
  const {isUserLogged, userInfo, connectedServices} = useAuth()

  return (
    <DataLoaderContext.Provider
      value={{isUserLogged, userInfo, connectedServices}}
    >
      {children}
    </DataLoaderContext.Provider>
  )
}
