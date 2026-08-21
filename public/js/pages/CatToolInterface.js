// The character counter preset a deployment wants in the editor. Core ships
// none, so whatever the job or the template already says decides.
// Overridden by plugin.
const catToolInterface = {
  getCharacterCounterMode: () => undefined,
}

export default catToolInterface
