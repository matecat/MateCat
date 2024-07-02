import {useHotkeys} from 'react-hotkeys-hook'

export const UseHotKeysComponent = ({
  shortcut,
  callback,
  keyup = false,
  enableOnContentEditable = true,
}) => {
  useHotkeys(shortcut, callback, {keyup, enableOnContentEditable})
}
