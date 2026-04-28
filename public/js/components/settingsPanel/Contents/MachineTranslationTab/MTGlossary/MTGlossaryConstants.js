import {getStatusMemoryGlossaryImport} from '../../../../../api/getStatusMemoryGlossaryImport/getStatusMemoryGlossaryImport'

export const MT_GLOSSARY_CREATE_ROW_ID = 'createRow'

export class MTGlossaryStatus {
  constructor() {
    this.wasAborted = false
  }

  get(props, promise = getStatusMemoryGlossaryImport) {
    this.wasAborted = false
    return new Promise((resolve, reject) => {
      this.executeApi({promise, props, resolve, reject})
    })
  }

  cancel() {
    this.wasAborted = true
  }

  executeApi({promise, props, resolve, reject}) {
    const DELAY = 1000

    promise(props).then((data) => {
      if (typeof data?.progress === 'undefined') {
        reject()
        return
      }
      if (data.progress === 0) {
        setTimeout(() => {
          if (!this.wasAborted)
            this.executeApi({promise, props, resolve, reject})
        }, DELAY)
      } else {
        resolve(data)
      }
    })
  }
}
