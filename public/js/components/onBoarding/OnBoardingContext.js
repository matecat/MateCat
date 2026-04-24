import {createContext} from 'react'

export const OnBoardingContext = createContext({})
export const socialUrls = {
  googleUrl: config.googleAuthURL,
  github: config.githubAuthUrl,
  microsoft: config.microsoftAuthUrl,
  linkedIn: config.linkedInAuthUrl,
  meta: config.facebookAuthUrl,
}
