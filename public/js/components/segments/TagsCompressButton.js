import React, {useState, useEffect} from 'react'
import CatToolActions from '../../actions/CatToolActions'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'

export const TagsCompressButton = () => {
  const [compressed, setCompressed] = useState(
    CatToolStore.isPhTagsCompressed(),
  )

  useEffect(() => {
    const handler = () => setCompressed(CatToolStore.isPhTagsCompressed())
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      handler,
    )
    return () =>
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
        handler,
      )
  }, [])

  return (
    <Button
      className="segment-target-toolbar-icon"
      size={BUTTON_SIZE.ICON_SMALL}
      mode={BUTTON_MODE.OUTLINE}
      active={compressed}
      title={compressed ? 'Expand ph tags' : 'Compress ph tags'}
      onClick={() => CatToolActions.togglePhTagsCompressed()}
    >
      <svg
        width="16"
        height="16"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M5.5,3 C4.67157,3 4,3.67157 4,4.5 L4,9.58579 C4,9.98361 4.15804,10.3654 4.43934,10.6464 L12.4393,18.6464 C13.0251,19.2322 13.9749,19.2322 14.5607,18.6464 L19.6464,13.5607 C20.2322,12.9749 20.2322,12.0251 19.6464,11.4393 L11.6464,3.43934 C11.3651,3.15804 10.9836,3 10.5858,3 L5.5,3 Z M7.5,8 C6.94772,8 6.5,7.55228 6.5,7 C6.5,6.44772 6.94772,6 7.5,6 C8.05228,6 8.5,6.44772 8.5,7 C8.5,7.55228 8.05228,8 7.5,8 Z"
          fill="currentColor"
          fillRule="nonzero"
        />
      </svg>
    </Button>
  )
}
