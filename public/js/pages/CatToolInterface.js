/**
 * Cattool-level settings a plugin can override.
 *
 * Two mechanisms work at once, deliberately, so core and the plugin submodules do
 * not have to land in the same instant:
 *
 *   - new: `catToolInterface.getCharacterCounterMode = fn` sets an own property
 *   - old: `CatToolInterface.prototype.getCharacterCounterMode = fn` still reaches
 *     the singleton through the prototype chain
 *
 * Core reads through the default export at call time, so whichever a deployment's
 * plugins use is seen. Once every plugin assigns on the object, the class can go.
 */
export class CatToolInterface {
  getCharacterCounterMode() {}
}

const catToolInterface = new CatToolInterface()

export default catToolInterface
