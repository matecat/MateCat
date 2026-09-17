// Wraps the raw {job, project} job-metadata payload so property access
// transparently resolves overrides: a job-level value wins, falling back to
// the project's value and then to any plain root-level field. When both
// sides have a plain object at the same key (e.g. mt_extra), the two are
// shallow-merged (job keys win) instead of one replacing the other outright.
// Arrays are treated as plain values, never merged, so a job's own
// mandatory_issues list replaces the project's rather than being combined
// with it index-by-index.
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
