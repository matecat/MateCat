import React, {useRef, useState} from 'react'
import {Button} from './Button/Button'
import Copy from '../icons/Copy'
import Check from '../../../img/icons/Check'

export const ButtonCopy = (props) => {
  const [wasClicked, setWasClicked] = useState(false)

  const tmOutRef = useRef()

  const {
    onClick,
    tooltip,
    tooltipCopied = 'Copied!',
    iconSize = 16,
    ...rest
  } = props

  const handleClick = (e) => {
    onClick(e)
    setWasClicked(true)
    clearTimeout(tmOutRef.current)
    tmOutRef.current = setTimeout(() => setWasClicked(false), 500)
  }

  return (
    <Button
      {...{
        ...rest,
        onClick: handleClick,
        tooltip: wasClicked ? tooltipCopied : tooltip,
      }}
    >
      {wasClicked ? <Check size={iconSize} /> : <Copy size={iconSize} />}
    </Button>
  )
}
