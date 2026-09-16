export class JobMetadataProxy {
  constructor(jobMetadata) {
    const root = this
    this.proxy = new Proxy(jobMetadata, {
      get(target, prop) {
        const jobValue = target.job?.[prop]
        const projectValue = target.project?.[prop]
        const rootValue = target[prop]

        if (jobValue !== undefined) {
          if (root.isObject(jobValue) && root.isObject(projectValue)) {
            return root.createMergedProxy(jobValue, projectValue)
          }

          return jobValue
        }

        if (projectValue !== undefined) {
          return projectValue
        }

        return rootValue
      },
    })
  }

  createMergedProxy(job, project) {
    return new Proxy(
      {...project, ...job},
      {
        get(_, prop) {
          const jobValue = job?.[prop]
          const projectValue = project?.[prop]

          if (jobValue !== undefined) {
            return jobValue
          }

          return projectValue
        },
      },
    )
  }

  isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
  }
}
