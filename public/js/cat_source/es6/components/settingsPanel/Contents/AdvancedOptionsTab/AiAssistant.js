import Switch from '../../../common/Switch'

export const AiAssistant = () => {
  return (
    <div className="options-box ai-assistant">
      <h3>Automatic AI assistant</h3>
      <p>
        By default, a button to activate the AI assistant appears under the
        source segment when you highlight a word. If you set this option to
        active, the AI assistant will activate automatically when a word is
        highlighted. The AI assistant can be activated for a maximum or 3 words,
        6 Chinese characters or 10 Japanese characters.
      </p>
      <Switch />
    </div>
  )
}
