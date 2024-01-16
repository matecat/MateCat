import React, {useState} from 'react'
import {Select} from '../common/Select'

export const ProjectTemplate = () => {
  const [templates, setTemplates] = useState([])

  return (
    <div className="settings-panel-project-template">
      <Select
        placeholder="Select template"
        label="Project template"
        id="project-template"
        maxHeightDroplist={100}
        options={templates}
        //   activeOption={activeAddEngine}
        //   onSelect={(option) => {
        //     setActiveAddEngine(option)
        //   }}
      />
    </div>
  )
}
