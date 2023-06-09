import React from 'react'

export const SegmentationRule = () => {
  return (
    <div className="options-box seg_rule">
      {/* Segmentation Rule
      TODO: disabled in cattool + Select
    */}
      <h3>Segmentation Rules</h3>
      <p>
        Select how sentences are split according to specific types of content.
      </p>
      <select name="segm_rule" id="segm_rule">
        <option value="">General</option>
        <option value="patent">Patent</option>
        <option value="paragraph">Paragraph (beta)</option>
      </select>
    </div>
  )
}
