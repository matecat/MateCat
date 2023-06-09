import React from 'react'

export const CrossLanguagesMatches = () => {
  return (
    <div className="options-box multi-match">
      <h3>Cross-language Matches</h3>
      <p>
        Get translation suggestions in other target languages you know as
        reference.
      </p>
      <select
        name="multi-match-1"
        id="multi-match-1"
        title="Primary language suggestion"
      >
        <option value="">Primary language suggestion</option>
        {/*<option tal:repeat="lang languages_array_obj" tal:content="lang/name" tal:attributes="value lang/code"/>*/}
      </select>
      <select
        name="multi-match-2"
        id="multi-match-2"
        disabled={true}
        title="Secondary language suggestion"
      >
        <option value="">Secondary language suggestion</option>
        {/*<option tal:repeat="lang languages_array_obj" tal:content="lang/name" tal:attributes="value lang/code">Patent
        </option>*/}
      </select>
    </div>
  )
}
