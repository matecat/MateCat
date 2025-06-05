import React, {useState} from 'react'
import Cookies from 'js-cookie'
import {Select} from '../common/Select'

export const GMTSelect = ({showLabel, changeValue}) => {
  const timezoneToShow = Cookies.get('matecat_timezone')
  const [timezone, setTimezone] = useState(timezoneToShow)
  const items = [
    {
      name: '(GMT -11:00 ) Midway Islands Time',
      id: '-11',
    },
    {
      name: '(GMT -10:00 ) Hawaii Standard Time',
      id: '-10',
    },
    {
      name: '(GMT -9:00 ) Alaska Standard Time',
      id: '-9',
    },
    {
      name: '(GMT -8:00 ) Pacific Standard Time',
      id: '-8',
    },
    {
      name: '(GMT -7:00 ) Mountain Standard Time',
      id: '-7',
    },
    {
      name: '(GMT -6:00 ) Central Standard Time',
      id: '-6',
    },
    {
      name: '(GMT -5:00 ) Eastern Standard Time',
      id: '-5',
    },
    {
      name: '(GMT -4:00 ) Atlantic Standard Time',
      id: '-4',
    },
    {
      name: '(GMT -3:00 ) Brazil Eastern Time, Argentina Standard Time',
      id: '-3',
    },
    {
      name: '(GMT -2:00 ) South Sandwich Islands',
      id: '-2',
    },
    {
      name: '(GMT -1:00 ) Central African Time',
      id: '-1',
    },
    {
      name: '(GMT) Greenwich Mean Time',
      id: '0',
    },
    {
      name: '(GMT +1:00 ) European Central Time ',
      id: '1',
    },
    {
      name: '(GMT +2:00 ) Eastern European Time',
      id: '2',
    },
    {
      name: '(GMT +3:00 ) Eastern African Time ',
      id: '3',
    },
    {
      name: '(GMT +3.30 ) Middle East Time',
      id: '3.5',
    },
    {
      name: '(GMT +4.00 ) Near East Time',
      id: '4',
    },
    {
      name: '(GMT +5.00 ) Pakistan Lahore Time',
      id: '5',
    },
    {
      name: '(GMT +5.30 ) India Standard Time',
      id: '5.5',
    },
    {
      name: '(GMT +6.00 ) Bangladesh Standard Time ',
      id: '6',
    },
    {
      name: '(GMT +7.00 ) Vietnam Standard Time ',
      id: '7',
    },
    {
      name: '(GMT +8.00 ) China Taiwan Time ',
      id: '8',
    },
    {
      name: '(GMT +9.00 ) Japan Standard Time ',
      id: '9',
    },
    {
      name: '(GMT +9.30 ) Australia Central Time ',
      id: '9.5',
    },
    {
      name: '(GMT +10.00 ) Australia Eastern Time ',
      id: '10',
    },
    {
      name: '(GMT +11.00 ) Solomon Standard Time ',
      id: '11',
    },
    {
      name: '(GMT +12.00 ) New Zealand Standard Time ',
      id: '12',
    },
  ]

  return (
    <Select
      name="gmt-select"
      className="gmt-select"
      label={showLabel ? 'GMT' : null}
      options={items}
      onSelect={(item) => {
        setTimezone(item.id)
        changeValue(item.id)
      }}
      activeOption={items.find(({id}) => id === timezone) || items[0]}
      tooltipPosition="bottom"
      checkSpaceToReverse={false}
      maxHeightDroplist={200}
    />
  )
}
